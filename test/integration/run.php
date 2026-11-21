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
	foreach (array('owner', 'property', 'renter', 'rent', 'receipt', 'cost', 'compteur', 'immocompteur', 'payment') as $permission) {
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

function qaMailingActor($renterRead = 1, $nativeRead = 1, $nativeWrite = 1)
{
	$actor = qaUser($renterRead);
	$actor->rights->mailing = (object) array('lire' => $nativeRead, 'creer' => $nativeWrite);
	return $actor;
}

function qaMailingRows($mailingId = null)
{
	global $db;
	$sql = 'SELECT * FROM '.MAIN_DB_PREFIX.'mailing_cibles';
	if ($mailingId !== null) {
		$sql .= ' WHERE fk_mailing='.(int) $mailingId;
	}
	$result = qaQuery($sql.' ORDER BY rowid');
	$rows = array();
	while ($row = $db->fetch_array($result)) {
		$rows[] = $row;
	}
	return $rows;
}

function qaMailingChecks()
{
	global $db, $conf, $user, $now, $run;
	$originalActor = $user;
	$originalEntity = $conf->entity;
	$originalModules = $conf->modules;
	$originalGet = $_GET;
	$originalPost = $_POST;
	$mailings = array();
	$renters = array();
	$selectorClass = 'mailing_mailinglist_kreaimmo_immorenter';
	try {
		$conf->modules['kreaimmo'] = 'kreaimmo';
		$conf->modules['mailing'] = 'mailing';
		$_GET['filter'] = $_POST['filter'] = 'none';
		qaCheck('mailing fixtures create native drafts in two companies without sending', function () use (&$mailings, &$renters) {
			global $db, $conf, $user, $now, $run;
			foreach (array(2, 3) as $entity) {
				$conf->entity = $entity;
				$user = qaMailingActor();
				$renters[$entity] = qaCreate(qaNew('KreaImmoRenter', array('firstname' => 'Mailing', 'lastname' => 'Synthetic company '.$entity, 'email' => strtolower($run).'-'.$entity.'@example.invalid', 'civility_id' => 1, 'birth' => $now, 'status' => 1)));
				$draft = new Mailing($db);
				$draft->title = $run.' synthetic draft '.$entity;
				$draft->sujet = 'Synthetic recipient selection';
				$draft->body = 'Integration fixture only.';
				$draft->email_from = 'fixture@example.invalid';
				$draft->messtype = 'email';
				$draft->evenunsubscribe = 0;
				qaAssert($draft->create($user, true) > 0, 'Native mailing create failed: '.$draft->error);
				$mailings[$entity] = $draft;
			}
		});
		$conf->entity = 2;
		$user = qaMailingActor();
		if (!isset($mailings[2], $mailings[3], $renters[2], $renters[3])) {
			return;
		}
		qaCheck('mailing selector counts and persists only own-company recipient data', function () use ($selectorClass, $mailings, $renters) {
			global $db;
			$selector = new $selectorClass($db);
			$count = $selector->getNbOfRecipients();
			qaAssert($count === 2, 'Recipient count must include only the two current-company addresses');
			qaAssert($selector->add_to_target($mailings[2]->id) === $count, 'Native recipient insertion failed: '.$selector->error);
			$rows = qaMailingRows($mailings[2]->id);
			qaAssert(count($rows) === $count && qaMailingRows($mailings[3]->id) === array(), 'Recipient selection modified the wrong mailing');
			$ownAddressFound = false;
			foreach ($rows as $row) {
				$result = qaQuery('SELECT entity, firstname, lastname, email FROM '.MAIN_DB_PREFIX.'kreaimmo_immorenter WHERE rowid='.(int) $row['source_id']);
				$renter = $db->fetch_object($result);
				qaAssert($renter && (int) $renter->entity === 2, 'Recipient source belongs to another company');
				qaAssert($row['lastname'] === $renter->lastname && $row['firstname'] === $renter->firstname && $row['email'] === $renter->email, 'Native recipient keys lost renter identity data');
				qaAssert($row['other'] === '' && $row['source_type'] === 'kreaimmo_immorenter', 'Invalid native recipient metadata');
				qaAssert(strpos($row['source_url'], '/kreaimmo/renter/immorenter_card.php') !== false && strpos($row['source_url'], 'id='.(int) $row['source_id']) !== false, 'Recipient source link is invalid');
				qaAssert(!empty($row['tag']) && (int) $row['statut'] === 0 && empty($row['date_envoi']), 'Recipient was sent or lacks its native unsubscribe tag');
				qaAssert($row['email'] !== $renters[3]->email, 'Foreign-company address leaked into a mailing');
				$ownAddressFound = $ownAddressFound || $row['email'] === $renters[2]->email;
			}
			qaAssert($ownAddressFound, 'Current-company unique address was omitted');
			$draft = new Mailing($db);
			qaAssert($draft->fetch($mailings[2]->id) > 0 && (int) $draft->nbemail === $count && (int) $draft->status === Mailing::STATUS_DRAFT && empty($draft->date_envoi), 'Selection changed delivery state or failed to update the native recipient count');
		});
		qaCheck('mailing selector follows native statuses and deduplicates recipient retries', function () use ($selectorClass, $mailings) {
			global $db;
			$before = qaMailingRows();
			try {
				foreach (array(Mailing::STATUS_DRAFT, Mailing::STATUS_VALIDATED, Mailing::STATUS_SENTPARTIALY, Mailing::STATUS_SENTCOMPLETELY) as $status) {
					qaQuery('UPDATE '.MAIN_DB_PREFIX.'mailing SET statut='.(int) $status.' WHERE rowid='.(int) $mailings[2]->id);
					$selector = new $selectorClass($db);
					qaAssert($selector->add_to_target($mailings[2]->id) === 0, 'Native allowed status rejected or retry duplicated recipients');
					qaAssert(qaMailingRows() === $before, 'Retry changed existing recipient rows');
				}
				qaQuery('UPDATE '.MAIN_DB_PREFIX.'mailing SET statut=99 WHERE rowid='.(int) $mailings[2]->id);
				qaAssert((new $selectorClass($db))->add_to_target($mailings[2]->id) < 0, 'Unknown mailing state accepted');
				qaAssert(qaMailingRows() === $before, 'Denied unknown-state selection changed recipients');
			} finally {
				qaQuery('UPDATE '.MAIN_DB_PREFIX.'mailing SET statut=0 WHERE rowid='.(int) $mailings[2]->id);
			}
		});
		qaCheck('mailing selector denies guessed foreign mailing IDs', function () use ($selectorClass, $mailings) {
			global $db;
			$before = qaMailingRows();
			$selector = new $selectorClass($db);
			qaAssert($selector->add_to_target($mailings[3]->id) < 0 && $selector->error === 'KreaImmoAccessDenied', 'Foreign mailing accepted recipients');
			qaAssert(qaMailingRows() === $before, 'Foreign-mailing denial changed recipients');
		});
		foreach (array('renter read' => array(0, 1, 1), 'native mailing read' => array(1, 0, 1), 'native mailing write' => array(1, 1, 0)) as $permission => $grants) {
			qaCheck('mailing selector enforces '.$permission.' permission', function () use ($selectorClass, $mailings, $grants) {
				global $db, $user;
				$user = qaMailingActor($grants[0], $grants[1], $grants[2]);
				$before = qaMailingRows();
				$selector = new $selectorClass($db);
				qaAssert($selector->add_to_target($mailings[2]->id) < 0 && $selector->error === 'KreaImmoAccessDenied', 'Missing mailing permission did not deny selection');
				qaAssert(($selector->getNbOfRecipients() >= 0) === (bool) ($grants[0] && $grants[1]), 'Recipient count does not enforce read permissions independently of write');
				qaAssert(qaMailingRows() === $before, 'Denied mailing write changed recipients');
			});
		}
		qaCheck('mailing selector denies external and unauthenticated users', function () use ($selectorClass, $mailings) {
			global $db, $user;
			$before = qaMailingRows();
			foreach (array('external', 'unauthenticated') as $type) {
				$user = qaMailingActor();
				if ($type === 'external') {
					$user->socid = $user->societe_id = 999;
				} else {
					$user->id = 0;
				}
				$selector = new $selectorClass($db);
				qaAssert(!$selector->enabled && $selector->getNbOfRecipients() < 0 && $selector->add_to_target($mailings[2]->id) < 0, 'Restricted user can use the mailing selector');
			}
			qaAssert(qaMailingRows() === $before, 'Restricted-user selection changed recipients');
		});
		foreach (array('kreaimmo', 'mailing') as $module) {
			qaCheck('mailing selector denies inactive '.$module.' module', function () use ($selectorClass, $mailings, $module) {
				global $db, $user, $conf;
				$user = qaMailingActor();
				$modules = $conf->modules;
				$before = qaMailingRows();
				try {
					foreach ($conf->modules as $key => $value) {
						if ($key === $module || $value === $module) {
							unset($conf->modules[$key]);
						}
					}
					$selector = new $selectorClass($db);
					qaAssert(!$selector->enabled && $selector->getNbOfRecipients() < 0 && $selector->add_to_target($mailings[2]->id) < 0, 'Inactive module allows mailing selection');
					qaAssert(qaMailingRows() === $before, 'Inactive-module selection changed recipients');
				} finally {
					$conf->modules = $modules;
				}
			});
		}
		qaCheck('mailing selector validates status filters and preserves scoped counts', function () use ($selectorClass, $mailings) {
			global $db, $user;
			$user = qaMailingActor();
			$before = qaMailingRows();
			$_GET['filter'] = $_POST['filter'] = '1';
			qaAssert((new $selectorClass($db))->getNbOfRecipients() === 1, 'Active-renter filter does not retain company scope');
			foreach (array('invalid', array('1')) as $filter) {
				$_GET['filter'] = $_POST['filter'] = $filter;
				$selector = new $selectorClass($db);
				qaAssert($selector->getNbOfRecipients() < 0 && $selector->add_to_target($mailings[2]->id) < 0, 'Malformed filter accepted');
			}
			qaAssert(qaMailingRows() === $before, 'Malformed filter changed recipients');
		});
	} finally {
		$conf->entity = $originalEntity;
		$conf->modules = $originalModules;
		$user = $originalActor;
		$_GET = $originalGet;
		$_POST = $originalPost;
	}
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

/** Exercise public real-estate evidence with synthetic native records only. */
function qaRecordApiChecks(array $aux)
{
	global $conf, $fixtures;
	$resources = array('owners' => array('owner', 'owner'), 'buildings' => array('building', 'property'),
		'properties' => array('property', 'property'), 'renters' => array('renter', 'renter'),
		'leases' => array('rent', 'rent'), 'receipts' => array('receipt', 'receipt'),
		'payments' => array('payment', 'payment'), 'costs' => array('cost', 'cost'), 'meters' => array('compteur', 'immocompteur'));
	$conf->entity = 2;
	foreach ($resources as $resource => $definition) {
		$kind = $definition[0];
		$own = $kind === 'building' ? $aux[2][$kind] : $fixtures[2][$kind];
		$foreign = $kind === 'building' ? $aux[3][$kind] : $fixtures[3][$kind];
		qaCheck('record API '.$resource.' list/detail preserve company, projection and order', function () use ($resource, $own, $foreign) {
			DolibarrApiAccess::$user = qaUser();
			$api = new KreaImmoApi();
			$list = $api->getRecords($resource);
			qaAssert($list['schema'] === 'kreaimmo_records_v1' && $list['entity_id'] === 2 && $list['resource'] === $resource, 'Invalid list identity');
			qaAssert($list['limit'] === 100 && $list['page'] === 0 && is_bool($list['has_more']) && (array) $list['filters'] === array(), 'Invalid pagination metadata');
			$detail = $api->getRecord($resource, $own->id);
			qaAssert($detail['schema'] === 'kreaimmo_record_v1' && $detail['entity_id'] === 2 && $detail['resource'] === $resource, 'Invalid detail identity');
			$previous = 0;
			$found = false;
			foreach ($list['items'] as $record) {
				qaAssert(is_int($record['id']) && $record['id'] > $previous && $record['entity'] === 2, 'Unordered, duplicate or foreign-company record');
				qaAssert($record['id'] !== (int) $foreign->id, 'Foreign-company record leaked');
				foreach (array('note_private', 'note_public', 'db', 'fields', 'array_options', 'birth', 'import_key') as $protected) {
					qaAssert(!array_key_exists($protected, $record), 'Protected or internal field exposed');
				}
				$previous = $record['id'];
				if ($record['id'] === (int) $own->id) {
					qaAssert($detail['record'] === $record, 'List and detail projections differ');
					$found = true;
				}
			}
			qaAssert($found, 'Own synthetic record missing');
			qaRestError(404, function () use ($api, $resource, $foreign) { $api->getRecord($resource, $foreign->id); });
			$empty = $api->getRecords($resource, 200, 1000);
			qaAssert($empty['items'] === array() && $empty['has_more'] === false, 'Empty page has incorrect continuation');
		});
		qaCheck('record API '.$resource.' filters bind exact native relationships', function () use ($resource, $own) {
			DolibarrApiAccess::$user = qaUser();
			$api = new KreaImmoApi();
			$record = $api->getRecord($resource, $own->id)['record'];
			$filters = array('ref' => 'ref', 'property_id' => $resource === 'meters' ? 'fk_immoproperty' : 'fk_property',
				'renter_id' => 'fk_renter', 'lease_id' => 'fk_rent', 'owner_id' => 'fk_owner');
			foreach ($filters as $name => $field) {
				$args = array($resource, 100, 0, null, null, null, null, null);
				$position = array('ref' => 3, 'property_id' => 4, 'renter_id' => 5, 'lease_id' => 6, 'owner_id' => 7);
				$value = isset($record[$field]) && $record[$field] !== '' && $record[$field] !== 0 ? $record[$field] : ($name === 'ref' ? 'missing-reference' : 1);
				$args[$position[$name]] = $value;
				if (!array_key_exists($field, $record)) {
					qaRestError(400, function () use ($api, $args) { call_user_func_array(array($api, 'getRecords'), $args); });
					continue;
				}
				$list = call_user_func_array(array($api, 'getRecords'), $args);
				qaAssert((array) $list['filters'] === array($name => $value), 'Applied filter metadata differs');
				foreach ($list['items'] as $item) {
					qaAssert($item[$field] === $value, 'Filter returned a mismatched native relationship');
				}
				if (isset($record[$field]) && $record[$field] !== '' && $record[$field] !== 0) {
					qaAssert(in_array((int) $own->id, array_column($list['items'], 'id'), true), 'Matching filter omitted synthetic record');
				}
			}
		});
		qaCheck('record API '.$resource.' enforces grants and internal-user boundary', function () use ($resource, $definition, $own) {
			$api = new KreaImmoApi();
			DolibarrApiAccess::$user = qaUser(0, 0, 0);
			qaRestError(403, function () use ($api, $resource) { $api->getRecords($resource); });
			qaRestError(403, function () use ($api, $resource, $own) { $api->getRecord($resource, $own->id); });
			DolibarrApiAccess::$user->rights->kreaimmo->{$definition[1]}->read = 1;
			qaAssert($api->getRecord($resource, $own->id)['record']['id'] === (int) $own->id, 'Granular permission rejected');
			DolibarrApiAccess::$user = qaUser(0, 0, 0);
			DolibarrApiAccess::$user->rights->kreaimmo->read = 1;
			qaAssert($api->getRecord($resource, $own->id)['record']['id'] === (int) $own->id, 'Module-wide permission rejected');
			DolibarrApiAccess::$user->societe_id = 99;
			qaRestError(403, function () use ($api, $resource) { $api->getRecords($resource); });
			qaRestError(403, function () use ($api, $resource, $own) { $api->getRecord($resource, $own->id); });
		});
	}
	qaCheck('record API validates pagination, references, IDs and resource names', function () {
		DolibarrApiAccess::$user = qaUser();
		$api = new KreaImmoApi();
		foreach (array(0, 201, -1, '1 OR 1=1', true, 1.5) as $limit) {
			qaRestError(400, function () use ($api, $limit) { $api->getRecords('owners', $limit); });
		}
		foreach (array(-1, 1001, '0 UNION SELECT 1', false, 1.5) as $page) {
			qaRestError(400, function () use ($api, $page) { $api->getRecords('owners', 100, $page); });
		}
		foreach (array(0, -1, '1 OR 1=1', true, 1.5) as $id) {
			qaRestError(400, function () use ($api, $id) { $api->getRecord('owners', $id); });
			qaRestError(400, function () use ($api, $id) { $api->getRecords('leases', 100, 0, null, $id); });
		}
		foreach (array('', "bad\nref", str_repeat('a', 129), array('ref')) as $ref) {
			qaRestError(400, function () use ($api, $ref) { $api->getRecords('owners', 100, 0, $ref); });
		}
		qaAssert($api->getRecords('owners', 100, 0, "' OR 1=1 --_%")['items'] === array(), 'Reference escaped out of exact matching');
		qaRestError(400, function () use ($api) { $api->getRecords('../owners'); });
		qaRestError(400, function () use ($api) { $api->getRecord('societe', 1); });
	});
	qaCheck('record API follows actual property ownership for buildings and meters', function () use ($aux, $fixtures) {
		global $db;
		DolibarrApiAccess::$user = qaUser();
		$api = new KreaImmoApi();
		foreach (array('buildings' => array($aux[2]['building'], 'fk_property'), 'meters' => array($fixtures[2]['compteur'], 'fk_immoproperty')) as $resource => $definition) {
			$object = $definition[0];
			$field = $definition[1];
			$original = (int) $object->$field;
			try {
				// Deliberately corrupt this synthetic child to reproduce inherited legacy data.
				qaQuery('UPDATE '.$db->prefix().$object->table_element.' SET '.$field.'='.(int) $fixtures[3]['property']->id.' WHERE rowid='.(int) $object->id);
				qaRestError(404, function () use ($api, $resource, $object) { $api->getRecord($resource, $object->id); });
				$list = $api->getRecords($resource);
				qaAssert(!in_array((int) $object->id, array_column($list['items'], 'id'), true), 'Child leaked after parent changed companies');
			} finally {
				qaQuery('UPDATE '.$db->prefix().$object->table_element.' SET '.$field.'='.$original.' WHERE rowid='.(int) $object->id);
			}
		}
	});
	qaCheck('record API uses a sentinel for bounded continuation and preserves decimals', function () use ($fixtures) {
		DolibarrApiAccess::$user = qaUser();
		$extra = qaCreate(qaNew('KreaImmoRenter', array('lastname' => 'Pagination synthetic', 'civility_id' => 1)));
		$api = new KreaImmoApi();
		$first = $api->getRecords('renters', 1, 0);
		$second = $api->getRecords('renters', 1, 1);
		qaAssert(count($first['items']) === 1 && $first['has_more'] && count($second['items']) === 1 && $second['items'][0]['id'] > $first['items'][0]['id'], 'Sentinel pagination repeats or skips its boundary');
		foreach (array('leases' => array('rent', 'rentamount'), 'receipts' => array('receipt', 'total_amount'), 'payments' => array('payment', 'amount'), 'costs' => array('cost', 'amount')) as $resource => $definition) {
			$object = $fixtures[2][$definition[0]];
			$field = $definition[1];
			$record = $api->getRecord($resource, $object->id)['record'];
			qaAssert(is_string($record[$field]) && $record[$field] === (string) qaRow($object)[$field], 'Database monetary string was reformatted');
		}
		global $user;
		qaAssert($extra->delete($user, true) > 0, 'Cannot remove synthetic pagination renter');
	});
	qaCheck('record API rejects inactive module and unauthenticated access', function () {
		global $conf;
		$api = new KreaImmoApi();
		DolibarrApiAccess::$user = null;
		qaRestError(401, function () use ($api) { $api->getRecords('owners'); });
		DolibarrApiAccess::$user = qaUser();
		$modules = $conf->modules;
		try {
			unset($conf->modules['kreaimmo']);
			qaRestError(403, function () use ($api) { $api->getRecords('owners'); });
		} finally {
			$conf->modules = $modules;
		}
		DolibarrApiAccess::$user = qaUser();
	});
}

/** Preview/apply one synthetic operation and verify its public projection parity. */
function qaManagementApply($resource, array $request)
{
	$request['confirm'] = true;
	$request['dry_run'] = true;
	$preview = (new KreaImmoApi())->manageRecord($resource, $request);
	qaAssert($preview['schema'] === 'kreaimmo_management_v1' && $preview['status'] === 'dry_run' && $preview['currency'] === 'EUR', 'Invalid management preview');
	qaAssert((bool) preg_match('/^[a-f0-9]{64}$/D', $preview['expected_hash']), 'Preview hash is missing');
	$request['dry_run'] = false;
	$request['expected_hash'] = $preview['expected_hash'];
	$result = (new KreaImmoApi())->manageRecord($resource, $request);
	qaAssert(in_array($result['status'], array('confirmed', 'unchanged'), true), 'Management did not confirm native execution');
	if ($result['after'] !== null) {
		$expected = $preview['after'];
		$expected['id'] = $result['record_id'];
		if (array_key_exists('ref', $expected) && $expected['ref'] === null) {
			$expected['ref'] = $result['after']['ref'];
		}
		if ($request['operation'] === 'post_bank') {
			$expected['fk_bank'] = $result['after']['fk_bank'];
		}
		qaAssert($expected === $result['after'], 'Native result differs from preview for '.$resource.': '.json_encode(array('expected' => $expected, 'actual' => $result['after'])));
	}
	qaAssert($preview['effects'] === $result['effects'], 'Related receipt effect differs from preview');
	return $result;
}

/** Management checks use the same disposable transaction and native fixtures. */
function qaManagementChecks()
{
	global $db, $conf, $user, $fixtures, $run, $now;
	require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';
	$conf->entity = 2;
	DolibarrApiAccess::$user = qaUser();
	qaQuery('SAVEPOINT kreaimmo_management_checks');
	try {
		$parent = $fixtures[2];
		$fields = array(
			'owners' => array('firstname' => 'Synthetic', 'lastname' => 'Management synthetic owner', 'email' => 'management@example.invalid', 'ref' => $run.'-MO'),
			'buildings' => array('label' => 'Management synthetic building', 'fk_property' => (int) $parent['property']->id),
			'properties' => array('label' => 'Management synthetic property', 'property_type_id' => (int) $parent['property']->property_type_id, 'fk_owner' => (int) $parent['owner']->id, 'datebuilt' => 1, 'target' => 1),
			'renters' => array('lastname' => 'Management synthetic renter', 'ref' => $run.'-MR'),
			'leases' => array('fk_property' => (int) $parent['property']->id, 'fk_owner' => (int) $parent['owner']->id, 'fk_renter' => (int) $parent['renter']->id, 'rentamount' => '100.00', 'chargesamount' => '0.00', 'date_start' => '2026-11-01'),
			'receipts' => array('fk_rent' => (int) $parent['rent']->id, 'label' => 'Management synthetic receipt', 'ref' => $run.'-RC', 'rentamount' => '100.00', 'chargesamount' => '0.00', 'date_start' => '2026-11-01', 'date_end' => '2026-11-30', 'date_echeance' => '2026-11-20'),
			'costs' => array('label' => 'Management synthetic cost', 'ref' => $run.'-MC', 'fk_property' => (int) $parent['property']->id, 'amount' => '100.00', 'amount_ht' => '100.00', 'amount_vat' => '0.00', 'date_start' => '2026-11-01', 'date_end' => '2026-11-30'),
			'meters' => array('label' => 'Management synthetic meter', 'compteur_type_id' => (int) $parent['compteur']->compteur_type_id, 'date_relever' => '2026-11-20', 'qty' => '10.5', 'fk_immoproperty' => (int) $parent['property']->id),
		);
		foreach ($fields as $resource => $input) {
			qaCheck('management '.$resource.' previews, creates, updates and deletes natively', function () use ($resource, $input) {
				DolibarrApiAccess::$user = qaUser();
				$result = qaManagementApply($resource, array('operation' => 'create', 'fields' => $input));
				$id = $result['record_id'];
				$update = in_array($resource, array('owners', 'renters'), true) ? array('lastname' => 'Management edited') : ($resource === 'leases' ? array('rentamount' => '150.00') : array('label' => 'Management edited'));
				if ($resource === 'receipts') {
					$update['rentamount'] = '150.00';
				}
				$result = qaManagementApply($resource, array('operation' => 'update', 'record_id' => $id, 'fields' => $update));
				if ($resource === 'receipts') {
					qaAssert($result['after']['balance'] === '150.00000000' && $result['after']['partial_payment'] === null && $result['after']['paye'] === 0, 'Unpaid edited receipt did not recompute its native summary');
				}
				qaManagementApply($resource, array('operation' => 'delete', 'record_id' => $id, 'fields' => array()));
				qaRestError(404, function () use ($resource, $id) { (new KreaImmoApi())->getRecord($resource, $id); });
			});
		}
		qaCheck('management stale previews, replays, permission and field guards fail closed', function () use ($fixtures) {
			DolibarrApiAccess::$user = qaUser();
			$request = array('operation' => 'create', 'fields' => array('lastname' => 'Guarded synthetic'), 'confirm' => true, 'dry_run' => true);
			$preview = (new KreaImmoApi())->manageRecord('renters', $request);
			$request['dry_run'] = false;
			$request['expected_hash'] = $preview['expected_hash'];
			$result = (new KreaImmoApi())->manageRecord('renters', $request);
			qaRestError(409, function () use ($request) { (new KreaImmoApi())->manageRecord('renters', $request); });
			$changed = $request;
			$changed['fields']['lastname'] = 'Changed binding';
			qaRestError(409, function () use ($changed) { (new KreaImmoApi())->manageRecord('renters', $changed); });
			qaRestError(400, function () { (new KreaImmoApi())->manageRecord('renters', array('operation' => 'create', 'fields' => array('lastname' => 'Denied', 'entity' => 3), 'confirm' => true)); });
			qaRestError(400, function () { (new KreaImmoApi())->manageRecord('payments', array('operation' => 'create', 'fields' => array('amount' => '0.001'), 'confirm' => true)); });
			qaRestError(404, function () use ($fixtures) { (new KreaImmoApi())->manageRecord('owners', array('operation' => 'update', 'record_id' => (int) $fixtures[3]['owner']->id, 'fields' => array('lastname' => 'Denied'), 'confirm' => true)); });
			qaRestError(409, function () use ($fixtures, $result) { (new KreaImmoApi())->manageRecord('leases', array('operation' => 'update', 'record_id' => (int) $fixtures[2]['rent']->id, 'fields' => array('fk_renter' => (int) $result['record_id']), 'confirm' => true)); });
			DolibarrApiAccess::$user = qaUser(1, 0, 0);
			qaRestError(403, function () { (new KreaImmoApi())->manageRecord('renters', array('operation' => 'create', 'fields' => array('lastname' => 'Denied'), 'confirm' => true)); });
			DolibarrApiAccess::$user = qaUser();
			qaManagementApply('renters', array('operation' => 'delete', 'record_id' => $result['record_id'], 'fields' => array()));
		});
		qaCheck('management payments maintain receipt summaries and explicit native bank lifecycle', function () use ($parent, $run, $now) {
			global $db, $user;
			DolibarrApiAccess::$user = qaUser();
			DolibarrApiAccess::$user->rights->banque = (object) array('lire' => 1, 'modifier' => 1);
			qaQuery("INSERT INTO ".MAIN_DB_PREFIX."c_paiement(entity,code,libelle,type,active) VALUES (2,'QA_MGT','Synthetic management transfer',2,1)");
			$methodId = (int) $db->last_insert_id(MAIN_DB_PREFIX.'c_paiement');
			$receiptFields = array('fk_rent' => (int) $parent['rent']->id, 'label' => 'Management payment receipt', 'ref' => $run.'-PAY', 'rentamount' => '100.00', 'chargesamount' => '0.00', 'date_start' => '2026-11-01', 'date_end' => '2026-11-30', 'date_echeance' => '2026-11-20');
			$receipt = qaManagementApply('receipts', array('operation' => 'create', 'fields' => $receiptFields));
			$receiptId = $receipt['record_id'];
			qaManagementApply('receipts', array('operation' => 'validate', 'record_id' => $receiptId, 'fields' => array()));
			$paymentFields = array('fk_receipt' => $receiptId, 'amount' => '25.00', 'date_payment' => '2026-11-20', 'fk_mode_reglement' => $methodId, 'num_payment' => 'Synthetic management');
			$payment = qaManagementApply('payments', array('operation' => 'create', 'fields' => $paymentFields));
			$id = $payment['record_id'];
			qaAssert($payment['effects'][0]['after']['balance'] === '75.00000000' && $payment['effects'][0]['after']['paye'] === 2, 'Partial payment summary is incorrect');
			$payment = qaManagementApply('payments', array('operation' => 'update', 'record_id' => $id, 'fields' => array('amount' => '100.00')));
			qaAssert($payment['effects'][0]['after']['balance'] === null && $payment['effects'][0]['after']['paye'] === 1, 'Paid receipt summary is incorrect');
			qaRestError(409, function () use ($paymentFields) { (new KreaImmoApi())->manageRecord('payments', array('operation' => 'create', 'fields' => $paymentFields, 'confirm' => true)); });
			$account = new Account($db);
			$account->ref = substr($run, 0, 8).'-MGT';
			$account->label = 'Synthetic management bank';
			$account->country_id = 1;
			$account->currency_code = 'EUR';
			$account->date_solde = $now;
			$account->balance = 0;
			$account->type = Account::TYPE_CURRENT;
			$account->clos = 0;
			$account->status = 0;
			qaAssert($account->create($user, true) > 0, 'Cannot create synthetic management bank account: '.$account->error);
			$posted = qaManagementApply('payments', array('operation' => 'post_bank', 'record_id' => $id, 'fields' => array('fk_account' => (int) $account->id)));
			qaAssert($posted['after']['fk_account'] === (int) $account->id && $posted['after']['fk_bank'] > 0, 'Explicit bank posting did not retain distinct account/line IDs');
			qaRestError(409, function () use ($id) { (new KreaImmoApi())->manageRecord('payments', array('operation' => 'update', 'record_id' => $id, 'fields' => array('amount' => '20.00'), 'confirm' => true)); });
			qaQuery('UPDATE '.MAIN_DB_PREFIX.'bank SET rappro=1 WHERE rowid='.(int) $posted['after']['fk_bank']);
			qaRestError(409, function () use ($id) { (new KreaImmoApi())->manageRecord('payments', array('operation' => 'delete', 'record_id' => $id, 'fields' => array(), 'confirm' => true)); });
			qaQuery('UPDATE '.MAIN_DB_PREFIX.'bank SET rappro=0 WHERE rowid='.(int) $posted['after']['fk_bank']);
			$deleted = qaManagementApply('payments', array('operation' => 'delete', 'record_id' => $id, 'fields' => array()));
			qaAssert($deleted['effects'][0]['after']['partial_payment'] === null && $deleted['effects'][0]['after']['balance'] === '100.00000000' && $deleted['effects'][0]['after']['paye'] === 0, 'Payment deletion did not restore unpaid native summary');
			qaAssert($db->num_rows(qaQuery('SELECT rowid FROM '.MAIN_DB_PREFIX.'bank WHERE rowid='.(int) $posted['after']['fk_bank'])) === 0, 'Native payment deletion left bank evidence behind');
		});
		qaCheck('record API and management preserve restricted commercial assignments', function () use ($parent) {
			global $db;
			$id = (int) $parent['renter']->id;
			$record = (new KreaImmoApi())->getRecord('renters', $id)['record'];
			$companyId = (int) $record['fk_soc'];
			qaAssert($companyId > 0, 'Synthetic renter lacks its existing third-party fixture');
			DolibarrApiAccess::$user = qaUser();
			DolibarrApiAccess::$user->rights->societe->client->voir = 0;
			$api = new KreaImmoApi();
			qaRestError(404, function () use ($api, $id) { $api->getRecord('renters', $id); });
			qaRestError(404, function () use ($api, $id) { $api->manageRecord('renters', array('operation' => 'update', 'record_id' => $id, 'fields' => array('lastname' => 'Denied'), 'confirm' => true)); });
			qaRestError(403, function () use ($api, $companyId) { $api->manageRecord('renters', array('operation' => 'create', 'fields' => array('lastname' => 'Denied', 'fk_soc' => $companyId), 'confirm' => true)); });
			qaAssert($api->getRecords('renters', 100, 0, $record['ref'])['items'] === array(), 'Unassigned renter leaked through a list');
			qaQuery('INSERT INTO '.MAIN_DB_PREFIX.'societe_commerciaux(fk_soc,fk_user) VALUES ('.$companyId.',1)');
			try {
				qaAssert($api->getRecord('renters', $id)['record']['id'] === $id, 'Assigned commercial reader cannot read renter');
				qaAssert(count($api->getRecords('renters', 100, 0, $record['ref'])['items']) === 1, 'Assigned commercial reader cannot list renter');
				$preview = $api->manageRecord('renters', array('operation' => 'update', 'record_id' => $id, 'fields' => array('lastname' => 'Assigned synthetic'), 'confirm' => true));
				qaAssert($preview['after']['lastname'] === 'Assigned synthetic', 'Assigned commercial reader cannot preview own renter edit');
			} finally {
				qaQuery('DELETE FROM '.MAIN_DB_PREFIX.'societe_commerciaux WHERE fk_soc='.$companyId.' AND fk_user=1');
				DolibarrApiAccess::$user = qaUser();
			}
		});
		qaCheck('management renter cancellation and reopening retain native identity', function () {
			DolibarrApiAccess::$user = qaUser();
			$renter = qaManagementApply('renters', array('operation' => 'create', 'fields' => array('lastname' => 'Lifecycle synthetic')));
			$id = $renter['record_id'];
			qaRestError(409, function () use ($id) { (new KreaImmoApi())->manageRecord('renters', array('operation' => 'set_status', 'record_id' => $id, 'fields' => array('status' => 9), 'confirm' => true)); });
			$active = qaManagementApply('renters', array('operation' => 'set_status', 'record_id' => $id, 'fields' => array('status' => 1)));
			$closed = qaManagementApply('renters', array('operation' => 'set_status', 'record_id' => $id, 'fields' => array('status' => 9)));
			$reopened = qaManagementApply('renters', array('operation' => 'set_status', 'record_id' => $id, 'fields' => array('status' => 1)));
			qaAssert($closed['after']['status'] === 9 && $reopened['after']['status'] === 1 && $reopened['after']['ref'] === $active['after']['ref'], 'Native renter reopen renumbered or failed its state transition');
			qaManagementApply('renters', array('operation' => 'delete', 'record_id' => $id, 'fields' => array()));
		});
		qaCheck('management protects canonical company files, ref changes and suffixed ECM records', function () use ($parent, $run) {
			global $conf, $db;
			DolibarrApiAccess::$user = qaUser();
			$originalDirectories = isset($conf->kreaimmo->multidir_output) ? $conf->kreaimmo->multidir_output : null;
			$directory = DOL_DATA_ROOT.'/management-files-'.$run;
			$files = array();
			try {
				$conf->kreaimmo->multidir_output[2] = $directory;
				$owner = qaManagementApply('owners', array('operation' => 'create', 'fields' => array('firstname' => 'Synthetic', 'lastname' => 'Document owner', 'email' => 'document@example.invalid', 'ref' => $run.'-DOC')));
				$ownerFile = $directory.'/owner/'.$owner['after']['ref'].'/synthetic.txt';
				dol_mkdir(dirname($ownerFile));
				qaAssert(file_put_contents($ownerFile, 'Synthetic attachment preservation fixture') !== false, 'Cannot prepare synthetic owner attachment');
				$files[] = $ownerFile;
				$ownerId = $owner['record_id'];
				qaRestError(409, function () use ($ownerId) { (new KreaImmoApi())->manageRecord('owners', array('operation' => 'delete', 'record_id' => $ownerId, 'fields' => array(), 'confirm' => true)); });
				qaRestError(409, function () use ($ownerId, $run) { (new KreaImmoApi())->manageRecord('owners', array('operation' => 'update', 'record_id' => $ownerId, 'fields' => array('ref' => $run.'-MOVED'), 'confirm' => true)); });
				qaAssert((new KreaImmoApi())->getRecord('owners', $ownerId)['record']['ref'] === $owner['after']['ref'] && is_file($ownerFile), 'Denied ref edit changed owner identity or attachment');
				$receipt = qaManagementApply('receipts', array('operation' => 'create', 'fields' => array('fk_rent' => (int) $parent['rent']->id, 'label' => 'Document receipt', 'ref' => $run.'-RCDOC', 'rentamount' => '100.00', 'chargesamount' => '0.00', 'date_start' => '2026-11-01', 'date_end' => '2026-11-30', 'date_echeance' => '2026-11-20')));
				$receiptFile = $directory.'/receipt/'.$receipt['after']['ref'].'/synthetic.txt';
				dol_mkdir(dirname($receiptFile));
				qaAssert(file_put_contents($receiptFile, 'Synthetic receipt attachment') !== false, 'Cannot prepare synthetic receipt attachment');
				$files[] = $receiptFile;
				$receiptId = $receipt['record_id'];
				qaRestError(409, function () use ($receiptId) { (new KreaImmoApi())->manageRecord('receipts', array('operation' => 'validate', 'record_id' => $receiptId, 'fields' => array(), 'confirm' => true)); });
				qaAssert((new KreaImmoApi())->getRecord('receipts', $receiptId)['record'] === $receipt['after'] && is_file($receiptFile) && file_get_contents($receiptFile) === 'Synthetic receipt attachment', 'Document-bearing receipt validation changed state or files');
				$building = qaManagementApply('buildings', array('operation' => 'create', 'fields' => array('label' => 'Document building', 'fk_property' => (int) $parent['property']->id)));
				$buildingId = $building['record_id'];
				qaQuery("INSERT INTO ".MAIN_DB_PREFIX."ecm_files(label,entity,filepath,filename,src_object_type,src_object_id) VALUES ('Synthetic building attachment',2,'kreaimmo/building','synthetic.txt','kreaimmo_building@kreaimmo',".$buildingId.')');
				qaRestError(409, function () use ($buildingId) { (new KreaImmoApi())->manageRecord('buildings', array('operation' => 'delete', 'record_id' => $buildingId, 'fields' => array(), 'confirm' => true)); });
				qaAssert((new KreaImmoApi())->getRecord('buildings', $buildingId)['record']['id'] === $buildingId, 'Suffixed ECM attachment failed to protect building');
			} finally {
				foreach ($files as $file) {
					dol_delete_file($file);
				}
				if ($originalDirectories === null) {
					unset($conf->kreaimmo->multidir_output);
				} else {
					$conf->kreaimmo->multidir_output = $originalDirectories;
				}
				DolibarrApiAccess::$user = qaUser();
			}
		});
		qaCheck('native validation preserves another company document root', function () use ($parent, $run) {
			global $conf;
			DolibarrApiAccess::$user = qaUser();
			$originalOutput = $conf->kreaimmo->dir_output;
			$originalDirectories = isset($conf->kreaimmo->multidir_output) ? $conf->kreaimmo->multidir_output : null;
			$selectedRoot = DOL_DATA_ROOT.'/validation-selected-'.$run;
			$otherRoot = DOL_DATA_ROOT.'/validation-other-'.$run;
			$files = array();
			try {
				$conf->kreaimmo->dir_output = $otherRoot;
				$conf->kreaimmo->multidir_output[2] = $selectedRoot;
				$receipt = qaManagementApply('receipts', array('operation' => 'create', 'fields' => array('fk_rent' => (int) $parent['rent']->id, 'label' => 'Native root receipt', 'rentamount' => '100.00', 'chargesamount' => '0.00', 'date_start' => '2026-11-01', 'date_end' => '2026-11-30', 'date_echeance' => '2026-11-20')));
				$receiptSentinel = $otherRoot.'/immoreceipt/'.dol_sanitizeFileName($receipt['after']['ref']).'/sentinel.txt';
				dol_mkdir(dirname($receiptSentinel));
				qaAssert(file_put_contents($receiptSentinel, 'Synthetic other-company receipt') !== false, 'Cannot create receipt root sentinel');
				$files[] = $receiptSentinel;
				qaManagementApply('receipts', array('operation' => 'validate', 'record_id' => $receipt['record_id'], 'fields' => array()));
				qaAssert(is_file($receiptSentinel) && file_get_contents($receiptSentinel) === 'Synthetic other-company receipt', 'Receipt validation touched another company document root');
				$renter = qaManagementApply('renters', array('operation' => 'create', 'fields' => array('lastname' => 'Native root renter')));
				$renterSentinel = $otherRoot.'/immorenter/'.dol_sanitizeFileName($renter['after']['ref']).'/sentinel.txt';
				dol_mkdir(dirname($renterSentinel));
				qaAssert(file_put_contents($renterSentinel, 'Synthetic other-company renter') !== false, 'Cannot create renter root sentinel');
				$files[] = $renterSentinel;
				qaManagementApply('renters', array('operation' => 'set_status', 'record_id' => $renter['record_id'], 'fields' => array('status' => 1)));
				qaAssert(is_file($renterSentinel) && file_get_contents($renterSentinel) === 'Synthetic other-company renter', 'Renter validation touched another company document root');
			} finally {
				foreach ($files as $file) {
					dol_delete_file($file);
				}
				$conf->kreaimmo->dir_output = $originalOutput;
				if ($originalDirectories === null) {
					unset($conf->kreaimmo->multidir_output);
				} else {
					$conf->kreaimmo->multidir_output = $originalDirectories;
				}
				DolibarrApiAccess::$user = qaUser();
			}
		});
	} finally {
		qaQuery('ROLLBACK TO SAVEPOINT kreaimmo_management_checks');
		qaQuery('RELEASE SAVEPOINT kreaimmo_management_checks');
		DolibarrApiAccess::$user = qaUser();
	}
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
qaCheck('native Restler validates omitted and typed optional query parameters', function () {
	$restler = new Luracast\Restler\Restler(false);
	$restler->responseFormat = new Luracast\Restler\Format\JsonFormat();
	Luracast\Restler\Routes::addAPIClass('KreaImmoApi', Luracast\Restler\Util::getResourcePath('KreaImmoApi', null), 1);
	$cases = array(
		array('kreaimmoapi/records/renters', array(), array(0 => 'renters', 1 => 100, 2 => 0, 3 => null, 4 => null, 5 => null, 6 => null, 7 => null)),
		array('kreaimmoapi/records/payments', array('limit' => '2', 'page' => '0', 'ref' => 'QA-EXACT', 'property_id' => '7', 'renter_id' => '8', 'lease_id' => '9', 'owner_id' => '10'), array(0 => 'payments', 1 => 2, 2 => 0, 3 => 'QA-EXACT', 4 => 7, 5 => 8, 6 => 9, 7 => 10)),
	);
	foreach ($cases as $case) {
		$route = Luracast\Restler\Routes::find($case[0], 'GET', 1, $case[1]);
		qaAssert($route->className === 'KreaImmoApi' && $route->methodName === 'getRecords' && $route->accessLevel >= 2, 'Protected native route metadata changed');
		foreach ($route->metadata['param'] as $index => $parameter) {
			$info = new Luracast\Restler\Data\ValidationInfo($parameter);
			$actual = Luracast\Restler\Data\Validator::validate($route->parameters[$index], $info);
			qaAssert($actual === $case[2][$index], 'Native request validation changed an optional or typed query parameter');
		}
	}
});
qaCheck('native Restler serializes new API return metadata for Swagger', function () {
	$explorer = new Luracast\Restler\Explorer();
	$responses = new ReflectionMethod($explorer, 'responses');
	$responses->setAccessible(true);
	foreach (array(array('kreaimmoapi/records/renters', 'GET'), array('kreaimmoapi/records/renters/17', 'GET'), array('kreaimmoapi/manage/renters', 'POST')) as $case) {
		$route = Luracast\Restler\Routes::find($case[0], $case[1], 1, array());
		qaAssert($route->metadata['return']['type'] === 'array', 'API public return type is not compatible with native Restler');
		$schema = $responses->invoke($explorer, array('metadata' => $route->metadata));
		qaAssert($schema['200']->schema->type === 'array' && json_encode($schema) !== false, 'Native Swagger return schema failed serialization');
	}
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
qaCheck('native mailing discovery instantiates the adapter while inactive', function () {
	global $db, $conf, $user;
	$directory = dirname(__DIR__, 2).'/core/modules/mailings';
	qaAssert(is_file($directory.'/mailinglist_kreaimmo_immorenter.modules.php'), 'Expected discoverable mailing adapter is missing');
	qaAssert(!is_file($directory.'/mailinglist_immobilier_immorenter.modules.php'), 'Legacy filename still exposes a mismatched mailing class');
	$modules = $conf->modules;
	$actor = $user;
	$entity = $conf->entity;
	try {
		unset($conf->modules['kreaimmo'], $conf->modules['mailing']);
		foreach (glob($directory.'/*.modules.php') as $file) {
			$class = 'mailing_'.basename($file, '.modules.php');
			require_once $file;
			qaAssert(class_exists($class, false), 'Native filename-to-class discovery cannot load '.$class);
			foreach (array(2, 3) as $company) {
				$conf->entity = $company;
				$user = qaMailingActor();
				$selector = new $class($db);
				qaAssert($selector instanceof MailingTargets && !$selector->enabled, 'Inactive selector discovery failed');
			}
		}
	} finally {
		$conf->modules = $modules;
		$conf->entity = $entity;
		$user = $actor;
	}
});

$baseline = array();
foreach (array('immoowner', 'immoproperty', 'immorenter', 'immorent', 'immoreceipt', 'immocost', 'immocompteur', 'immopayment') as $table) {
	$result = qaQuery('SELECT COUNT(*) AS total FROM '.MAIN_DB_PREFIX.'kreaimmo_'.$table);
	$baseline[$table] = (int) $db->fetch_object($result)->total;
}
$mailingBaseline = array();
foreach (array('mailing', 'mailing_cibles') as $table) {
	$result = qaQuery('SELECT COUNT(*) AS total FROM '.MAIN_DB_PREFIX.$table);
	$mailingBaseline[$table] = (int) $db->fetch_object($result)->total;
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
		qaCheck('payment queries retain property renter and receipt display labels', function () {
			global $db, $fixtures;
			$payments = new KreaImmoPayment($db);
			qaAssert($payments->fetchAll('ASC', 't.rowid', 100) > 0, 'Cannot list synthetic payments');
			$matching = null;
			foreach ($payments->lines as $line) {
				if ((int) $line->rowid === (int) $fixtures[2]['payment']->id) {
					$matching = $line;
				}
			}
			qaAssert($matching !== null, 'Synthetic payment is absent from its list');
			qaAssert($matching->property_label === $fixtures[2]['property']->label, 'Payment list lost the property label');
			qaAssert($matching->renter_name === $fixtures[2]['renter']->lastname, 'Payment list lost the renter name');
			qaAssert($matching->receipt_label === $fixtures[2]['receipt']->label, 'Payment list lost the receipt label');
			$linked = new KreaImmoPayment($db);
			qaAssert($linked->fetch_by_loyer($fixtures[2]['receipt']->id) > 0, 'Cannot fetch payment through its receipt');
			qaAssert($linked->property_label === $matching->property_label && $linked->renter_name === $matching->renter_name && $linked->receipt_label === $matching->receipt_label, 'Receipt-linked payment query lost display labels');
			$foreign = new KreaImmoPayment($db);
			qaAssert($foreign->fetch_by_loyer($fixtures[3]['receipt']->id) === 0, 'Receipt-linked payment query exposed another company');
		});
		qaCheck('property receipt queries retain display labels and company isolation', function () {
			global $db, $fixtures;
			$receipts = new KreaImmoReceipt($db);
			qaAssert($receipts->fetchByLocalId($fixtures[2]['property']->id) > 0, 'Cannot list receipts for the synthetic property');
			$matching = null;
			foreach ($receipts->lines as $line) {
				if ((int) $line->id === (int) $fixtures[2]['receipt']->id) {
					$matching = $line;
				}
			}
			qaAssert($matching !== null, 'Synthetic receipt is absent from its property list');
			qaAssert($matching->property_label === $fixtures[2]['property']->label, 'Property receipt query lost the property label');
			qaAssert($matching->renter_name === $fixtures[2]['renter']->firstname, 'Property receipt query lost the renter name');
			qaAssert($matching->label === $fixtures[2]['receipt']->label, 'Property receipt query lost the receipt label');
			$foreign = new KreaImmoReceipt($db);
			qaAssert($foreign->fetchByLocalId($fixtures[3]['property']->id) === 0 && $foreign->lines === array(), 'Property receipt query exposed another company');
		});
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
		qaMailingChecks();
		$aux = array();
		qaCheck('auxiliary object chains create in both companies', function () use (&$aux) {
			$aux[2] = qaAuxChain(2);
			$aux[3] = qaAuxChain(3);
		});
		$conf->entity = 2;
		$user = qaUser();
		if (isset($aux[2], $aux[3])) {
			qaRecordApiChecks($aux);
			qaManagementChecks();
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
qaCheck('mailing fixture rollback preserves original draft and recipient counts', function () use ($mailingBaseline) {
	global $db;
	foreach ($mailingBaseline as $table => $expected) {
		$result = qaQuery('SELECT COUNT(*) AS total FROM '.MAIN_DB_PREFIX.$table);
		qaAssert((int) $db->fetch_object($result)->total === $expected, 'Mailing fixture cleanup changed '.$table.' row count');
	}
});

$failed = count(array_filter($results, function ($result) { return $result['status'] === 'FAIL'; }));
echo json_encode(array('database' => $db->database_name, 'php' => PHP_VERSION, 'dolibarr' => DOL_VERSION, 'bootstrap' => isset($kreaimmoIntegrationBootstrap) ? $kreaimmoIntegrationBootstrap : null, 'checks' => $results, 'passed' => count($results) - $failed, 'failed' => $failed), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
exit($failed ? 1 : 0);
