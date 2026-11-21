<?php
/* Copyright (C) 2026 Kreativität Works <mail@kreativitat.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

use Luracast\Restler\RestException;

/** Strict, preview-bound adapter around native KreaImmo business lifecycles. */
class KreaImmoApiManagement
{
	/** @var DoliDB Native database connection */
	private $db;
	/** @var KreaImmoApi Public read projection provider */
	private $api;
	/** @var string Public resource name */
	private $resource;
	/** @var array<string,mixed> Trusted registry definition */
	private $definition;
	/** @var User Authenticated native actor */
	private $user;
	/** @var int Selected company */
	private $entity;

	/**
	 * @param DoliDB $db Native connection
	 * @param KreaImmoApi $api Public projection provider
	 * @param string $resource Public resource
	 * @param array<string,mixed> $definition Trusted read definition
	 */
	public function __construct($db, $api, $resource, array $definition)
	{
		global $conf;
		$this->db = $db;
		$this->api = $api;
		$this->resource = $resource;
		$this->definition = $definition;
		$this->user = DolibarrApiAccess::$user;
		$this->entity = (int) $conf->entity;
	}

	/**
	 * Execute one versioned, confirmed operation without guessing financial data.
	 *
	 * @param mixed $data JSON request object
	 * @return array<string,mixed> Versioned result and related receipt effects
	 */
	public function manage($data)
	{
		global $conf;
		if (!is_array($data) || array_diff(array_keys($data), array('operation', 'record_id', 'fields', 'confirm', 'dry_run', 'expected_hash'))) {
			throw new RestException(400, 'Expected a management object with documented fields');
		}
		$operation = isset($data['operation']) ? $data['operation'] : '';
		if (!is_string($operation) || !in_array($operation, array('create', 'update', 'delete', 'set_status', 'validate', 'post_bank'), true)) {
			throw new RestException(400, 'Unknown management operation');
		}
		if (!isset($data['confirm']) || $data['confirm'] !== true || (array_key_exists('dry_run', $data) && !is_bool($data['dry_run']))) {
			throw new RestException(400, 'confirm=true and a boolean dry_run are required');
		}
		$dryRun = isset($data['dry_run']) ? $data['dry_run'] : true;
		$id = $operation === 'create' ? null : $this->identifier(isset($data['record_id']) ? $data['record_id'] : null, 'record_id');
		if ($operation === 'create' && array_key_exists('record_id', $data)) {
			throw new RestException(400, 'create does not accept record_id');
		}
		$fields = isset($data['fields']) ? $data['fields'] : array();
		if (is_object($fields) && get_class($fields) === 'stdClass') {
			$fields = (array) $fields;
		}
		if (!is_array($fields)) {
			throw new RestException(400, 'fields must be an object');
		}
		$this->permission($operation === 'delete' ? 'delete' : 'write', $this->definition['permission']);
		$this->validateOperation($operation, $fields);
		$request = array('operation' => $operation, 'fields' => (object) $fields);
		if ($id !== null) {
			$request['record_id'] = $id;
		}
		if (!$dryRun && (!isset($data['expected_hash']) || !is_string($data['expected_hash']) || !preg_match('/^[a-f0-9]{64}$/D', $data['expected_hash']))) {
			throw new RestException(400, 'Apply requires the expected_hash returned by a matching preview');
		}
		$lock = null;
		$level = $this->db->transaction_opened;
		$started = false;
		try {
			if (!$dryRun) {
				$lock = $this->lockCompany();
			}
			if (!$this->db->begin() || !$this->db->query('SAVEPOINT kreaimmo_management')) {
				throw new RestException(500, 'Unable to begin management transaction');
			}
			$started = true;
			$object = $this->definition['object'];
			if ($id !== null && $object->fetch($id) <= 0) {
				throw new RestException(404, 'KreaImmo record not found');
			}
			$receipt = null;
			if ($this->resource === 'payments') {
				$this->permission('write', 'receipt');
				$receiptId = $id === null ? (isset($fields['fk_receipt']) ? $fields['fk_receipt'] : null) : $object->fk_receipt;
				$receipt = $this->fetchParent('receipt', $this->identifier($receiptId, 'fk_receipt'), !$dryRun);
			}
			$hash = hash_init('sha256');
			hash_update($hash, $this->canonical(array('entity_id' => $this->entity, 'currency' => (string) $conf->currency, 'resource' => $this->resource, 'request' => $request)));
			$this->hashQuery($hash, 'SELECT t.* FROM '.$this->db->prefix().$object->table_element.' AS t WHERE '.$object->getKreaImmoScopeSQL('t').' ORDER BY t.rowid'.(!$dryRun ? ' FOR UPDATE' : ''));
			if ($id !== null && $object->fetch($id) <= 0) {
				throw new RestException(404, 'KreaImmo record not found');
			}
			$before = $id === null ? null : $this->api->getRecord($this->resource, $id)['record'];
			$this->prepare($object, $operation, $fields, $receipt, $hash, !$dryRun);
			$effects = $this->receiptEffects($receipt, $object, $operation);
			if ($receipt) {
				hash_update($hash, $this->canonical($this->rawRow($receipt)));
			}
			$after = $this->plannedRecord($object, $operation, $before, $fields);
			hash_update($hash, $this->canonical(array('after' => $after, 'effects' => $effects)));
			$expectedHash = hash_final($hash);
			if (!$dryRun && !hash_equals($expectedHash, $data['expected_hash'])) {
				throw new RestException(409, 'stale_preview: preview this exact request again before applying');
			}
			$status = 'dry_run';
			if (!$dryRun) {
				$this->apply($object, $operation, $fields);
				$id = (int) $object->id;
				if ($receipt && in_array($operation, array('create', 'update', 'delete'), true)) {
					$this->syncReceipt($receipt);
					$effects[0]['after'] = $this->api->getRecord('receipts', $receipt->id)['record'];
				}
				$after = $operation === 'delete' ? null : $this->api->getRecord($this->resource, $id)['record'];
				if ($after !== null) {
					$this->verifyFields($after, $fields, $operation);
				}
				$status = $before !== null && $before === $after ? 'unchanged' : 'confirmed';
			}
			$result = array('schema' => 'kreaimmo_management_v1', 'entity_id' => $this->entity, 'resource' => $this->resource, 'currency' => (string) $conf->currency,
				'operation' => $operation, 'dry_run' => $dryRun, 'status' => $status, 'record_id' => $id,
				'expected_hash' => $expectedHash, 'request' => $request, 'before' => $before, 'after' => $after, 'effects' => $effects);
			if (!$this->db->query('RELEASE SAVEPOINT kreaimmo_management') || !$this->db->commit()) {
				throw new RestException(503, 'Management commit outcome unknown; inspect records before retrying');
			}
			$started = false;
			return $result;
		} catch (Throwable $error) {
			if ($started) {
				while ($this->db->transaction_opened > $level + 1) {
					$this->db->rollback();
				}
				$this->db->query('ROLLBACK TO SAVEPOINT kreaimmo_management');
				$this->db->rollback();
			}
			throw $error;
		} finally {
			if ($lock !== null) {
				$this->db->query("SELECT RELEASE_LOCK('".$this->db->escape($lock)."')");
			}
		}
	}

	/** @param string $permission Native operation @param string $domain Native domain @return void */
	private function permission($permission, $domain)
	{
		if (!$this->user->hasRight('kreaimmo', $permission) && !$this->user->hasRight('kreaimmo', $domain, $permission)) {
			throw new RestException(403, 'KreaImmo '.$domain.' '.$permission.' permission required');
		}
	}

	/** @param string $operation Operation @param array<string,mixed> $fields Input @return void */
	private function validateOperation($operation, array $fields)
	{
		$statuses = array('owners' => array(0, 1, 9), 'properties' => array(0, 1, 9), 'renters' => array(0, 1, 9), 'leases' => array(0, 1), 'costs' => array(0, 1, -2));
		if ($operation === 'set_status') {
			if (!isset($statuses[$this->resource]) || array_keys($fields) !== array('status') || !is_int($fields['status']) || !in_array($fields['status'], $statuses[$this->resource], true)) {
				throw new RestException(400, 'Unsupported resource status transition');
			}
		} elseif ($operation === 'validate') {
			if ($this->resource !== 'receipts' || $fields) {
				throw new RestException(400, 'validate supports receipts and no editable fields');
			}
		} elseif ($operation === 'post_bank') {
			if ($this->resource !== 'payments' || array_keys($fields) !== array('fk_account')) {
				throw new RestException(400, 'post_bank requires a payment and fk_account only');
			}
			$this->identifier($fields['fk_account'], 'fk_account');
		} elseif ($operation === 'delete') {
			if ($fields) {
				throw new RestException(400, 'delete accepts no editable fields');
			}
		} elseif (!$fields) {
			throw new RestException(400, 'create and update require editable fields');
		}
	}

	/** @return array<string,array<string,mixed>> Fixed editable contracts */
	private function contracts()
	{
		return array(
			'owners' => array('required' => 'firstname,lastname,email', 'fields' => 'ref,fk_soc,fk_owner_type,societe,firstname,lastname,address,zip,town,country_id,email,phone,phone_mobile'),
			'buildings' => array('required' => 'label,fk_property', 'fields' => 'label,fk_property'),
			'properties' => array('required' => 'label,property_type_id,fk_owner', 'fields' => 'label,property_type_id,fk_owner,fk_property,fk_soc,juridique_id,datebuilt,target,area,numroom,numflat,numdoor,numfloor,staircase,building,address,zip,town,country_id'),
			'renters' => array('required' => 'lastname', 'fields' => 'ref,fk_soc,societe,firstname,lastname,email,phone,phone_mobile,town,country_id'),
			'leases' => array('required' => 'fk_property,fk_owner,fk_renter,rentamount,chargesamount,date_start', 'fields' => 'fk_property,fk_owner,fk_renter,rentamount,chargesamount,date_start,fk_account,fk_soc,location_type_id,vat,deposit,periode,preavis,date_end,date_next_rent,date_last_regul,date_last_regul_charge'),
			'receipts' => array('required' => 'fk_rent,label,rentamount,chargesamount,date_start,date_end,date_echeance', 'fields' => 'fk_rent,label,rentamount,chargesamount,date_start,date_end,date_echeance,ref,vat_amount,vat_tx'),
			'payments' => array('required' => 'fk_receipt,amount,date_payment,fk_mode_reglement', 'fields' => 'fk_receipt,amount,date_payment,fk_mode_reglement,num_payment'),
			'costs' => array('required' => 'label,fk_property,amount,amount_ht,amount_vat,date_start,date_end', 'fields' => 'label,fk_property,amount,amount_ht,amount_vat,date_start,date_end,ref,fk_owner,fk_soc,fk_cost_type,dispatch'),
			'meters' => array('required' => 'label,compteur_type_id,date_relever,qty,fk_immoproperty', 'fields' => 'label,compteur_type_id,date_relever,qty,fk_immoproperty'),
		);
	}

	/**
	 * Validate all fields and references before any business write.
	 * @param CommonObjectKreaImmo $object Proposed native object
	 * @param string $operation Operation
	 * @param array<string,mixed> $fields Input
	 * @param KreaImmoReceipt|null $receipt Locked related receipt
	 * @param resource|HashContext $hash Incremental state hash
	 * @param bool $locking Whether rows must be locked
	 * @return void
	 */
	private function prepare($object, $operation, array $fields, $receipt, $hash, $locking)
	{
		if (in_array($operation, array('create', 'update'), true)) {
			$contract = $this->contracts()[$this->resource];
			$allowed = explode(',', $contract['fields']);
			if (array_diff(array_keys($fields), $allowed)) {
				throw new RestException(400, 'Unknown, protected or computed management field');
			}
			if ($operation === 'create' && array_diff(explode(',', $contract['required']), array_keys($fields))) {
				throw new RestException(400, 'Missing required create fields: '.$contract['required']);
			}
			foreach ($fields as $field => $value) {
				$validated = $this->fieldValue($field, $value);
				if ($operation === 'update' && $field === 'ref' && (string) $object->ref !== $validated) {
					$this->assertNoDocuments($object);
				}
				if ($operation === 'update' && strpos($field, 'fk_') === 0 && (int) $object->$field !== (int) $validated) {
					$this->assertUnreferenced($object, $hash, $locking);
				}
				if ($operation === 'update' && in_array($this->resource, array('receipts', 'payments'), true) && in_array($field, array('fk_rent', 'fk_receipt'), true) && (int) $object->$field !== $validated) {
					throw new RestException(409, 'Financial records cannot move to another parent');
				}
				$object->$field = $validated;
			}
			if ($operation === 'create') {
				if (isset($object->fields['entity'])) {
					$object->entity = $this->entity;
				}
				if (isset($object->fields['status'])) {
					$object->status = 0;
				}
				if (isset($object->fields['civility_id'])) {
					$object->civility_id = 0;
				}
				if ($this->resource === 'leases' && empty($object->preavis)) {
					$object->preavis = 1;
				}
			}
			$this->deriveFields($object, $operation, $receipt, $hash, $locking);
			if ($this->resource === 'renters' && !$this->user->hasRight('societe', 'client', 'voir')) {
				$result = $this->query('SELECT fk_soc FROM '.$this->db->prefix().'societe_commerciaux WHERE fk_user='.(int) $this->user->id.' AND fk_soc='.(int) $object->fk_soc);
				if (!$this->db->fetch_object($result)) {
					throw new RestException(403, 'Renter third-party assignment is required for this reader');
				}
			}
			if (!$object->checkKreaImmoWriteAccess($operation === 'create')) {
				throw new RestException(403, 'Company ownership or linked record access denied');
			}
			if ($this->resource === 'receipts' && $operation === 'update' && (int) $object->status !== 0) {
				throw new RestException(409, 'Only draft receipts can be edited');
			}
		}
		if ($this->resource === 'payments' && $operation !== 'create' && !empty($object->fk_bank)) {
			if ($operation !== 'delete') {
				throw new RestException(409, 'Posted payments cannot be edited or posted again');
			}
			$this->assertBankAccount((int) $object->fk_account, $hash, $locking);
			$result = $this->query('SELECT rowid, rappro FROM '.$this->db->prefix().'bank WHERE rowid='.(int) $object->fk_bank.($locking ? ' FOR UPDATE' : ''));
			$line = $this->db->fetch_object($result);
			if (!$line || (int) $line->rappro !== 0) {
				throw new RestException(409, 'Reconciled or missing bank evidence prevents payment deletion');
			}
			hash_update($hash, $this->canonical((array) $line));
			$this->verifyBankPosting($object);
		}
		if ($operation === 'delete') {
			if ($this->resource === 'receipts' && (int) $object->status !== 0) {
				throw new RestException(409, 'Only draft unpaid receipts can be deleted');
			}
			$this->assertUnreferenced($object, $hash, $locking);
			$this->assertNoDocuments($object);
		}
		if ($operation === 'validate') {
			if ((int) $object->status !== 0) {
				throw new RestException(409, 'Only a draft receipt can be validated');
			}
			$this->assertNoDocuments($object);
			$this->assertNoPayments($object->id);
		}
		if ($operation === 'set_status' && $this->resource === 'renters' && $fields['status'] === 1) {
			if ((int) $object->status === 0) {
				$this->assertNoDocuments($object);
			}
		}
		if ($operation === 'set_status' && $this->resource === 'renters') {
			$transitions = array(0 => array(0, 1), 1 => array(0, 1, 9), 9 => array(0, 1, 9));
			if (!isset($transitions[(int) $object->status]) || !in_array($fields['status'], $transitions[(int) $object->status], true)) {
				throw new RestException(409, 'Unsupported native renter status transition');
			}
		}
		if ($operation === 'post_bank') {
			$this->assertBankAccount($fields['fk_account'], $hash, $locking);
			$object->fk_account = $fields['fk_account'];
		}
	}

	/** @param string $field Native field @param mixed $value Input @return int|string Validated value */
	private function fieldValue($field, $value)
	{
		if (strpos($field, 'fk_') === 0 || in_array($field, array('country_id', 'property_type_id', 'compteur_type_id'), true)) {
			return $this->identifier($value, $field);
		}
		$enums = array('vat' => array(0, 1), 'location_type_id' => array(1, 2), 'juridique_id' => array(1, 2), 'datebuilt' => array(1, 2, 3, 4, 5), 'preavis' => array(1, 2), 'target' => array(-1, 1, 2), 'dispatch' => array(0, 1));
		if (isset($enums[$field])) {
			if (!is_int($value) || !in_array($value, $enums[$field], true)) {
				throw new RestException(400, 'Invalid enum value for '.$field);
			}
			return $value;
		}
		if (in_array($field, array('numroom', 'vat_tx'), true)) {
			if (!is_int($value) || $value < 0 || $value > ($field === 'vat_tx' ? 100 : 10000)) {
				throw new RestException(400, 'Invalid nonnegative integer for '.$field);
			}
			return $value;
		}
		if (in_array($field, array('amount', 'amount_ht', 'amount_vat', 'rentamount', 'chargesamount', 'deposit', 'vat_amount'), true)) {
			return $this->money($this->cents($value, 400));
		}
		if (strpos($field, 'date_') === 0) {
			if (!is_string($value) || !preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/D', $value, $parts) || !checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]) || (int) $parts[1] < 1970 || (int) $parts[1] > 2099) {
				throw new RestException(400, $field.' requires a calendar date YYYY-MM-DD from 1970 to 2099');
			}
			return dol_mktime(12, 0, 0, (int) $parts[2], (int) $parts[3], (int) $parts[1]);
		}
		if ($field === 'qty') {
			if (!is_string($value) || !preg_match('/^[0-9]{1,12}(?:\.[0-9]{1,8})?$/D', $value)) {
				throw new RestException(400, 'qty must be a nonnegative decimal string with at most eight fractional digits');
			}
			return $value;
		}
		$lengths = array('ref' => 128, 'societe' => 128, 'zip' => 32, 'town' => 64, 'phone' => 30, 'phone_mobile' => 30, 'area' => 8, 'numflat' => 8, 'numdoor' => 8, 'numfloor' => 8, 'staircase' => 8, 'building' => 32, 'datebuilt' => 32, 'num_payment' => 50, 'periode' => 128);
		if (!is_string($value) || preg_match('/[\x00-\x1f\x7f<>]/', $value) || dol_strlen($value) > (isset($lengths[$field]) ? $lengths[$field] : 255) || (in_array($field, array('ref', 'label', 'lastname'), true) && trim($value) === '')) {
			throw new RestException(400, 'Invalid text for '.$field);
		}
		if ($field === 'email' && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
			throw new RestException(400, 'Invalid email address');
		}
		if ($this->resource === 'owners' && in_array($field, array('firstname', 'email'), true) && trim($value) === '') {
			throw new RestException(400, 'Owner '.$field.' is required by the native object');
		}
		if ($field === 'ref' && dol_string_nospecial($value) !== $value) {
			throw new RestException(400, 'ref must already conform to native reference sanitization');
		}
		return $value;
	}

	/** @param mixed $value Integer ID @param string $field Name @return int */
	private function identifier($value, $field)
	{
		if (!is_int($value) || $value < 1 || $value > 2147483647) {
			throw new RestException(400, $field.' must be a positive integer');
		}
		return $value;
	}

	/** @param mixed $value Stored or requested amount @param int $status Invalid-input status @return int Exact cents */
	private function cents($value, $status = 409)
	{
		if (!is_string($value) || !preg_match('/^([0-9]{1,12})(?:\.([0-9]{1,8}))?$/D', $value, $parts)) {
			throw new RestException($status, 'Money must be a nonnegative exact-cent decimal string with at most 12 integer digits');
		}
		$fraction = isset($parts[2]) ? $parts[2] : '';
		if (trim(substr($fraction, 2), '0') !== '') {
			throw new RestException($status, 'Sub-cent monetary precision requires review before management');
		}
		return (int) $parts[1] * 100 + (int) str_pad(substr($fraction, 0, 2), 2, '0');
	}

	/** @param int $cents Exact nonnegative cents @return string Decimal amount */
	private function money($cents)
	{
		if ($cents < 0 || $cents > 99999999999999) {
			throw new RestException(409, 'Monetary total is outside the supported management range');
		}
		return (string) intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
	}

	/** @param string $model Parent model @param int $id Parent ID @param bool $locking Lock row @return CommonObjectKreaImmo */
	private function fetchParent($model, $id, $locking = false)
	{
		$classes = array('owner' => 'KreaImmoOwner', 'property' => 'KreaImmoProperty', 'renter' => 'KreaImmoRenter', 'rent' => 'KreaImmoRent', 'receipt' => 'KreaImmoReceipt');
		dol_include_once('/kreaimmo/class/kreaimmo'.$model.'.class.php');
		$class = $classes[$model];
		$object = new $class($this->db);
		if ($locking) {
			$this->query('SELECT t.rowid FROM '.$this->db->prefix().$object->table_element.' AS t WHERE t.rowid = '.((int) $id).' AND '.$object->getKreaImmoScopeSQL('t').' FOR UPDATE');
		}
		if ($object->fetch($id) <= 0) {
			throw new RestException(403, 'Referenced '.$model.' is unavailable in the selected company');
		}
		if ($model === 'renter') {
			$this->api->getRecord('renters', $id);
		}
		return $object;
	}

	/**
	 * Derive relationships and totals from verified native parents.
	 * @param CommonObjectKreaImmo $object Proposed object
	 * @param string $operation Operation
	 * @param KreaImmoReceipt|null $receipt Related receipt
	 * @param resource|HashContext $hash Incremental hash
	 * @param bool $locking Lock parent records
	 * @return void
	 */
	private function deriveFields($object, $operation, $receipt, $hash, $locking)
	{
		if (in_array($this->resource, array('properties', 'buildings', 'leases', 'costs', 'meters'), true)) {
			$field = $this->resource === 'meters' ? 'fk_immoproperty' : 'fk_property';
			if (!empty($object->$field)) {
				$parent = $this->fetchParent('property', (int) $object->$field, $locking);
				hash_update($hash, $this->canonical($this->rawRow($parent)));
				if ($this->resource === 'properties' && (int) $parent->id === (int) $object->id) {
					throw new RestException(409, 'A property cannot be its own parent');
				}
				if ($this->resource === 'properties') {
					$ancestor = $parent;
					$seen = array();
					while (!empty($ancestor->fk_property)) {
						if (isset($seen[$ancestor->id]) || count($seen) >= 100 || (int) $ancestor->fk_property === (int) $object->id) {
							throw new RestException(409, 'Property ancestry contains a cycle or exceeds the supported depth');
						}
						$seen[$ancestor->id] = true;
						$ancestor = $this->fetchParent('property', (int) $ancestor->fk_property, $locking);
						hash_update($hash, $this->canonical($this->rawRow($ancestor)));
					}
				}
				if ($this->resource === 'leases' && (int) $parent->fk_owner !== (int) $object->fk_owner) {
					throw new RestException(409, 'Lease owner must match the selected property owner');
				}
			}
		}
		if ($this->resource === 'leases') {
			$renter = $this->fetchParent('renter', (int) $object->fk_renter, $locking);
			hash_update($hash, $this->canonical($this->rawRow($renter)));
			$object->totalamount = $this->money($this->objectCents($object, 'rentamount') + $this->objectCents($object, 'chargesamount'));
		}
		if ($this->resource === 'receipts') {
			$lease = $this->fetchParent('rent', (int) $object->fk_rent, $locking);
			hash_update($hash, $this->canonical($this->rawRow($lease)));
			foreach (array('fk_property', 'fk_renter', 'fk_owner', 'fk_soc') as $field) {
				$object->$field = $lease->$field;
			}
			$object->total_amount = $this->money($this->objectCents($object, 'rentamount') + $this->objectCents($object, 'chargesamount'));
			if ($operation !== 'create') {
				$this->assertNoPayments($object->id);
			}
			$object->paye = 0;
			$object->partial_payment = '0.00';
			$object->balance = $object->total_amount;
		}
		if ($this->resource === 'payments') {
			if ((int) $receipt->status !== 1) {
				throw new RestException(409, 'Payments require a validated receipt');
			}
			foreach (array('fk_rent', 'fk_property', 'fk_renter', 'fk_owner', 'fk_soc') as $field) {
				$object->$field = $receipt->$field;
			}
			$object->fk_payment = (int) $object->fk_mode_reglement;
			$method = $this->query('SELECT id FROM '.$this->db->prefix().'c_paiement WHERE id = '.((int) $object->fk_mode_reglement).' AND active = 1 AND entity IN ('.getEntity('c_paiement').')');
			if (!$this->db->fetch_object($method)) {
				throw new RestException(400, 'An active native payment method is required');
			}
			if ($this->cents((string) $object->amount) <= 0) {
				throw new RestException(400, 'Payment amount must be positive');
			}
		}
		if ($this->resource === 'costs' && $this->objectCents($object, 'amount') !== $this->objectCents($object, 'amount_ht') + $this->objectCents($object, 'amount_vat')) {
			throw new RestException(400, 'Cost amount must equal amount_ht plus amount_vat');
		}
		if (isset($object->date_start, $object->date_end) && $object->date_start && $object->date_end && $object->date_start > $object->date_end) {
			throw new RestException(400, 'date_start must not follow date_end');
		}
	}

	/** @param CommonObjectKreaImmo $object Object @return array<string,mixed> Stored row */
	private function rawRow($object)
	{
		$result = $this->query('SELECT t.* FROM '.$this->db->prefix().$object->table_element.' AS t WHERE t.rowid = '.((int) $object->id).' AND '.$object->getKreaImmoScopeSQL('t'));
		$row = $this->db->fetch_object($result);
		if (!$row) {
			throw new RestException(409, 'Referenced state changed during management');
		}
		return (array) $row;
	}

	/** @param int $receiptId Receipt ID @return int Exact linked payment cents */
	private function paidCents($receiptId)
	{
		$result = $this->query('SELECT rowid, entity, amount FROM '.$this->db->prefix().'kreaimmo_immopayment WHERE fk_receipt = '.((int) $receiptId).' ORDER BY rowid');
		$total = 0;
		while ($row = $this->db->fetch_object($result)) {
			if ((int) $row->entity !== $this->entity) {
				throw new RestException(409, 'Receipt has foreign-company payment evidence');
			}
			$total += $this->cents((string) $row->amount);
			$this->money($total);
		}
		return $total;
	}

	/** @param int $id Receipt ID @return void */
	private function assertNoPayments($id)
	{
		$result = $this->query('SELECT rowid FROM '.$this->db->prefix().'kreaimmo_immopayment WHERE fk_receipt = '.((int) $id).$this->db->plimit(1));
		if ($this->db->fetch_object($result)) {
			throw new RestException(409, 'Receipt payment evidence prevents this edit or deletion');
		}
	}

	/** @param KreaImmoReceipt|null $receipt Related receipt @param CommonObjectKreaImmo $object Target @param string $operation Operation @return array<int,array<string,mixed>> */
	private function receiptEffects($receipt, $object, $operation)
	{
		if (!$receipt || !in_array($operation, array('create', 'update', 'delete'), true)) {
			return array();
		}
		$paid = $this->paidCents($receipt->id);
		if ($operation !== 'create') {
			$stored = $this->rawRow($object);
			$paid -= $this->cents((string) $stored['amount']);
		}
		if ($operation !== 'delete') {
			$paid += $this->cents((string) $object->amount);
		}
		$before = $this->api->getRecord('receipts', $receipt->id)['record'];
		$total = $this->cents((string) $before['total_amount']);
		if ($paid > $total || $paid < 0) {
			throw new RestException(409, 'Payment would overpay the receipt or contradict its recorded evidence');
		}
		$after = $before;
		$after['partial_payment'] = $this->storedValue($receipt, 'partial_payment', $this->money($paid));
		$after['balance'] = $this->storedValue($receipt, 'balance', $this->money($total - $paid));
		$after['paye'] = $paid === 0 ? 0 : ($paid === $total ? 1 : 2);
		return array(array('resource' => 'receipts', 'record_id' => (int) $receipt->id, 'before' => $before, 'after' => $after));
	}

	/** @param KreaImmoReceipt $receipt Locked receipt @return void */
	private function syncReceipt($receipt)
	{
		$paid = $this->paidCents($receipt->id);
		$total = $this->cents((string) $receipt->total_amount);
		$receipt->partial_payment = $this->money($paid);
		$receipt->balance = $this->money($total - $paid);
		$receipt->paye = $paid === 0 ? 0 : ($paid === $total ? 1 : 2);
		if ($receipt->update($this->user) <= 0) {
			throw new RestException(500, 'Native receipt payment-summary update failed');
		}
	}

	/** @param CommonObjectKreaImmo $object Target @param string $operation Operation @param array<string,mixed>|null $before Public state @param array<string,mixed> $fields Input @return array<string,mixed>|null */
	private function plannedRecord($object, $operation, $before, array $fields)
	{
		if ($operation === 'delete') {
			return null;
		}
		$record = array('id' => $before === null ? null : $before['id'], 'entity' => $this->entity);
		foreach ($this->definition['fields'] as $field) {
			$value = isset($object->$field) ? $object->$field : null;
			$record[$field] = $this->storedValue($object, $field, $value);
		}
		if ($before === null && array_key_exists('ref', $record) && (empty($object->ref) || in_array($this->resource, array('properties', 'leases', 'meters'), true))) {
			$record['ref'] = null;
		}
		if ($operation === 'set_status') {
			$record['status'] = $fields['status'];
		} elseif ($operation === 'validate') {
			$record['status'] = 1;
		}
		if (($operation === 'validate' || ($operation === 'set_status' && $this->resource === 'renters' && (int) $before['status'] === 0 && $fields['status'] === 1)) && (empty($object->ref) || preg_match('/^[\(]?PROV/i', $object->ref))) {
			if ($this->resource === 'receipts') {
				$numbering = $object;
			} else {
				dol_include_once('/kreaimmo/class/kreaimmoreceipt.class.php');
				$numbering = new KreaImmoReceipt($this->db);
			}
			$reference = $numbering->getNextNumRef();
			if (!is_string($reference) || $reference === '') {
				throw new RestException(409, 'Native numbering could not produce a reviewable reference');
			}
			$record['ref'] = $reference;
		}
		return $record;
	}

	/** @param CommonObjectKreaImmo $object Native object @param string $field Monetary field @return int */
	private function objectCents($object, $field)
	{
		$value = isset($object->$field) ? $object->$field : null;
		if ($value === null && isset($object->fields[$field]['notnull']) && $object->fields[$field]['notnull'] === -1) {
			return 0;
		}
		return $this->cents((string) $value);
	}

	/**
	 * Mirror native zero-to-NULL semantics and declared storage formats for previews.
	 * @param CommonObjectKreaImmo $object Native object
	 * @param string $field Public native field
	 * @param mixed $value Proposed value
	 * @return int|string|null Stored public representation
	 */
	private function storedValue($object, $field, $value)
	{
		$metadata = $object->fields[$field];
		$numeric = $object->isInt($metadata) || $object->isFloat($metadata);
		if (isset($metadata['notnull']) && (int) $metadata['notnull'] === -1 && ($value === null || $value === '' || ($numeric && is_numeric($value) && (float) $value == 0))) {
			return null;
		}
		if ($value === null) {
			return $numeric && !empty($metadata['notnull']) ? 0 : null;
		}
		if (strpos($field, 'date_') === 0 && is_numeric($value)) {
			$date = $this->db->idate($value);
			return in_array($this->resource, array('leases', 'costs', 'meters'), true) ? substr($date, 0, 10) : $date;
		}
		if (in_array($field, array('rentamount', 'chargesamount', 'totalamount', 'deposit', 'encours', 'total_amount', 'partial_payment', 'balance', 'vat_amount', 'amount', 'amount_ht', 'amount_vat'), true)) {
			return $this->money($this->cents((string) $value)).'000000';
		}
		$integers = array('status', 'property_type_id', 'juridique_id', 'datebuilt', 'target', 'country_id', 'location_type_id', 'vat', 'preavis', 'numroom', 'paye', 'vat_tx', 'dispatch', 'compteur_type_id');
		return strpos($field, 'fk_') === 0 || in_array($field, $integers, true) ? (int) $value : (string) $value;
	}

	/** @param CommonObjectKreaImmo $object Target @param string $operation Operation @param array<string,mixed> $fields Input @return void */
	private function apply($object, $operation, array $fields)
	{
		if ($operation === 'create') {
			$result = $object->create($this->user);
		} elseif ($operation === 'update') {
			$result = $object->update($this->user);
		} elseif ($operation === 'delete') {
			$result = $object->delete($this->user);
		} elseif ($operation === 'set_status') {
			if ($this->resource === 'renters') {
				$method = $fields['status'] === 1 && (int) $object->status === 9 ? 'reopen' : array(0 => 'setDraft', 1 => 'validate', 9 => 'cancel')[$fields['status']];
				$result = (int) $object->status === $fields['status'] ? 1 : $object->$method($this->user);
			} else {
				$result = $object->setStatusCommon($this->user, $fields['status']);
			}
		} elseif ($operation === 'validate') {
			$result = $object->validate($this->user);
		} else {
			$result = $object->addPaymentToBank($this->user, 'kreaimmo_immopayment', '(CustomerReceiptPayment)', $fields['fk_account'], '', '');
			if ($result > 0) {
				$this->verifyBankPosting($object);
			}
		}
		if ($result <= 0) {
			throw new RestException(409, 'Native KreaImmo operation failed; no confirmed change');
		}
	}

	/** @param int $id Account ID @param resource|HashContext $hash Hash @param bool $locking Lock account @return void */
	private function assertBankAccount($id, $hash, $locking)
	{
		if (!isModEnabled('banque') || !$this->user->hasRight('banque', 'modifier') || !$this->user->hasRight('banque', 'lire')) {
			throw new RestException(403, 'Native bank read and modify permissions are required');
		}
		$result = $this->query('SELECT rowid, entity, clos, courant, currency_code FROM '.$this->db->prefix().'bank_account WHERE rowid = '.((int) $id).' AND entity = '.$this->entity.($locking ? ' FOR UPDATE' : ''));
		$row = $this->db->fetch_object($result);
		global $conf;
		if (!$row || (int) $row->clos !== 0 || $row->currency_code !== $conf->currency) {
			throw new RestException(403, 'An open current-company bank account in the company currency is required');
		}
		hash_update($hash, $this->canonical((array) $row));
	}

	/** @param KreaImmoPayment $payment Posted payment @return void */
	private function verifyBankPosting($payment)
	{
		if ($payment->fetch($payment->id) <= 0 || empty($payment->fk_bank)) {
			throw new RestException(500, 'Native bank posting readback failed');
		}
		$result = $this->query('SELECT b.amount, b.fk_account, ba.entity FROM '.$this->db->prefix().'bank AS b INNER JOIN '.$this->db->prefix().'bank_account AS ba ON ba.rowid=b.fk_account WHERE b.rowid='.(int) $payment->fk_bank);
		$row = $this->db->fetch_object($result);
		if (!$row || (int) $row->entity !== $this->entity || (int) $row->fk_account !== (int) $payment->fk_account || $this->cents((string) $row->amount) !== $this->cents((string) $payment->amount)) {
			throw new RestException(500, 'Native bank amount or ownership verification failed');
		}
		$result = $this->query('SELECT url_id FROM '.$this->db->prefix().'bank_url WHERE fk_bank='.(int) $payment->fk_bank." AND type='kreaimmo_immopayment'");
		$link = $this->db->fetch_object($result);
		if (!$link || (int) $link->url_id !== (int) $payment->id || $this->db->fetch_object($result)) {
			throw new RestException(500, 'Native bank payment link verification failed');
		}
	}

	/** @param CommonObjectKreaImmo $object Target @param resource|HashContext $hash Hash @param bool $locking Lock dependents @return void */
	private function assertUnreferenced($object, $hash, $locking)
	{
		$dependencies = array(
			'owners' => array('immoproperty:fk_owner', 'immorent:fk_owner', 'immoreceipt:fk_owner', 'immopayment:fk_owner', 'immocost:fk_owner'),
			'properties' => array('immoproperty:fk_property', 'building:fk_property', 'immorent:fk_property', 'immoreceipt:fk_property', 'immopayment:fk_property', 'immocost:fk_property', 'immocompteur:fk_immoproperty', 'immocost_detail:fk_property', 'immospreadzone:fk_property_parent', 'immospreadzone:fk_property_child'),
			'renters' => array('immorent:fk_renter', 'immoreceipt:fk_renter', 'immopayment:fk_renter'),
			'leases' => array('immoreceipt:fk_rent', 'immopayment:fk_rent'), 'receipts' => array('immopayment:fk_receipt'),
			'costs' => array('immocost_detail:fk_immocost'),
		);
		foreach (isset($dependencies[$this->resource]) ? $dependencies[$this->resource] : array() as $dependency) {
			list($table, $field) = explode(':', $dependency);
			$result = $this->query('SELECT rowid FROM '.$this->db->prefix().'kreaimmo_'.$table.' WHERE '.$field.'='.(int) $object->id.$this->db->plimit(1).($locking ? ' FOR UPDATE' : ''));
			if ($this->db->fetch_object($result)) {
				throw new RestException(409, 'Linked records must be resolved before deletion');
			}
			hash_update($hash, $dependency.':empty');
		}
	}

	/** @param CommonObjectKreaImmo $object Target @return void */
	private function assertNoDocuments($object)
	{
		$types = array($object->table_element, $object->table_element.'@kreaimmo');
		if (!empty($object->module)) {
			$types[] = $object->table_element.'@'.$object->module;
		}
		$typeValues = array();
		foreach (array_unique($types) as $type) {
			$typeValues[] = "'".$this->db->escape($type)."'";
		}
		$result = $this->query('SELECT rowid FROM '.$this->db->prefix().'ecm_files WHERE src_object_type IN ('.implode(',', $typeValues).') AND src_object_id='.(int) $object->id.' AND entity='.$this->entity.$this->db->plimit(1));
		if ($this->db->fetch_object($result)) {
			throw new RestException(409, 'Attached documents require an explicit document-preserving workflow');
		}
		global $conf;
		$base = isset($conf->kreaimmo->multidir_output[$this->entity]) ? $conf->kreaimmo->multidir_output[$this->entity] : (isset($conf->kreaimmo->dir_output) ? $conf->kreaimmo->dir_output : '');
		if ($base === '' || empty($object->ref)) {
			return;
		}
		$folders = array('owners' => 'owner', 'buildings' => 'building', 'properties' => 'property', 'renters' => 'renter', 'leases' => 'rent', 'receipts' => 'receipt', 'payments' => 'payment', 'costs' => 'cost', 'meters' => 'compteur');
		$reference = dol_sanitizeFileName($object->ref);
		$directories = array($base.'/'.$folders[$this->resource].'/'.$reference, $base.'/'.str_replace('kreaimmo_', '', $object->table_element).'/'.$reference, $base.'/'.$reference);
		$native = getMultidirOutput($object);
		if (is_string($native) && strpos($native, rtrim($base, '/').'/') === 0) {
			$directories[] = $native.'/'.$reference;
		}
		foreach (array_unique($directories) as $directory) {
			if (is_dir($directory) && dol_dir_list($directory, 'files', 1)) {
				throw new RestException(409, 'Stored documents require an explicit document-preserving workflow');
			}
		}
	}

	/** @return string Bounded advisory lock name */
	private function lockCompany()
	{
		if (!in_array($this->db->type, array('mysqli', 'mysql'), true)) {
			throw new RestException(501, 'Management requires the supported MySQL/MariaDB advisory-lock backend');
		}
		$name = 'kreaimmo_'.substr(hash('sha256', $this->db->database_name.':'.$this->entity), 0, 48);
		$result = $this->query("SELECT GET_LOCK('".$this->db->escape($name)."', 5) AS acquired");
		$row = $this->db->fetch_object($result);
		if (!$row || (int) $row->acquired !== 1) {
			throw new RestException(409, 'Management is busy for this company; retry with a fresh preview');
		}
		return $name;
	}

	/** @param string $sqlQuery Trusted SQL @return resource|object Native result */
	private function query($sqlQuery)
	{
		$result = $this->db->query($sqlQuery);
		if (!$result) {
			throw new RestException(500, 'Unable to verify native management state');
		}
		return $result;
	}

	/** @param resource|HashContext $hash Hash @param string $sqlQuery Scoped SELECT @return void */
	private function hashQuery($hash, $sqlQuery)
	{
		// Native MySQL unbuffered results bound PHP memory while hashing a collection.
		$result = $this->db->query($sqlQuery, 0, 'auto', 1);
		if (!$result) {
			throw new RestException(500, 'Unable to snapshot management state');
		}
		while ($row = $this->db->fetch_object($result)) {
			hash_update($hash, $this->canonical((array) $row)."\n");
		}
		$this->db->free($result);
	}

	/** @param array<string,mixed> $record Readback @param array<string,mixed> $fields Input @param string $operation Operation @return void */
	private function verifyFields(array $record, array $fields, $operation)
	{
		$object = $this->definition['object'];
		foreach ($fields as $field => $value) {
			if (!array_key_exists($field, $record)) {
				throw new RestException(500, 'Native management readback lacks the requested field');
			}
			$actual = $record[$field];
			if (in_array($field, array('amount', 'amount_ht', 'amount_vat', 'rentamount', 'chargesamount', 'deposit', 'vat_amount'), true)) {
				$expected = $this->storedValue($object, $field, $value);
				$equal = $expected === null ? $actual === null : $this->cents((string) $actual) === $this->cents($value);
			} elseif (strpos($field, 'date_') === 0) {
				$equal = is_string($actual) && substr($actual, 0, 10) === $value;
			} elseif ($field === 'qty') {
				$actualText = strpos((string) $actual, '.') === false ? (string) $actual : rtrim(rtrim((string) $actual, '0'), '.');
				$valueText = strpos($value, '.') === false ? $value : rtrim(rtrim($value, '0'), '.');
				$equal = $actualText === $valueText;
			} else {
				$equal = $actual === $this->storedValue($object, $field, $value);
			}
			if (!$equal) {
				throw new RestException(500, 'Native management readback differs from the confirmed request');
			}
		}
		if ($operation === 'validate' && $record['status'] !== 1) {
			throw new RestException(500, 'Native receipt validation was not confirmed');
		}
	}

	/** @param mixed $value JSON-safe value @return string Canonically ordered JSON */
	private function canonical($value)
	{
		if (is_object($value)) {
			$value = (array) $value;
		}
		if (is_array($value)) {
			ksort($value);
			foreach ($value as $key => $item) {
				$value[$key] = json_decode($this->canonical($item), true);
			}
		}
		return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
	}
}
