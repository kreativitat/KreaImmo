<?php
/* Copyright (C) 2026 Kreativität Works <mail@kreativitat.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/** Standalone integration checks against an explicitly selected disposable Dolibarr database. */
if (PHP_SAPI !== 'cli' || getenv('KREAIMMO_TEST_ALLOW_WRITES') !== '1') {
	fwrite(STDERR, "Refusing execution: CLI and KREAIMMO_TEST_ALLOW_WRITES=1 are required.\n");
	exit(2);
}
$bootstrap = getenv('KREAIMMO_TEST_BOOTSTRAP') ?: __DIR__.'/bootstrap.php';
if (!$bootstrap || !is_file($bootstrap)) {
	fwrite(STDERR, "KREAIMMO_TEST_BOOTSTRAP must identify a disposable Dolibarr bootstrap.\n");
	exit(2);
}
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
ini_set('display_errors', 'stderr');
set_error_handler(function ($severity, $message, $file, $line) {
	if (!(error_reporting() & $severity)) {
		return false;
	}
	throw new ErrorException($message, 0, $severity, $file, $line);
});
require $bootstrap;
if (!isset($db) || !($db instanceof DoliDB) || $db->database_name !== 'kreaimmo_clean') {
	fwrite(STDERR, "Refusing execution: the selected database must be kreaimmo_clean.\n");
	exit(2);
}
if (!defined('DOL_DATA_ROOT') || strpos(DOL_DATA_ROOT, '/tmp/kreaimmo-') !== 0) {
	fwrite(STDERR, "Refusing execution: a dedicated /tmp/kreaimmo-* document root is required.\n");
	exit(2);
}
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
foreach (array('owner', 'property', 'renter', 'rent', 'receipt', 'cost', 'compteur', 'payment') as $name) {
	require_once dirname(__DIR__, 2).'/class/kreaimmo'.$name.'.class.php';
}
require_once DOL_DOCUMENT_ROOT.'/api/class/api_access.class.php';
require_once DOL_DOCUMENT_ROOT.'/api/class/api.class.php';
require_once dirname(__DIR__, 2).'/class/api_kreaimmo.class.php';
require_once dirname(__DIR__, 2).'/class/actions_kreaimmo.class.php';
require_once dirname(__DIR__, 2).'/class/kreaimmoproperty_type.class.php';
require_once dirname(__DIR__, 2).'/class/kreaimmocompteur_type.class.php';
foreach (array('building', 'cost_detail', 'spreadzone', 'compteur_cost', 'owner_type', 'cost_type') as $name) {
	require_once dirname(__DIR__, 2).'/class/kreaimmo'.$name.'.class.php';
}

$results = array();
$fixtures = array();
$run = 'QA'.substr(bin2hex(random_bytes(6)), 0, 12);
$originalEntity = (int) $conf->entity;
$originalUser = $user;
$conf->kreaimmo->enabled = 1;
$conf->global->MAIN_MODULE_KREAIMMO = 1;
$conf->modules['kreaimmo'] = 'kreaimmo';
$conf->modules['societe'] = 'societe';
$now = dol_mktime(12, 0, 0, 11, 20, 2026);

function qaAssert($condition, $message)
{
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

function qaCheck($name, callable $check)
{
	global $results;
	try {
		$check();
		$results[] = array('name' => $name, 'status' => 'PASS');
	} catch (Throwable $error) {
		$results[] = array('name' => $name, 'status' => 'FAIL', 'error' => $error->getMessage(), 'location' => basename($error->getFile()).':'.$error->getLine());
	}
}

function qaQuery($sql)
{
	global $db;
	$result = $db->query($sql);
	qaAssert((bool) $result, 'SQL failure: '.$db->lasterror());
	return $result;
}

function qaRow($object)
{
	global $db;
	$result = qaQuery('SELECT * FROM '.MAIN_DB_PREFIX.$object->table_element.' WHERE rowid='.(int) $object->id);
	return $db->fetch_array($result);
}

function qaUser($read = 1, $write = 1, $delete = 1)
{
	global $db, $conf;
	$actor = new User($db);
	$actor->id = 1;
	$actor->entity = (int) $conf->entity;
	$actor->login = 'synthetic_validation';
	$actor->admin = 0;
	$actor->rights = new stdClass();
	$actor->rights->kreaimmo = new stdClass();
	foreach (array('owner', 'property', 'renter', 'rent', 'receipt', 'cost', 'compteur', 'payment') as $permission) {
		$actor->rights->kreaimmo->$permission = (object) array('read' => $read, 'write' => $write, 'delete' => $delete);
	}
	$actor->rights->societe = (object) array('client' => (object) array('voir' => 1));
	return $actor;
}

function qaNew($class, array $values)
{
	global $db, $conf, $now, $run;
	$object = new $class($db);
	$defaults = array('ref' => $run.'-'.substr($class, 8).'-'.bin2hex(random_bytes(3)), 'entity' => (int) $conf->entity, 'status' => 0, 'date_creation' => $now, 'import_key' => $run);
	foreach (array_merge($defaults, $values) as $key => $value) {
		if (isset($object->fields[$key])) {
			$object->$key = $value;
		}
	}
	return $object;
}

function qaCreate($object)
{
	global $user;
	$result = $object->create($user, true);
	qaAssert($result > 0 && $object->id > 0, get_class($object).' create failed: '.$object->error.' '.implode('; ', $object->errors));
	return $object;
}

function qaFetch($class, $id)
{
	global $db;
	$object = new $class($db);
	qaAssert($object->fetch($id) > 0, $class.' fetch failed: '.$object->error);
	return $object;
}

function qaDecimal($expected, $actual, $description)
{
	qaAssert(number_format((float) $expected, 2, '.', '') === number_format((float) $actual, 2, '.', ''), $description.': expected '.$expected.', received '.var_export($actual, true));
}

function qaCostTypeSnapshot()
{
	global $db;
	$result = qaQuery('SELECT * FROM '.MAIN_DB_PREFIX.'kreaimmo_immocost_type ORDER BY rowid');
	$rows = array();
	while ($row = $db->fetch_array($result)) {
		$rows[] = $row;
	}
	return $rows;
}

function qaRestError($status, callable $action)
{
	try {
		$action();
	} catch (Luracast\Restler\RestException $error) {
		qaAssert((int) $error->getCode() === $status, 'Expected API '.$status.', received '.$error->getCode().': '.$error->getMessage());
		return;
	}
	throw new RuntimeException('API action unexpectedly succeeded; expected '.$status);
}

function qaChain($entity)
{
	global $conf, $user, $now, $fixtures;
	$conf->entity = $entity;
	$user = qaUser();
	$chain = array();
	$propertyType = qaCreate(qaNew('KreaImmoProperty_Type', array('code' => 'QA'.$entity, 'label' => 'Synthetic property type', 'active' => 1)));
	$meterType = qaCreate(qaNew('KreaImmoCompteur_Type', array('label' => 'Synthetic meter type', 'active' => 1)));
	$chain['owner'] = qaCreate(qaNew('KreaImmoOwner', array('civility_id' => 1, 'firstname' => 'Synthetic', 'lastname' => 'Owner', 'email' => 'owner@example.invalid', 'birth' => $now)));
	$chain['property'] = qaCreate(qaNew('KreaImmoProperty', array('fk_owner' => $chain['owner']->id, 'property_type_id' => $propertyType->id, 'label' => 'Synthetic property', 'address' => 'Test address', 'zip' => '0000-000', 'town' => 'Test', 'country_id' => 1)));
	$chain['renter'] = qaCreate(qaNew('KreaImmoRenter', array('civility_id' => 1, 'firstname' => 'Synthetic', 'lastname' => 'Renter', 'email' => 'renter@example.invalid', 'birth' => $now)));
	$links = array('fk_owner' => $chain['owner']->id, 'fk_property' => $chain['property']->id, 'fk_renter' => $chain['renter']->id);
	$chain['rent'] = qaCreate(qaNew('KreaImmoRent', array_merge($links, array('rentamount' => '1000.25', 'chargesamount' => '50.10', 'totalamount' => '1050.35', 'deposit' => '100.15', 'encours' => '0.05', 'periode' => 'monthly', 'preavis' => 1, 'date_start' => $now, 'date_end' => $now + 86400 * 30))));
	$links['fk_rent'] = $chain['rent']->id;
	$chain['receipt'] = qaCreate(qaNew('KreaImmoReceipt', array_merge($links, array('label' => 'Synthetic receipt', 'rentamount' => '1000.25', 'chargesamount' => '50.10', 'total_amount' => '1050.35', 'balance' => '1050.35', 'partial_payment' => '0.00', 'vat_amount' => '0.00', 'vat_tx' => 0, 'paye' => 0, 'date_start' => $now, 'date_end' => $now + 86400 * 30, 'date_echeance' => $now + 86400 * 7))));
	$chain['cost'] = qaCreate(qaNew('KreaImmoCost', array_merge($links, array('label' => 'Synthetic cost', 'amount' => '123.45', 'amount_ht' => '100.00', 'amount_vat' => '23.45', 'date_start' => $now, 'date_end' => $now + 86400))));
	$chain['compteur'] = qaCreate(qaNew('KreaImmoCompteur', array('label' => 'Synthetic meter', 'compteur_type_id' => $meterType->id, 'fk_immoproperty' => $chain['property']->id, 'date_relever' => $now, 'qty' => '12.5')));
	$chain['payment'] = qaCreate(qaNew('KreaImmoPayment', array_merge($links, array('fk_receipt' => $chain['receipt']->id, 'fk_payment' => 1, 'amount' => '25.15', 'date_payment' => $now))));
	$fixtures[$entity] = $chain;
	return $chain;
}

function qaAuxChain($entity)
{
	global $conf, $user, $fixtures;
	$conf->entity = $entity;
	$user = qaUser();
	$parents = $fixtures[$entity];
	$aux = array();
	$aux['owner_type'] = qaCreate(qaNew('KreaImmoOwner_Type', array('label' => 'Synthetic owner type')));
	$aux['cost_type'] = qaCreate(qaNew('KreaImmoCost_Type', array('label' => 'Synthetic cost type', 'famille' => 'Synthetic', 'active' => 1)));
	$aux['building'] = qaCreate(qaNew('KreaImmoBuilding', array('label' => 'Synthetic building', 'fk_property' => $parents['property']->id)));
	$aux['cost_detail'] = qaCreate(qaNew('KreaImmoCost_Detail', array('label' => 'Synthetic cost detail', 'amount' => '12.35', 'fk_immocost' => $parents['cost']->id, 'fk_property' => $parents['property']->id, 'fk_cost_type' => $aux['cost_type']->id)));
	$aux['spreadzone'] = qaCreate(qaNew('KreaImmoSpreadZone', array('fk_property_parent' => $parents['property']->id, 'fk_property_child' => $parents['property']->id, 'percent_application' => '50.25')));
	$aux['compteur_cost'] = qaCreate(qaNew('KreaImmoCompteur_Cost', array('fk_immocompteur_type' => $parents['compteur']->compteur_type_id, 'cost_year' => 2026, 'amount' => '12.35')));
	return $aux;
}

qaCheck('descriptor uses registered module and permission IDs', function () {
	global $db;
	require_once dirname(__DIR__, 2).'/core/modules/modKreaImmo.class.php';
	$module = new modKreaImmo($db);
	qaAssert($module->numero === 156571, 'Unexpected module ID');
	$ids = array();
	foreach ($module->rights as $permission) {
		qaAssert(!in_array($permission[0], $ids, true), 'Duplicate permission ID');
		$ids[] = $permission[0];
	}
	qaAssert(count($ids) > 0, 'No permissions declared');
});
if (isset($kreaimmoIntegrationBootstrap)) {
	qaCheck('native module installation replay preserves existing records', function () use ($kreaimmoIntegrationBootstrap) {
		qaAssert($kreaimmoIntegrationBootstrap['module_install'] > 0 && $kreaimmoIntegrationBootstrap['module_replay'] > 0, 'Native installation failed');
		qaAssert($kreaimmoIntegrationBootstrap['module_tables'] >= 18 && $kreaimmoIntegrationBootstrap['preserved_existing_record'], 'Schema replay failed to preserve synthetic record');
	});
}
qaCheck('installation seeds all 18 cost types only in the active company', function () {
	global $db, $conf, $kreaimmoIntegrationBootstrap;
	$refs = array();
	for ($number = 1; $number <= 18; $number++) {
		$refs[] = "'COSTTYPE".sprintf('%03d', $number)."'";
	}
	$result = qaQuery('SELECT ref, COUNT(*) AS total FROM '.MAIN_DB_PREFIX.'kreaimmo_immocost_type WHERE entity='.(int) $conf->entity.' AND ref IN ('.implode(',', $refs).') GROUP BY ref');
	qaAssert($db->num_rows($result) === 18, 'The active company does not have all 18 default cost types');
	while ($row = $db->fetch_object($result)) {
		qaAssert((int) $row->total === 1, 'Duplicate active-company seed: '.$row->ref);
	}
	if (!empty($kreaimmoIntegrationBootstrap['clean_install'])) {
		$row = $db->fetch_object(qaQuery('SELECT COUNT(*) AS total FROM '.MAIN_DB_PREFIX.'kreaimmo_immocost_type WHERE entity=1'));
		qaAssert((int) $row->total === 0, 'Clean installation leaked cost types into entity 1');
	}
});
qaCheck('installation registers hooks in the active company without global hooks', function () {
	global $db, $conf;
	$result = qaQuery("SELECT entity, value FROM ".MAIN_DB_PREFIX."const WHERE name='MAIN_MODULE_KREAIMMO_HOOKS'");
	$current = 0;
	while ($row = $db->fetch_object($result)) {
		qaAssert((int) $row->entity !== 0, 'Module hooks were registered globally');
		if ((int) $row->entity === (int) $conf->entity) {
			$current++;
			$contexts = json_decode($row->value, true);
			qaAssert(is_array($contexts) && in_array('elementproperties', $contexts, true) && in_array('all', $contexts, true), 'Current-company hook contexts were not installed');
		}
	}
	qaAssert($current === 1, 'Expected exactly one current-company hook registration');
});
qaCheck('element hook resolves module aliases and preserves native object mappings', function () {
	global $db, $hookmanager;
	$hook = new ActionsKreaImmo($db);
	$object = null;
	$action = '';
	foreach (array('kreaimmo_immoproperty' => 'KreaImmoProperty', 'KreaImmoReceipt@kreaimmo' => 'KreaImmoReceipt') as $element => $expected) {
		qaAssert($hook->getElementProperties(array('context' => 'elementproperties', 'elementType' => $element), $object, $action, $hookmanager) === 1, 'Module hook did not resolve '.$element);
		qaAssert($hook->results['classname'] === $expected && $hook->results['module'] === 'kreaimmo', 'Module hook resolved an incorrect class');
	}
	foreach (array('invoice', 'kreaimmo_immoproperty@othermodule', 'immoproperty') as $element) {
		qaAssert($hook->getElementProperties(array('context' => 'elementproperties', 'elementType' => $element), $object, $action, $hookmanager) === 0 && $hook->results === array(), 'Hook replaced another module/native object or leaked stale results');
	}
});
qaCheck('entity hook shares reference dictionaries while retaining business ownership', function () {
	global $db, $hookmanager, $conf;
	$hook = new ActionsKreaImmo($db);
	$object = null;
	$action = '';
	$entity = (int) $conf->entity;
	qaAssert($hook->hookGetEntity(array('element' => 'c_kreaimmo_immoproperty_type'), $object, $action, $hookmanager) === 1 && $hook->resprints === '0,'.$entity, 'Reference dictionary sharing does not include global and current company');
	foreach (array('kreaimmo_immoproperty', 'kreaimmo_immoowner', 'bank_account') as $element) {
		qaAssert($hook->hookGetEntity(array('element' => $element), $object, $action, $hookmanager) === 0 && $hook->resprints === '', 'Business entity sharing overridden or stale dictionary result retained');
	}
});

$baseline = array();
foreach (array('immoowner', 'immoproperty', 'immorenter', 'immorent', 'immoreceipt', 'immocost', 'immocompteur', 'immopayment') as $table) {
	$result = qaQuery('SELECT COUNT(*) AS total FROM '.MAIN_DB_PREFIX.'kreaimmo_'.$table);
	$baseline[$table] = (int) $db->fetch_object($result)->total;
}
qaAssert($db->begin('KreaImmo synthetic integration suite') > 0, 'Cannot begin fixture transaction');
try {
	qaCheck('seed replay preserves customized labels without duplicates and rolls back', function () {
		global $db, $conf;
		$original = qaCostTypeSnapshot();
		$row = $db->fetch_object(qaQuery("SELECT rowid FROM ".MAIN_DB_PREFIX."kreaimmo_immocost_type WHERE entity=".(int) $conf->entity." AND ref='COSTTYPE001'"));
		qaAssert((bool) $row, 'Cannot customize a missing current-company seed');
		qaQuery('SAVEPOINT kreaimmo_qa_seed_replay');
		try {
			qaQuery("UPDATE ".MAIN_DB_PREFIX."kreaimmo_immocost_type SET label='Synthetic customized cost type' WHERE rowid=".(int) $row->rowid);
			$customized = qaCostTypeSnapshot();
			for ($attempt = 0; $attempt < 2; $attempt++) {
				qaAssert(run_sql(dirname(__DIR__, 2).'/sql/data.sql', 1, (int) $conf->entity, 0) > 0, 'Native seed replay failed');
				qaAssert(qaCostTypeSnapshot() === $customized, 'Seed replay overwrote a customized row, added duplicates, or changed another company');
			}
		} finally {
			qaQuery('ROLLBACK TO SAVEPOINT kreaimmo_qa_seed_replay');
			qaQuery('RELEASE SAVEPOINT kreaimmo_qa_seed_replay');
		}
		qaAssert(qaCostTypeSnapshot() === $original, 'Seed-replay fixture rollback did not restore the original rows');
	});
	qaCheck('entity 2 complete synthetic object chain creates without warnings', function () { qaChain(2); });
	qaCheck('entity 3 complete synthetic object chain creates without warnings', function () { qaChain(3); });
	if (isset($fixtures[2], $fixtures[3])) {
		$conf->entity = 2;
		$user = qaUser();
		foreach ($fixtures[2] as $kind => $fixture) {
			if (method_exists($fixture, 'fetchAll')) {
				qaCheck($kind.' list includes own objects and excludes foreign objects', function () use ($kind, $fixture) {
					global $db, $fixtures;
					$class = get_class($fixture);
					$probe = new $class($db);
					$result = $probe->fetchAll('ASC', 't.rowid', 100);
					qaAssert(is_array($result) || $result >= 0, 'List query failed: '.$probe->error.' '.implode('; ', $probe->errors));
					$records = is_array($result) ? $result : $probe->lines;
					$ids = array();
					foreach ($records as $record) {
						$ids[] = (int) (isset($record->id) ? $record->id : $record->rowid);
					}
					qaAssert(in_array((int) $fixture->id, $ids, true), 'Own object absent from list');
					qaAssert(!in_array((int) $fixtures[3][$kind]->id, $ids, true), 'Foreign object present in list');
				});
			}
			qaCheck($kind.' fetches by reference', function () use ($fixture) {
				global $db;
				$class = get_class($fixture);
				$probe = new $class($db);
				qaAssert($probe->fetch(0, $fixture->ref) > 0 && (int) $probe->id === (int) $fixture->id, 'Fetch by reference did not resolve the object ID: '.$probe->error);
			});
			qaCheck($kind.' fetch/update preserves values', function () use ($kind, $fixture) {
				global $user;
				$object = qaFetch(get_class($fixture), $fixture->id);
				$field = $kind === 'compteur' ? 'description' : 'note_private';
				if (!isset($object->fields[$field])) {
					$field = 'note_public';
				}
				$object->$field = 'Synthetic edited value';
				qaAssert($object->update($user, true) > 0, 'Update failed: '.$object->error);
				$reloaded = qaFetch(get_class($object), $object->id);
				qaAssert($reloaded->$field === 'Synthetic edited value', 'Edited value was not persisted');
				if ($kind === 'rent') {
					foreach (array('rentamount' => '1000.25', 'chargesamount' => '50.10', 'totalamount' => '1050.35', 'deposit' => '100.15', 'encours' => '0.05') as $key => $amount) {
						qaDecimal($amount, $reloaded->$key, $key.' precision');
					}
				} elseif ($kind === 'payment') {
					qaDecimal('25.15', $reloaded->amount, 'Payment precision');
				} elseif ($kind === 'receipt') {
					qaDecimal('1050.35', $reloaded->total_amount, 'Receipt precision');
				} elseif ($kind === 'cost') {
					qaDecimal('123.45', $reloaded->amount, 'Cost precision');
				}
			});
			qaCheck($kind.' rejects guessed foreign IDs and preserves foreign data', function () use ($kind) {
				global $db, $fixtures, $user;
				$foreign = $fixtures[3][$kind];
				$before = qaRow($foreign);
				$class = get_class($foreign);
				$probe = new $class($db);
				qaAssert($probe->fetch($foreign->id) === 0, 'Foreign object can be fetched');
				$probe = clone $foreign;
				$probe->note_public = 'Forbidden change';
				qaAssert($probe->update($user, true) < 0, 'Foreign object can be updated');
				qaAssert($probe->delete($user, true) < 0, 'Foreign object can be deleted');
				qaAssert(qaRow($foreign) === $before, 'Foreign object changed after denied writes');
			});
			if (isset($fixture->fields['entity'])) {
				qaCheck($kind.' rejects create with forged entity', function () use ($fixture) {
					global $user;
					$probe = clone $fixture;
					$probe->id = 0;
					$probe->entity = 3;
					$probe->ref .= '-denied';
					qaAssert($probe->create($user, true) < 0, 'Create accepted a foreign entity');
				});
				qaCheck($kind.' rejects entity reassignment', function () use ($fixture) {
					global $user;
					$object = qaFetch(get_class($fixture), $fixture->id);
					$before = qaRow($object);
					$object->entity = 3;
					qaAssert($object->update($user, true) < 0, 'Object can move to another entity');
					qaAssert(qaRow($object) === $before, 'Denied entity reassignment changed data');
				});
			}
		}
		foreach (array('property' => 'fk_owner', 'rent' => 'fk_property', 'receipt' => 'fk_rent', 'cost' => 'fk_property', 'compteur' => 'fk_immoproperty', 'payment' => 'fk_receipt') as $kind => $field) {
			qaCheck($kind.' rejects create with foreign parent', function () use ($kind, $field) {
				global $fixtures, $user;
				$probe = clone $fixtures[2][$kind];
				$probe->id = 0;
				$probe->ref .= '-denied';
				$probe->$field = $fixtures[3][$kind]->$field;
				qaAssert($probe->create($user, true) < 0, 'Foreign parent accepted during create');
			});
			qaCheck($kind.' rejects foreign parent reassignment', function () use ($kind, $field) {
				global $fixtures, $user;
				$object = qaFetch(get_class($fixtures[2][$kind]), $fixtures[2][$kind]->id);
				$before = qaRow($object);
				$object->$field = $fixtures[3][$kind]->$field;
				qaAssert($object->update($user, true) < 0, 'Foreign parent can be assigned');
				qaAssert(qaRow($object) === $before, 'Denied parent reassignment changed data');
			});
		}
		qaCheck('property rejects foreign legal-category reassignment', function () {
			global $db, $fixtures, $user;
			qaQuery("INSERT INTO ".MAIN_DB_PREFIX."c_kreaimmo_juridique(entity,code,label,active) VALUES (3,'QA_FOREIGN','Synthetic foreign legal category',1)");
			$dictionaryId = $db->last_insert_id(MAIN_DB_PREFIX.'c_kreaimmo_juridique');
			$property = qaFetch('KreaImmoProperty', $fixtures[2]['property']->id);
			$before = qaRow($property);
			$property->juridique_id = $dictionaryId;
			qaAssert($property->update($user, true) < 0, 'Foreign legal category accepted');
			qaAssert(qaRow($property) === $before, 'Denied legal-category change altered property');
		});
		qaCheck('receipt generates a synthetic PDF document', function () {
			global $fixtures, $langs, $conf;
			$receipt = qaFetch('KreaImmoReceipt', $fixtures[2]['receipt']->id);
			qaAssert($receipt->generateDocument('kreaimmo_quittance', $langs) > 0, 'Receipt PDF generation failed: '.$receipt->error);
			$ref = dol_sanitizeFileName($receipt->ref);
			$file = $conf->kreaimmo->dir_output.'/receipt/'.$ref.'/'.$ref.'.pdf';
			qaAssert(is_file($file) && filesize($file) > 1000, 'Receipt PDF was not written');
			$stream = fopen($file, 'rb');
			$header = fread($stream, 5);
			fclose($stream);
			qaAssert($header === '%PDF-', 'Generated document is not a PDF');
		});
		qaCheck('receipt PDF generation rejects a foreign company object', function () {
			global $fixtures, $langs;
			$receipt = clone $fixtures[3]['receipt'];
			qaAssert($receipt->generateDocument('kreaimmo_quittance', $langs) <= 0, 'Foreign receipt can generate a document');
		});
		qaCheck('native transaction rollback removes a successful synthetic create', function () {
			global $db, $now;
			qaQuery('SAVEPOINT kreaimmo_qa_rollback');
			$object = qaCreate(qaNew('KreaImmoOwner', array('civility_id' => 1, 'firstname' => 'Rollback', 'lastname' => 'Synthetic', 'email' => 'rollback@example.invalid', 'birth' => $now)));
			qaQuery('ROLLBACK TO SAVEPOINT kreaimmo_qa_rollback');
			$probe = new KreaImmoOwner($db);
			qaAssert($probe->fetch($object->id) === 0, 'Rolled-back owner remains visible');
		});
		qaCheck('native field/status setters preserve ownership boundaries', function () {
			global $fixtures, $user;
			$own = qaFetch('KreaImmoOwner', $fixtures[2]['owner']->id);
			$before = qaRow($own);
			qaAssert($own->setValueFrom('entity', 3) < 0, 'Field setter allowed entity reassignment');
			qaAssert($own->setValueFrom('note_private', 'Denied', '', $own->id, '', 'entity') < 0, 'Field setter accepted a company-wide ID selector');
			qaAssert($own->setStatut(3, null, '', '', 'entity') < 0, 'Status setter allowed entity reassignment');
			qaAssert(qaRow($own) === $before, 'Denied native setters changed own row');
			$foreign = clone $fixtures[3]['owner'];
			$before = qaRow($foreign);
			qaAssert($foreign->setStatut(1) < 0, 'Foreign status can be changed');
			qaAssert($foreign->setStatusCommon($user, 1, true) < 0, 'Foreign native status can be changed');
			qaAssert($foreign->setValueFrom('note_private', 'Denied') < 0, 'Foreign native field can be changed');
			qaAssert(qaRow($foreign) === $before, 'Denied native setters changed foreign row');
			qaAssert($own->setValueFrom('note_private', 'Synthetic native setter') > 0, 'Own native field setter failed');
			qaAssert($own->setStatusCommon($user, 1, true) > 0, 'Own native status setter failed');
			$reloaded = qaFetch('KreaImmoOwner', $own->id);
			qaAssert($reloaded->note_private === 'Synthetic native setter' && (int) $reloaded->status === 1, 'Own native setter values not persisted');
		});
		qaCheck('API denies every operation without renter grants', function () {
			global $fixtures;
			DolibarrApiAccess::$user = qaUser(0, 0, 0);
			$id = $fixtures[2]['renter']->id;
			foreach (array(function () use ($id) { (new KreaImmoApi())->get($id); }, function () { (new KreaImmoApi())->index(); }, function () { (new KreaImmoApi())->post(array('lastname' => 'Denied')); }, function () use ($id) { (new KreaImmoApi())->put($id, array('lastname' => 'Denied')); }, function () use ($id) { (new KreaImmoApi())->delete($id); }) as $action) {
				qaRestError(401, $action);
			}
		});
		qaCheck('API read-only grants deny create/update/delete', function () {
			global $fixtures;
			DolibarrApiAccess::$user = qaUser(1, 0, 0);
			$id = $fixtures[2]['renter']->id;
			qaAssert((int) (new KreaImmoApi())->get($id)->id === (int) $id, 'Read grant does not allow own renter read');
			qaRestError(401, function () { (new KreaImmoApi())->post(array('lastname' => 'Denied')); });
			qaRestError(401, function () use ($id) { (new KreaImmoApi())->put($id, array('lastname' => 'Denied')); });
			qaRestError(401, function () use ($id) { (new KreaImmoApi())->delete($id); });
		});
		qaCheck('API hides guessed foreign IDs and entity-scopes lists', function () {
			global $fixtures;
			DolibarrApiAccess::$user = qaUser();
			$id = $fixtures[3]['renter']->id;
			qaRestError(404, function () use ($id) { (new KreaImmoApi())->get($id); });
			qaRestError(404, function () use ($id) { (new KreaImmoApi())->put($id, array('lastname' => 'Denied')); });
			qaRestError(404, function () use ($id) { (new KreaImmoApi())->delete($id); });
			$list = (new KreaImmoApi())->index();
			$ids = array();
			foreach ($list as $renter) {
				$ids[] = (int) $renter->id;
				qaAssert((int) $renter->entity === 2, 'Foreign entity returned in API list');
			}
			qaAssert(in_array((int) $fixtures[2]['renter']->id, $ids, true), 'Own renter absent from API list');
			qaAssert(!in_array((int) $id, $ids, true), 'Foreign renter present in API list');
		});
		qaCheck('API creates, edits and deletes a renter using real fields', function () {
			global $now;
			DolibarrApiAccess::$user = qaUser();
			$id = (new KreaImmoApi())->post(array('lastname' => 'API Synthetic', 'firstname' => 'Test', 'civility_id' => 1, 'birth' => $now, 'email' => 'api@example.invalid'));
			qaAssert($id > 0, 'API did not create renter');
			$renter = (new KreaImmoApi())->put($id, array('lastname' => 'API Edited'));
			qaAssert($renter->lastname === 'API Edited', 'API did not update renter');
			(new KreaImmoApi())->delete($id);
			qaRestError(404, function () use ($id) { (new KreaImmoApi())->get($id); });
		});
		qaCheck('API rejects protected field mass assignment', function () {
			global $fixtures;
			DolibarrApiAccess::$user = qaUser();
			$id = $fixtures[2]['renter']->id;
			$before = qaRow($fixtures[2]['renter']);
			qaRestError(403, function () { (new KreaImmoApi())->post(array('lastname' => 'Denied', 'entity' => 3)); });
			foreach (array('entity' => 3, 'id' => $fixtures[3]['renter']->id, 'fk_user_creat' => 99) as $field => $value) {
				qaRestError(403, function () use ($id, $field, $value) { (new KreaImmoApi())->put($id, array($field => $value)); });
			}
			qaAssert(qaRow($fixtures[2]['renter']) === $before, 'Protected field request changed renter');
		});
		qaCheck('API validates payloads and sorting before SQL writes', function () {
			global $fixtures;
			DolibarrApiAccess::$user = qaUser();
			qaRestError(400, function () { (new KreaImmoApi())->post(array('firstname' => 'Missing lastname')); });
			qaRestError(400, function () { (new KreaImmoApi())->post(null); });
			qaRestError(400, function () { (new KreaImmoApi())->index('t.rowid; SELECT 1'); });
			qaRestError(400, function () { (new KreaImmoApi())->index('t.rowid', 'INVALID'); });
			qaRestError(400, function () use ($fixtures) { (new KreaImmoApi())->put($fixtures[2]['renter']->id, array('lastname' => array('invalid'))); });
		});
		qaCheck('API external users only access their own third-party renters', function () {
			global $db, $fixtures, $user, $now;
			$companies = array();
			foreach (array('First', 'Second') as $name) {
				$company = new Societe($db);
				$company->name = 'Synthetic '.$name.' third party';
				$company->entity = 2;
				$company->client = 0;
				$company->fournisseur = 0;
				qaAssert($company->create($user, true) > 0, 'Synthetic third party creation failed: '.$company->error);
				$companies[] = $company;
			}
			$own = qaFetch('KreaImmoRenter', $fixtures[2]['renter']->id);
			$own->fk_soc = $companies[0]->id;
			qaAssert($own->update($user, true) > 0, 'Cannot link synthetic own renter');
			$other = qaCreate(qaNew('KreaImmoRenter', array('lastname' => 'Other synthetic renter', 'civility_id' => 1, 'birth' => $now, 'fk_soc' => $companies[1]->id)));
			$external = qaUser();
			$external->societe_id = $companies[0]->id;
			$external->socid = $companies[0]->id;
			DolibarrApiAccess::$user = $external;
			qaAssert((int) (new KreaImmoApi())->get($own->id)->id === (int) $own->id, 'External user cannot read own renter');
			qaRestError(404, function () use ($other) { (new KreaImmoApi())->get($other->id); });
			qaRestError(404, function () use ($other) { (new KreaImmoApi())->put($other->id, array('lastname' => 'Denied')); });
			qaRestError(404, function () use ($other) { (new KreaImmoApi())->delete($other->id); });
			foreach ((new KreaImmoApi())->index() as $record) {
				qaAssert((int) $record->fk_soc === (int) $companies[0]->id, 'External API list leaked another third party');
			}
			qaRestError(403, function () use ($own, $companies) { (new KreaImmoApi())->put($own->id, array('fk_soc' => $companies[1]->id)); });
			qaRestError(403, function () use ($companies) { (new KreaImmoApi())->post(array('lastname' => 'Denied', 'fk_soc' => $companies[1]->id)); });
			$id = (new KreaImmoApi())->post(array('lastname' => 'External synthetic renter', 'fk_soc' => $companies[0]->id));
			qaAssert($id > 0, 'External API create failed for own third party');
			(new KreaImmoApi())->delete($id);
			DolibarrApiAccess::$user = qaUser();
			qaAssert($other->delete($user, true) > 0, 'Cannot delete synthetic second renter');
		});
		$aux = array();
		qaCheck('auxiliary object chains create in both companies', function () use (&$aux) {
			$aux[2] = qaAuxChain(2);
			$aux[3] = qaAuxChain(3);
		});
		$conf->entity = 2;
		$user = qaUser();
		if (isset($aux[2], $aux[3])) {
			foreach (array('owner_type' => 'label', 'cost_type' => 'label', 'building' => 'label', 'cost_detail' => 'amount', 'spreadzone' => 'percent_application', 'compteur_cost' => 'amount') as $kind => $field) {
				qaCheck($kind.' CRUD and inherited company isolation', function () use ($aux, $kind, $field) {
					global $db, $user;
					$own = qaFetch(get_class($aux[2][$kind]), $aux[2][$kind]->id);
					$own->$field = $field === 'label' ? 'Synthetic edited auxiliary' : '13.45';
					qaAssert($own->update($user, true) > 0, 'Auxiliary update failed: '.$own->error);
					$reloaded = qaFetch(get_class($own), $own->id);
					if ($field === 'label') {
						qaAssert($reloaded->$field === $own->$field, 'Auxiliary label not persisted');
					} else {
						qaDecimal('13.45', $reloaded->$field, 'Auxiliary value');
					}
					$foreign = $aux[3][$kind];
					$before = qaRow($foreign);
					$class = get_class($foreign);
					$probe = new $class($db);
					qaAssert($probe->fetch($foreign->id) === 0, 'Foreign auxiliary record can be fetched');
					$probe = clone $foreign;
					$probe->$field = $own->$field;
					qaAssert($probe->update($user, true) < 0, 'Foreign auxiliary record can be updated');
					qaAssert($probe->delete($user, true) < 0, 'Foreign auxiliary record can be deleted');
					$probe->id = 0;
					qaAssert($probe->create($user, true) < 0, 'Foreign auxiliary parent/entity accepted during create');
					qaAssert(qaRow($foreign) === $before, 'Denied auxiliary writes changed foreign data');
				});
			}
			foreach (array_reverse($aux[2], true) as $kind => $fixture) {
				qaCheck($kind.' deletes through native lifecycle', function () use ($fixture) {
					global $user;
					$object = qaFetch(get_class($fixture), $fixture->id);
					qaAssert($object->delete($user, true) > 0, 'Auxiliary delete failed: '.get_class($object).' '.$object->error);
				});
			}
		}
		qaCheck('annual meter costs retain company ownership with a shared meter type', function () {
			global $db, $conf, $user;
			$originalActor = $user;
			qaQuery("INSERT INTO ".MAIN_DB_PREFIX."c_kreaimmo_immocompteur_type(ref,entity,label,active) VALUES ('QA_SHARED',0,'Synthetic shared meter type',1)");
			$typeId = $db->last_insert_id(MAIN_DB_PREFIX.'c_kreaimmo_immocompteur_type');
			try {
				$costs = array();
				foreach (array(2, 3) as $entity) {
					$conf->entity = $entity;
					$user = qaUser();
					$costs[$entity] = qaCreate(qaNew('KreaImmoCompteur_Cost', array('fk_immocompteur_type' => $typeId, 'cost_year' => 2026, 'amount' => '15.25')));
				}
				foreach (array(2, 3) as $entity) {
					$conf->entity = $entity;
					$user = qaUser();
					$own = qaFetch('KreaImmoCompteur_Cost', $costs[$entity]->id);
					$own->amount = '16.35';
					qaAssert($own->update($user, true) > 0, 'Cannot update own shared-type annual cost');
					$foreign = $costs[$entity === 2 ? 3 : 2];
					$before = qaRow($foreign);
					$probe = new KreaImmoCompteur_Cost($db);
					qaAssert($probe->fetch($foreign->id) === 0, 'Shared dictionary type exposed another company annual cost');
					$probe = clone $foreign;
					$probe->amount = '999.99';
					qaAssert($probe->update($user, true) < 0, 'Shared dictionary type allowed foreign annual-cost update');
					qaAssert($probe->delete($user, true) < 0, 'Shared dictionary type allowed foreign annual-cost delete');
					qaAssert(qaRow($foreign) === $before, 'Denied annual-cost writes changed foreign company data');
				}
			} finally {
				$conf->entity = 2;
				$user = $originalActor;
			}
		});
		qaCheck('synthetic bank posting preserves account/line IDs and retries once', function () {
			global $db, $user, $conf, $fixtures, $now, $run;
			require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';
			$conf->modules['banque'] = 'banque';
			$accounts = array();
			foreach (array(2, 3) as $entity) {
				$conf->entity = $entity;
				$account = new Account($db);
				$account->ref = 'QA'.$entity.substr($run, -7);
				$account->label = 'Synthetic bank account';
				$account->country_id = 1;
				$account->currency_code = 'EUR';
				$account->date_solde = $now;
				$account->balance = 0;
				$account->type = Account::TYPE_CURRENT;
				$account->clos = 0;
				$account->status = 0;
				qaAssert($account->create($user, true) > 0, 'Synthetic bank account create failed: '.$account->error);
				$accounts[$entity] = $account;
			}
			$conf->entity = 2;
			qaQuery("INSERT INTO ".MAIN_DB_PREFIX."c_paiement(entity,code,libelle,type,active) VALUES (2,'QA_VIR','Synthetic transfer',2,1)");
			$methodId = $db->last_insert_id(MAIN_DB_PREFIX.'c_paiement');
			$payment = qaFetch('KreaImmoPayment', $fixtures[2]['payment']->id);
			$payment->id = 0;
			$payment->ref .= '-bank';
			$payment->fk_mode_reglement = $methodId;
			qaCreate($payment);
			$before = qaRow($payment);
			qaAssert($payment->addPaymentToBank($user, 'kreaimmo_immopayment', 'Synthetic payment', $accounts[3]->id, '', '') < 0, 'Foreign bank account accepted');
			qaAssert(qaRow($payment) === $before, 'Denied bank posting changed payment');
			$foreignLine = $db->fetch_object(qaQuery('SELECT rowid FROM '.MAIN_DB_PREFIX.'bank WHERE fk_account='.(int) $accounts[3]->id));
			qaAssert($foreignLine && $payment->update_fk_bank($foreignLine->rowid) < 0, 'Foreign bank line accepted');
			$count = $db->fetch_object(qaQuery('SELECT COUNT(*) AS total FROM '.MAIN_DB_PREFIX.'bank WHERE fk_account='.(int) $accounts[2]->id))->total;
			qaAssert($payment->addPaymentToBank($user, 'kreaimmo_immopayment', 'Synthetic payment', $accounts[2]->id, '', '') > 0, 'Bank posting failed: '.$payment->error.' '.$db->lasterror());
			$reloaded = qaFetch('KreaImmoPayment', $payment->id);
			qaAssert((int) $reloaded->fk_account === (int) $accounts[2]->id && $reloaded->fk_bank > 0, 'Account and bank line links were not stored independently');
			$line = $db->fetch_object(qaQuery('SELECT fk_account, amount FROM '.MAIN_DB_PREFIX.'bank WHERE rowid='.(int) $reloaded->fk_bank));
			qaAssert((int) $line->fk_account === (int) $reloaded->fk_account, 'Stored bank line belongs to another account');
			qaDecimal('25.15', $line->amount, 'Posted amount');
			qaAssert($reloaded->addPaymentToBank($user, 'kreaimmo_immopayment', 'Synthetic payment retry', $accounts[2]->id, '', '') > 0, 'Bank retry failed: '.$reloaded->error);
			$after = $db->fetch_object(qaQuery('SELECT COUNT(*) AS total FROM '.MAIN_DB_PREFIX.'bank WHERE fk_account='.(int) $accounts[2]->id))->total;
			qaAssert((int) $after === (int) $count + 1, 'Retry created a duplicate bank line');
			$before = qaRow($reloaded);
			$bankLineId = (int) $reloaded->fk_bank;
			qaQuery('UPDATE '.MAIN_DB_PREFIX.'bank SET rappro=1 WHERE rowid='.$bankLineId);
			qaAssert($reloaded->delete($user, true) < 0, 'Reconciled bank payment can be deleted');
			qaAssert(qaRow($reloaded) === $before, 'Denied reconciled deletion changed payment');
			qaQuery('UPDATE '.MAIN_DB_PREFIX.'bank SET rappro=0 WHERE rowid='.$bankLineId);
			qaAssert($reloaded->delete($user, true) > 0, 'Unreconciled payment deletion failed: '.$reloaded->error);
			qaAssert($db->num_rows(qaQuery('SELECT rowid FROM '.MAIN_DB_PREFIX.'bank WHERE rowid='.$bankLineId)) === 0, 'Payment deletion left an orphan bank line');
			qaAssert($db->num_rows(qaQuery('SELECT rowid FROM '.MAIN_DB_PREFIX.'bank_url WHERE fk_bank='.$bankLineId)) === 0, 'Payment deletion left an orphan bank URL');
		});
		$conf->entity = 2;
		$user = qaUser();
		foreach (array_reverse($fixtures[2], true) as $kind => $fixture) {
			qaCheck($kind.' deletes through its native lifecycle', function () use ($fixture) {
				global $user, $db;
				$object = qaFetch(get_class($fixture), $fixture->id);
				qaAssert($object->delete($user, true) > 0, 'Delete failed: '.$object->error);
				$class = get_class($fixture);
				$probe = new $class($db);
				qaAssert($probe->fetch($fixture->id) === 0, 'Deleted object remains visible');
			});
		}
	}
} finally {
	while ($db->transaction_opened > 0) {
		$db->rollback('KreaImmo integration fixture cleanup');
	}
	$conf->entity = $originalEntity;
	$user = $originalUser;
}
qaCheck('suite rollback preserves all original object counts', function () use ($baseline) {
	global $db;
	foreach ($baseline as $table => $expected) {
		$result = qaQuery('SELECT COUNT(*) AS total FROM '.MAIN_DB_PREFIX.'kreaimmo_'.$table);
		qaAssert((int) $db->fetch_object($result)->total === $expected, 'Fixture cleanup changed '.$table.' row count');
	}
});

$failed = count(array_filter($results, function ($result) { return $result['status'] === 'FAIL'; }));
echo json_encode(array('database' => $db->database_name, 'php' => PHP_VERSION, 'dolibarr' => DOL_VERSION, 'bootstrap' => isset($kreaimmoIntegrationBootstrap) ? $kreaimmoIntegrationBootstrap : null, 'checks' => $results, 'passed' => count($results) - $failed, 'failed' => $failed), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
exit($failed ? 1 : 0);
