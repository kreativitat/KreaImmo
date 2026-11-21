<?php
/* Copyright (C) 2015   Jean-François Ferry     <jfefe@aternatik.fr>
 * Copyright (C) 2018 Philippe GRAND <philippe.grand@atoo-net.com>
 * Copyright (C) 2026 Kreativität Works <mail@kreativitat.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

use Luracast\Restler\RestException;

dol_include_once('/kreaimmo/class/kreaimmorenter.class.php');



/**
 * \file    kreaimmo/class/api_kreaimmo.class.php
 * \ingroup kreaimmo
 * \brief   Company-scoped real-estate evidence and native management API.
 */

/**
 * KreaImmo portfolio and management API, including legacy renter compatibility.
 *
 * @smart-auto-routing false
 * @access protected
 * @class  DolibarrApiAccess {@requires user,external}
 */
class KreaImmoApi extends DolibarrApi
{
	/** @var string[] Required create fields */
	public static $FIELDS = array('lastname');

	/** @var KreaImmoRenter */
	public $immorenter;

	public function __construct()
	{
		global $db;
		$this->db = $db;
		$this->immorenter = new KreaImmoRenter($db);
	}

	/**
	 * List company-owned real-estate evidence without exposing object internals.
	 *
	 * @param string $resource Resource name documented in doc/API.md
	 * @param int $limit Maximum rows, from 1 to 200
	 * @param int $page Zero-based page, from 0 to 1000
	 * @param string $ref Optional exact reference; omitted defaults to null
	 * @param int $property_id Optional direct property relationship
	 * @param int $renter_id Optional direct renter relationship
	 * @param int $lease_id Optional direct lease relationship
	 * @param int $owner_id Optional direct owner relationship
	 * @return array Versioned evidence envelope
	 * @phpstan-return array<string,mixed>
	 * @url GET records/{resource}
	 */
	public function getRecords($resource, $limit = 100, $page = 0, $ref = null, $property_id = null, $renter_id = null, $lease_id = null, $owner_id = null)
	{
		$definition = $this->readDefinition($resource);
		$limit = $this->readInteger($limit, 'limit', 1, 200);
		$page = $this->readInteger($page, 'page', 0, 1000);
		$filters = array();
		$sqlWhereClause = '';
		foreach (array('ref' => $ref, 'property_id' => $property_id, 'renter_id' => $renter_id, 'lease_id' => $lease_id, 'owner_id' => $owner_id) as $name => $value) {
			if ($value === null) {
				continue;
			}
			if (!isset($definition['filters'][$name])) {
				throw new RestException(400, 'Unsupported filter for '.$resource.': '.$name);
			}
			if ($name === 'ref') {
				if (!is_string($value) || trim($value) === '' || dol_strlen($value) > 128 || preg_match('/[\x00-\x1f\x7f]/', $value)) {
					throw new RestException(400, 'ref must be an exact nonempty reference of at most 128 characters');
				}
				$sqlWhereClause .= " AND BINARY t.ref = BINARY '".$this->db->escape($value)."'";
			} else {
				$value = $this->readInteger($value, $name, 1, 2147483647);
				$sqlWhereClause .= ' AND t.'.$definition['filters'][$name].' = '.$value;
			}
			$filters[$name] = $value;
		}
		$sqlQuery = $this->readSelect($definition).$sqlWhereClause;
		$sqlQuery .= $this->db->order('t.rowid', 'ASC').$this->db->plimit($limit + 1, $page * $limit);
		$items = $this->readRows($sqlQuery, $definition);
		$hasMore = count($items) > $limit;
		if ($hasMore) {
			array_pop($items);
		}
		global $conf;
		return array('schema' => 'kreaimmo_records_v1', 'entity_id' => (int) $conf->entity, 'resource' => $resource,
			'items' => $items, 'limit' => $limit, 'page' => $page, 'has_more' => $hasMore, 'filters' => (object) $filters);
	}

	/**
	 * Fetch one company-owned record using the same projection as the list.
	 *
	 * @param string $resource Resource name documented in doc/API.md
	 * @param int $id Positive record ID
	 * @return array Versioned evidence envelope
	 * @phpstan-return array<string,mixed>
	 * @url GET records/{resource}/{id}
	 */
	public function getRecord($resource, $id)
	{
		$definition = $this->readDefinition($resource);
		$id = $this->readInteger($id, 'id', 1, 2147483647);
		$records = $this->readRows($this->readSelect($definition).' AND t.rowid = '.$id, $definition);
		if (!$records) {
			throw new RestException(404, 'KreaImmo record not found');
		}
		global $conf;
		return array('schema' => 'kreaimmo_record_v1', 'entity_id' => (int) $conf->entity,
			'resource' => $resource, 'record' => $records[0]);
	}

	/**
	 * Preview or apply an explicitly confirmed native management operation.
	 *
	 * @param string $resource Public real-estate resource
	 * @param array $request_data Operation, fields and preview safeguards
	 * @return array Versioned management result
	 * @phpstan-return array<string,mixed>
	 * @url POST manage/{resource}
	 */
	public function manageRecord($resource, $request_data = null)
	{
		$definition = $this->readDefinition($resource);
		require_once __DIR__.'/kreaimmoapimanagement.class.php';
		$service = new KreaImmoApiManagement($this->db, $this, $resource, $definition);
		return $service->manage($request_data);
	}

	/**
	 * Resolve a fixed projection only after authenticating an internal reader.
	 *
	 * @param string $resource Public resource name
	 * @return array<string,mixed> Trusted definition and native business object
	 */
	private function readDefinition($resource)
	{
		global $conf;
		$user = DolibarrApiAccess::$user;
		if (!$user || empty($user->id)) {
			throw new RestException(401, 'Authenticated user required');
		}
		if ((int) $conf->entity < 1 || !isModEnabled('kreaimmo') || !empty($user->societe_id) || !empty($user->socid)) {
			throw new RestException(403, 'Active KreaImmo company and internal user required');
		}
		$definitions = array(
			'owners' => array('model' => 'owner', 'class' => 'KreaImmoOwner', 'permission' => 'owner',
				'fields' => 'ref,status,fk_soc,fk_owner_type,societe,firstname,lastname,address,zip,town,country_id,email,phone,phone_mobile', 'filters' => array('ref' => 'ref')),
			'buildings' => array('model' => 'building', 'class' => 'KreaImmoBuilding', 'permission' => 'property',
				'fields' => 'label,fk_property', 'filters' => array('property_id' => 'fk_property')),
			'properties' => array('model' => 'property', 'class' => 'KreaImmoProperty', 'permission' => 'property',
				'fields' => 'ref,status,label,property_type_id,fk_property,fk_owner,fk_soc,juridique_id,datebuilt,target,area,numroom,numflat,numdoor,numfloor,staircase,building,address,zip,town,country_id',
				'filters' => array('ref' => 'ref', 'property_id' => 'fk_property', 'owner_id' => 'fk_owner')),
			'renters' => array('model' => 'renter', 'class' => 'KreaImmoRenter', 'permission' => 'renter',
				'fields' => 'ref,status,fk_soc,societe,firstname,lastname,email,phone,phone_mobile,town,country_id', 'filters' => array('ref' => 'ref')),
			'leases' => array('model' => 'rent', 'class' => 'KreaImmoRent', 'permission' => 'rent',
				'fields' => 'ref,status,fk_property,fk_owner,fk_renter,fk_account,fk_soc,location_type_id,vat,rentamount,chargesamount,totalamount,deposit,encours,periode,preavis,date_start,date_end,date_next_rent,date_last_regul,date_last_regul_charge',
				'filters' => array('ref' => 'ref', 'property_id' => 'fk_property', 'renter_id' => 'fk_renter', 'owner_id' => 'fk_owner')),
			'receipts' => array('model' => 'receipt', 'class' => 'KreaImmoReceipt', 'permission' => 'receipt',
				'fields' => 'ref,status,label,fk_rent,fk_property,fk_renter,fk_owner,fk_soc,date_echeance,date_start,date_end,rentamount,chargesamount,total_amount,partial_payment,balance,paye,vat_amount,vat_tx',
				'filters' => array('ref' => 'ref', 'property_id' => 'fk_property', 'renter_id' => 'fk_renter', 'lease_id' => 'fk_rent', 'owner_id' => 'fk_owner')),
			'payments' => array('model' => 'payment', 'class' => 'KreaImmoPayment', 'permission' => 'payment',
				'fields' => 'ref,status,fk_rent,fk_receipt,fk_owner,fk_soc,fk_property,fk_renter,date_payment,amount,fk_mode_reglement,fk_payment,fk_bank,fk_account,num_payment',
				'filters' => array('ref' => 'ref', 'property_id' => 'fk_property', 'renter_id' => 'fk_renter', 'lease_id' => 'fk_rent', 'owner_id' => 'fk_owner')),
			'costs' => array('model' => 'cost', 'class' => 'KreaImmoCost', 'permission' => 'cost',
				'fields' => 'ref,status,label,fk_property,fk_owner,fk_soc,fk_cost_type,amount,amount_ht,amount_vat,date_start,date_end,dispatch',
				'filters' => array('ref' => 'ref', 'property_id' => 'fk_property', 'owner_id' => 'fk_owner')),
			'meters' => array('model' => 'compteur', 'class' => 'KreaImmoCompteur', 'permission' => 'immocompteur',
				'fields' => 'ref,label,compteur_type_id,date_relever,qty,fk_immoproperty',
				'filters' => array('ref' => 'ref', 'property_id' => 'fk_immoproperty')),
		);
		if (!is_string($resource) || !isset($definitions[$resource])) {
			throw new RestException(400, 'Unknown KreaImmo resource');
		}
		$definition = $definitions[$resource];
		if (!$user->hasRight('kreaimmo', 'read') && !$user->hasRight('kreaimmo', $definition['permission'], 'read')) {
			throw new RestException(403, 'KreaImmo resource read permission required');
		}
		dol_include_once('/kreaimmo/class/kreaimmo'.$definition['model'].'.class.php');
		$class = $definition['class'];
		$definition['object'] = new $class($this->db);
		$definition['fields'] = explode(',', $definition['fields']);
		return $definition;
	}

	/**
	 * Validate integer inputs before any resource query; do not silently clamp.
	 *
	 * @param mixed $value Request value
	 * @param string $name Public parameter name
	 * @param int $minimum Smallest accepted value
	 * @param int $maximum Largest accepted value
	 * @return int Validated integer
	 */
	private function readInteger($value, $name, $minimum, $maximum)
	{
		if ((!is_int($value) && !is_string($value)) || !preg_match('/^(0|[1-9][0-9]*)$/D', (string) $value) || (int) $value < $minimum || (int) $value > $maximum) {
			throw new RestException(400, $name.' must be an integer between '.$minimum.' and '.$maximum);
		}
		return (int) $value;
	}

	/**
	 * Build a query from fixed fields and the model's native company predicate.
	 *
	 * @param array<string,mixed> $definition Trusted resource definition
	 * @return string Company-scoped SELECT
	 */
	private function readSelect(array $definition)
	{
		$object = $definition['object'];
		$sqlQuery = 'SELECT t.rowid AS id';
		$sqlQuery .= isset($object->fields['entity']) ? ', t.entity AS entity' : ', p.entity AS entity';
		foreach ($definition['fields'] as $field) {
			$sqlQuery .= ', t.'.$field;
		}
		$sqlQuery .= ' FROM '.$this->db->prefix().$object->table_element.' AS t';
		if (!isset($object->fields['entity'])) {
			$parentField = $definition['model'] === 'building' ? 'fk_property' : 'fk_immoproperty';
			$sqlQuery .= ' INNER JOIN '.$this->db->prefix().'kreaimmo_immoproperty AS p ON p.rowid = t.'.$parentField;
		}
		$sqlQuery .= ' WHERE '.$object->getKreaImmoScopeSQL('t');
		if ($definition['model'] === 'renter') {
			$sqlQuery .= $this->visibilitySQL('t');
		}
		return $sqlQuery;
	}

	/**
	 * Read only the public projection; retain exact monetary database strings.
	 *
	 * @param string $sqlQuery Trusted scoped SELECT
	 * @param array<string,mixed> $definition Trusted resource definition
	 * @return array<int,array<string,mixed>> Public records
	 */
	private function readRows($sqlQuery, array $definition)
	{
		global $conf;
		$result = $this->db->query($sqlQuery);
		if (!$result) {
			throw new RestException(500, 'Unable to read KreaImmo records');
		}
		$records = array();
		$integerFields = array('status', 'property_type_id', 'juridique_id', 'datebuilt', 'target', 'country_id', 'location_type_id', 'vat', 'preavis', 'numroom', 'paye', 'vat_tx', 'dispatch', 'compteur_type_id');
		while ($row = $this->db->fetch_object($result)) {
			if ((int) $row->entity !== (int) $conf->entity) {
				throw new RestException(500, 'Invalid KreaImmo company ownership');
			}
			$record = array('id' => (int) $row->id, 'entity' => (int) $row->entity);
			foreach ($definition['fields'] as $field) {
				$value = $row->$field;
				if ($value !== null) {
					$value = strpos($field, 'fk_') === 0 || in_array($field, $integerFields, true) ? (int) $value : (string) $value;
				}
				$record[$field] = $value;
			}
			$records[] = $record;
		}
		$this->db->free($result);
		return $records;
	}

	/**
	 * Fetch a renter belonging to the active company and permitted third party.
	 *
	 * @param int $id Renter ID
	 * @return object
	 * @url GET immorenters/{id}
	 */
	public function get($id)
	{
		$this->requirePermission('read');
		$this->loadRenter($id);
		return $this->_cleanObjectDatas($this->immorenter);
	}

	/**
	 * List renters with native filters and bounded pagination.
	 *
	 * @param string $sortfield Sort field
	 * @param string $sortorder Sort order
	 * @param int $limit Maximum rows, from 1 to 1000
	 * @param int $page Zero-based page
	 * @param string $sqlfilters Native universal search criteria
	 * @return array
	 * @url GET /immorenters/
	 */
	public function index($sortfield = 't.rowid', $sortorder = 'ASC', $limit = 100, $page = 0, $sqlfilters = '')
	{
		$this->requirePermission('read');
		$field = preg_replace('/^t\./', '', $sortfield);
		if (!isset($this->immorenter->fields[$field]) || !in_array(strtoupper($sortorder), array('ASC', 'DESC'), true)) {
			throw new RestException(400, 'Invalid sort field or order');
		}
		$limit = max(1, min(1000, (int) $limit));
		$page = max(0, (int) $page);
		$sql = 'SELECT '.$this->immorenter->getFieldList('t').' FROM '.$this->db->prefix().'kreaimmo_immorenter AS t';
		$sql .= ' WHERE '.$this->immorenter->getKreaImmoScopeSQL('t').$this->visibilitySQL('t');
		if ($sqlfilters !== '') {
			$error = '';
			$filter = forgeSQLFromUniversalSearchCriteria($sqlfilters, $error);
			if ($error) {
				throw new RestException(400, 'Invalid search criteria');
			}
			$sql .= $filter;
		}
		$sql .= $this->db->order('t.'.$field, $sortorder).$this->db->plimit($limit, $page * $limit);
		$resql = $this->db->query($sql);
		if (!$resql) {
			throw new RestException(500, 'Unable to list renters');
		}
		$records = array();
		while ($row = $this->db->fetch_object($resql)) {
			$renter = new KreaImmoRenter($this->db);
			$renter->setVarsFromFetchObj($row);
			$records[] = $this->_cleanObjectDatas($renter);
		}
		return $records;
	}

	/**
	 * Create a renter.
	 *
	 * @param array $request_data Renter fields
	 * @return int
	 * @url POST immorenters/
	 */
	public function post($request_data = null)
	{
		$this->requirePermission('write');
		$this->immorenter = new KreaImmoRenter($this->db);
		$data = $this->_validate($request_data);
		$this->assignFields($data);
		if (!isset($this->immorenter->civility_id)) {
			$this->immorenter->civility_id = 0;
		}
		if ($this->immorenter->create(DolibarrApiAccess::$user) <= 0) {
			$this->writeFailed();
		}
		return $this->immorenter->id;
	}

	/**
	 * Update a renter.
	 *
	 * @param int $id Renter ID
	 * @param array $request_data Editable renter fields
	 * @return object
	 * @url PUT immorenters/{id}
	 */
	public function put($id, $request_data = null)
	{
		$this->requirePermission('write');
		$this->loadRenter($id);
		$this->assignFields($request_data);
		if ($this->immorenter->update(DolibarrApiAccess::$user) <= 0) {
			$this->writeFailed();
		}
		$this->loadRenter($id);
		return $this->_cleanObjectDatas($this->immorenter);
	}

	/**
	 * Delete a renter.
	 *
	 * @param int $id Renter ID
	 * @return array
	 * @url DELETE immorenter/{id}
	 */
	public function delete($id)
	{
		$this->requirePermission('delete');
		$this->loadRenter($id);
		if ($this->immorenter->delete(DolibarrApiAccess::$user) <= 0) {
			$this->writeFailed();
		}
		return array('success' => array('code' => 200, 'message' => 'KreaImmoRenter deleted'));
	}

	/** @param string $permission Permission name */
	private function requirePermission($permission)
	{
		if (!DolibarrApiAccess::$user->hasRight('kreaimmo', $permission) && !DolibarrApiAccess::$user->hasRight('kreaimmo', 'renter', $permission)) {
			throw new RestException(401, 'Renter permission required');
		}
	}

	/** @return string SQL enforcing native third-party visibility */
	private function visibilitySQL($alias)
	{
		$user = DolibarrApiAccess::$user;
		if (!empty($user->societe_id)) {
			return ' AND '.$alias.'.fk_soc = '.((int) $user->societe_id);
		}
		if (!$user->hasRight('societe', 'client', 'voir')) {
			return ' AND EXISTS (SELECT 1 FROM '.$this->db->prefix().'societe_commerciaux AS sc WHERE sc.fk_soc = '.$alias.'.fk_soc AND sc.fk_user = '.((int) $user->id).')';
		}
		return '';
	}

	/** @param int $id Renter ID */
	private function loadRenter($id)
	{
		$this->immorenter = new KreaImmoRenter($this->db);
		if ($this->immorenter->fetch((int) $id) <= 0) {
			throw new RestException(404, 'KreaImmoRenter not found');
		}
		$sql = 'SELECT t.rowid FROM '.$this->db->prefix().'kreaimmo_immorenter AS t WHERE t.rowid = '.((int) $id).$this->visibilitySQL('t');
		$resql = $this->db->query($sql);
		if (!$resql) {
			throw new RestException(500, 'Unable to check renter access');
		}
		if (!$this->db->fetch_object($resql)) {
			throw new RestException(404, 'KreaImmoRenter not found');
		}
	}

	/** @param array $data Editable values */
	private function assignFields($data)
	{
		if (!is_array($data)) {
			throw new RestException(400, 'Expected a renter object');
		}
		$protected = array('rowid', 'entity', 'date_creation', 'tms', 'fk_user_creat', 'fk_user_modif', 'import_key', 'status');
		foreach ($data as $field => $value) {
			if (!isset($this->immorenter->fields[$field]) || in_array($field, $protected, true)) {
				throw new RestException(403, 'Field is not editable: '.$field);
			}
			if (!is_scalar($value) && $value !== null) {
				throw new RestException(400, 'Invalid field value: '.$field);
			}
			$this->immorenter->$field = $value;
		}
		$user = DolibarrApiAccess::$user;
		if (!empty($user->societe_id) && (int) $this->immorenter->fk_soc !== (int) $user->societe_id) {
			throw new RestException(403, 'Third-party access denied');
		}
	}

	/** @return void */
	private function writeFailed()
	{
		if ($this->immorenter->error === 'KreaImmoAccessDenied') {
			throw new RestException(403, 'Company access denied');
		}
		throw new RestException(500, 'Unable to save renter');
	}

	/**
	 * @param array $data Create values
	 * @return array
	 */
	public function _validate($data)
	{
		if (!is_array($data)) {
			throw new RestException(400, 'Expected a renter object');
		}
		foreach (self::$FIELDS as $field) {
			if (!isset($data[$field]) || !is_scalar($data[$field]) || trim((string) $data[$field]) === '') {
				throw new RestException(400, $field.' field missing');
			}
		}
		return $data;
	}
}
