<?php
/* Copyright (C) 2026 Kreativität Works <mail@kreativitat.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/**
 * Disposable CLI NDJSON bridge for cross-language contract acceptance.
 * This invokes real API classes; it is not Dolibarr's HTTP authentication middleware.
 */
if (PHP_SAPI !== 'cli' || getenv('KREAIMMO_TEST_ALLOW_WRITES') !== '1') {
	fwrite(STDERR, "Disposable CLI bridge requires KREAIMMO_TEST_ALLOW_WRITES=1.\n");
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
require __DIR__.'/bootstrap.php';
if (!isset($db) || !($db instanceof DoliDB) || $db->database_name !== 'kreaimmo_clean' || strpos(DOL_DATA_ROOT, '/tmp/kreaimmo-') !== 0) {
	throw new RuntimeException('Refusing a non-disposable database or document directory');
}
require_once DOL_DOCUMENT_ROOT.'/api/class/api_access.class.php';
require_once DOL_DOCUMENT_ROOT.'/api/class/api.class.php';
require_once dirname(__DIR__, 2).'/class/api_kreaimmo.class.php';
require_once dirname(__DIR__, 2).'/class/kreaimmoproperty_type.class.php';
require_once dirname(__DIR__, 2).'/class/kreaimmocompteur_type.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';

/** @param string $profile Synthetic permission profile @return User */
function bridgeActor($profile = 'full')
{
	global $db, $conf;
	$actor = new User($db);
	$actor->id = 1;
	$actor->entity = (int) $conf->entity;
	$actor->login = 'synthetic_contract';
	$actor->admin = 0;
	$actor->rights = new stdClass();
	$actor->rights->kreaimmo = new stdClass();
	foreach (array('owner', 'property', 'renter', 'rent', 'receipt', 'cost', 'immocompteur', 'payment') as $domain) {
		$actor->rights->kreaimmo->$domain = (object) array('read' => 1, 'write' => $profile === 'read_only' ? 0 : 1, 'delete' => $profile === 'read_only' ? 0 : 1);
	}
	$actor->rights->societe = (object) array('client' => (object) array('voir' => $profile === 'restricted' ? 0 : 1));
	$actor->rights->banque = (object) array('lire' => 1, 'modifier' => $profile === 'no_bank' ? 0 : 1);
	if ($profile === 'external') {
		$actor->socid = $actor->societe_id = 123456;
	}
	return $actor;
}

/** @param object $object Native object @return int Created ID */
function bridgeCreate($object)
{
	global $user;
	if ($object->create($user, true) <= 0) {
		throw new RuntimeException('Synthetic '.get_class($object).' fixture failed: '.$object->error);
	}
	return (int) $object->id;
}

/** @param array<string,mixed> $value NDJSON response @return void */
function bridgeEmit(array $value)
{
	fwrite(STDOUT, json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");
	fflush(STDOUT);
}

$conf->modules['kreaimmo'] = 'kreaimmo';
$conf->kreaimmo->enabled = 1;
$conf->global->MAIN_MODULE_KREAIMMO = 1;
$conf->entity = 2;
$user = bridgeActor();
DolibarrApiAccess::$user = $user;
$prefix = 'BR'.bin2hex(random_bytes(5));
$baseline = array();
foreach (array('kreaimmo_immoowner', 'kreaimmo_building', 'kreaimmo_immoproperty', 'kreaimmo_immorenter', 'kreaimmo_immorent', 'kreaimmo_immoreceipt', 'kreaimmo_immopayment', 'kreaimmo_immocost', 'kreaimmo_immocompteur', 'c_kreaimmo_immoproperty_type', 'c_kreaimmo_immocompteur_type', 'c_paiement', 'bank_account', 'bank', 'bank_url') as $table) {
	$baseline[$table] = (int) $db->fetch_object(kreaimmoFixtureQuery('SELECT COUNT(*) AS total FROM '.MAIN_DB_PREFIX.$table))->total;
}
$db->begin('Synthetic MCP contract bridge');
try {
	$type = new KreaImmoProperty_Type($db);
	$type->entity = 2;
	$type->code = $prefix;
	$type->label = 'Synthetic contract property type';
	$type->active = 1;
	$propertyType = bridgeCreate($type);
	$meter = new KreaImmoCompteur_Type($db);
	$meter->entity = 2;
	$meter->label = 'Synthetic contract meter type';
	$meter->active = 1;
	$meterType = bridgeCreate($meter);
	$account = new Account($db);
	$account->ref = $prefix;
	$account->label = 'Synthetic contract account';
	$account->country_id = 1;
	$account->currency_code = 'EUR';
	$account->date_solde = dol_mktime(12, 0, 0, 11, 21, 2026);
	$account->balance = 0;
	$account->type = Account::TYPE_CURRENT;
	$account->clos = 0;
	$account->status = 0;
	$accountId = bridgeCreate($account);
	kreaimmoFixtureQuery("INSERT INTO ".MAIN_DB_PREFIX."c_paiement(entity,code,libelle,type,active) VALUES (2,'BRVIR','Synthetic contract transfer',2,1)");
	$paymentMethod = (int) $db->last_insert_id(MAIN_DB_PREFIX.'c_paiement');
	bridgeEmit(array('ready' => true, 'database' => $db->database_name, 'entity_id' => 2, 'currency' => 'EUR',
		'fixtures' => array('property_type_id' => $propertyType, 'compteur_type_id' => $meterType, 'fk_account' => $accountId, 'fk_mode_reglement' => $paymentMethod)));
	while (($line = fgets(STDIN)) !== false) {
		try {
			$request = json_decode($line, true, 32, JSON_THROW_ON_ERROR);
			if (!is_array($request)) {
				throw new Luracast\Restler\RestException(400, 'Expected a bridge request object');
			}
			$entity = isset($request['entity_id']) ? $request['entity_id'] : 2;
			if (!in_array($entity, array(2, 3), true)) {
				throw new Luracast\Restler\RestException(403, 'Synthetic company is outside the fixture');
			}
			$conf->entity = $entity;
			$user = bridgeActor(isset($request['actor']) ? $request['actor'] : 'full');
			DolibarrApiAccess::$user = $user;
			$api = new KreaImmoApi();
			$path = isset($request['path']) ? $request['path'] : '';
			$method = isset($request['method']) ? $request['method'] : '';
			if ($method === 'GET' && preg_match('#^kreaimmoapi/records/([a-z]+)(?:/([1-9][0-9]*))?$#D', $path, $parts)) {
				if (isset($parts[2])) {
					$result = $api->getRecord($parts[1], (int) $parts[2]);
				} else {
					$params = isset($request['params']) ? $request['params'] : array();
					$result = $api->getRecords($parts[1], isset($params['limit']) ? $params['limit'] : 100,
						isset($params['page']) ? $params['page'] : 0, isset($params['ref']) ? $params['ref'] : null,
						isset($params['property_id']) ? $params['property_id'] : null, isset($params['renter_id']) ? $params['renter_id'] : null,
						isset($params['lease_id']) ? $params['lease_id'] : null, isset($params['owner_id']) ? $params['owner_id'] : null);
				}
			} elseif ($method === 'POST' && preg_match('#^kreaimmoapi/manage/([a-z]+)$#D', $path, $parts)) {
				$result = $api->manageRecord($parts[1], isset($request['body']) ? $request['body'] : null);
			} else {
				throw new Luracast\Restler\RestException(404, 'Unsupported disposable bridge route');
			}
			bridgeEmit(array('status' => 200, 'body' => $result));
		} catch (Luracast\Restler\RestException $error) {
			bridgeEmit(array('status' => (int) $error->getCode(), 'body' => array('error' => array('message' => $error->getMessage()))));
		} catch (Throwable $error) {
			bridgeEmit(array('status' => 500, 'body' => array('error' => array('message' => get_class($error).': '.$error->getMessage()))));
		}
	}
} finally {
	while ($db->transaction_opened > 0) {
		$db->rollback('Synthetic MCP bridge cleanup');
	}
	foreach ($baseline as $table => $count) {
		$current = (int) $db->fetch_object(kreaimmoFixtureQuery('SELECT COUNT(*) AS total FROM '.MAIN_DB_PREFIX.$table))->total;
		if ($current !== $count) {
			throw new RuntimeException('Bridge rollback changed '.$table.' row count');
		}
	}
}
