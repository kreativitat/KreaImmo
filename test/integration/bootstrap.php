<?php
/* Copyright (C) 2026 Kreativität Works <mail@kreativitat.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/** Synthetic Dolibarr fixture. Never loads an installation's conf.php or business data. */
if (PHP_SAPI !== 'cli' || getenv('KREAIMMO_TEST_ALLOW_WRITES') !== '1') {
	throw new RuntimeException('Disposable CLI fixture requires KREAIMMO_TEST_ALLOW_WRITES=1');
}
$coreRoot = realpath(getenv('KREAIMMO_TEST_DOL_DOCUMENT_ROOT') ?: dirname(__DIR__, 4));
if (!$coreRoot || !is_file($coreRoot.'/version.inc.php')) {
	throw new RuntimeException('Set KREAIMMO_TEST_DOL_DOCUMENT_ROOT to the supported Dolibarr source');
}
define('DOL_DOCUMENT_ROOT', $coreRoot);
define('DOL_URL_ROOT', '');
define('DOL_MAIN_URL_ROOT', 'http://localhost');
define('DOL_DATA_ROOT', '/tmp/kreaimmo-integration-documents');
define('MAIN_DB_PREFIX', 'llx_');
define('TCPDF_PATH', DOL_DOCUMENT_ROOT.'/includes/tecnickcom/tcpdf/');
define('TCPDI_PATH', DOL_DOCUMENT_ROOT.'/includes/tcpdi/');
require_once DOL_DOCUMENT_ROOT.'/version.inc.php';
foreach (array('/core/lib/functions.lib.php', '/core/lib/files.lib.php', '/core/class/conf.class.php', '/core/class/translate.class.php', '/core/db/DoliDB.class.php', '/core/db/mysqli.class.php') as $include) {
	require_once DOL_DOCUMENT_ROOT.$include;
}

$conf = new Conf();
$conf->entity = 2;
$conf->db->character_set = 'utf8';
$conf->db->dolibarr_main_db_collation = 'utf8_unicode_ci';
$conf->file->dol_document_root = array('main' => DOL_DOCUMENT_ROOT, 'alt0' => DOL_DOCUMENT_ROOT.'/custom');
$conf->file->dol_url_root = array('main' => '', 'alt0' => '/custom');
$conf->file->dol_main_url_root = DOL_MAIN_URL_ROOT;
$conf->global->MAIN_FEATURES_LEVEL = 2;
$conf->global->MAIN_LANG_DEFAULT = 'en_US';
$conf->global->MAIN_MONNAIE = 'EUR';
$conf->global->MAIN_MAX_DECIMALS_TOT = 2;
$conf->global->MAIN_MAX_DECIMALS_UNIT = 5;
$conf->global->MAIN_MAX_DECIMALS_STOCK = 5;
$conf->global->MAIN_INFO_SOCIETE_NOM = 'KreaImmo synthetic validation';
$conf->global->MAIN_DISPLAY_SQL_INSTALL_LOG = 0;
$conf->currency = 'EUR';
$langs = new Translate(DOL_DOCUMENT_ROOT.'/langs', $conf);
$langs->setDefaultLang('en_US');
dol_mkdir(DOL_DATA_ROOT);

// The empty password is exclusively for an isolated, disposable MariaDB container.
$fixtureHost = getenv('KREAIMMO_TEST_DB_HOST') ?: '127.0.0.1';
$fixturePort = (int) (getenv('KREAIMMO_TEST_DB_PORT') ?: 3306);
$connection = new DoliDBMysqli('mysqli', $fixtureHost, 'root', '', 'mysql', $fixturePort);
if (!$connection->ok || !$connection->query('CREATE DATABASE IF NOT EXISTS kreaimmo_clean CHARACTER SET utf8 COLLATE utf8_unicode_ci')) {
	throw new RuntimeException('Unable to create disposable kreaimmo_clean database');
}
$connection->close();
$db = new DoliDBMysqli('mysqli', $fixtureHost, 'root', '', 'kreaimmo_clean', $fixturePort);
if (!$db->ok || $db->database_name !== 'kreaimmo_clean') {
	throw new RuntimeException('Disposable database connection failed');
}

function kreaimmoFixtureQuery($sql)
{
	global $db;
	$result = $db->query($sql);
	if (!$result) {
		throw new RuntimeException('Fixture SQL failed: '.$db->lasterror());
	}
	return $result;
}

function kreaimmoFixtureAssertModuleTables(array $expectedTables)
{
	global $db;
	$actualTables = array();
	$result = kreaimmoFixtureQuery('SHOW TABLES');
	while ($row = $db->fetch_object($result)) {
		$names = array_values((array) $row);
		$actualTables[$names[0]] = true;
	}
	$missing = array_diff($expectedTables, array_keys($actualTables));
	if ($missing) {
		throw new RuntimeException('Native installation left module tables missing: '.implode(', ', $missing));
	}
	return count($expectedTables);
}

// Native table DDL supplies host prerequisites; module tables/indexes use the native installer.
$coreTables = glob(DOL_DOCUMENT_ROOT.'/install/mysql/tables/llx_*.sql');
$coreCount = 0;
foreach ($coreTables as $file) {
	if (substr($file, -8) === '.key.sql') {
		continue;
	}
	$sql = preg_replace('/--[^\r\n]*/', '', file_get_contents($file));
	$sql = preg_replace('/CREATE TABLE(?! IF NOT EXISTS)/i', 'CREATE TABLE IF NOT EXISTS', $sql);
	kreaimmoFixtureQuery($sql);
	$coreCount++;
}
kreaimmoFixtureQuery("INSERT IGNORE INTO llx_user(rowid,entity,login,lastname,admin,statut) VALUES (1,2,'validation','Synthetic validation',1,1)");

foreach (array('/core/lib/admin.lib.php', '/core/lib/functions2.lib.php', '/core/lib/security.lib.php', '/user/class/user.class.php', '/societe/class/societe.class.php', '/core/class/html.form.class.php', '/core/class/hookmanager.class.php') as $include) {
	require_once DOL_DOCUMENT_ROOT.$include;
}
$user = new User($db);
if ($user->fetch(1) <= 0) {
	throw new RuntimeException('Cannot load synthetic fixture user');
}
$mysoc = new Societe($db);
$mysoc->name = $conf->global->MAIN_INFO_SOCIETE_NOM;
$mysoc->country_code = 'PT';
$mysoc->currency_code = 'EUR';
$hookmanager = new HookManager($db);
$form = new Form($db);
$conf->modules['kreaimmo'] = 'kreaimmo';
$conf->modules['societe'] = 'societe';
$conf->modules['banque'] = 'banque';
$langs->loadLangs(array('main', 'other', 'admin', 'companies', 'kreaimmo@kreaimmo'));

require_once dirname(__DIR__, 2).'/core/modules/modKreaImmo.class.php';
$expectedTables = array();
foreach (glob(dirname(__DIR__, 2).'/sql/llx_*.sql') as $file) {
	$sql = preg_replace('/--[^\r\n]*/', '', file_get_contents($file));
	preg_match_all('/\bCREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?(llx_[a-z0-9_]+)`?/i', $sql, $matches);
	foreach ($matches[1] as $table) {
		$expectedTables[] = $db->prefix().substr($table, 4);
	}
}
$expectedTables = array_values(array_unique($expectedTables));
if (!$expectedTables) {
	throw new RuntimeException('No module CREATE TABLE targets found in sql/llx_*.sql');
}
$module = new modKreaImmo($db);
$beforeInstall = kreaimmoFixtureQuery("SHOW TABLES LIKE 'llx\\_kreaimmo\\_%'");
$cleanInstall = $db->num_rows($beforeInstall) === 0;
if ($module->remove('noboxes') <= 0) {
	throw new RuntimeException('Cannot reset disposable native module activation metadata');
}
$firstInstall = $module->init('noboxes');
if ($firstInstall <= 0) {
	throw new RuntimeException('KreaImmo native module installation failed: '.$module->error.' '.implode('; ', (array) $module->errors).' '.$db->lasterror());
}
kreaimmoFixtureAssertModuleTables($expectedTables);
$sentinelRef = 'SCHEMA-'.bin2hex(random_bytes(8));
kreaimmoFixtureQuery("INSERT INTO llx_kreaimmo_immoowner(ref,entity,date_creation,fk_user_creat,status,firstname,lastname,email) VALUES ('".$db->escape($sentinelRef)."',2,'2026-11-20 12:00:00',1,0,'Synthetic','Schema preservation','schema@example.invalid')");
try {
	$loader = new ReflectionMethod($module, '_load_tables');
	$loader->setAccessible(true);
	$replayInstall = $loader->invoke($module, '/kreaimmo/sql/');
	if ($replayInstall <= 0) {
		throw new RuntimeException('KreaImmo native module installation replay failed');
	}
	kreaimmoFixtureAssertModuleTables($expectedTables);
	$result = kreaimmoFixtureQuery("SELECT lastname FROM llx_kreaimmo_immoowner WHERE ref='".$db->escape($sentinelRef)."'");
	$sentinel = $db->fetch_object($result);
	if (!$sentinel || $sentinel->lastname !== 'Schema preservation') {
		throw new RuntimeException('Native installation replay did not preserve existing synthetic data');
	}
} finally {
	kreaimmoFixtureQuery("DELETE FROM llx_kreaimmo_immoowner WHERE ref='".$db->escape($sentinelRef)."'");
}
$tables = kreaimmoFixtureQuery("SHOW TABLES LIKE 'llx\\_kreaimmo\\_%'");
$version = $db->fetch_object(kreaimmoFixtureQuery('SELECT VERSION() AS version'));
$kreaimmoIntegrationBootstrap = array('core_table_ddl' => $coreCount, 'clean_install' => $cleanInstall, 'module_install' => $firstInstall, 'module_replay' => $replayInstall, 'module_tables' => count($expectedTables), 'business_tables' => $db->num_rows($tables), 'dictionary_tables' => count($expectedTables) - $db->num_rows($tables), 'preserved_existing_record' => true, 'database_server' => $version->version);
$user->getrights('kreaimmo', 1);
$conf->kreaimmo->dir_output = DOL_DATA_ROOT.'/kreaimmo';
$conf->kreaimmo->multidir_output = array(1 => DOL_DATA_ROOT.'/1/kreaimmo', 2 => DOL_DATA_ROOT.'/2/kreaimmo', 3 => DOL_DATA_ROOT.'/3/kreaimmo');
$conf->kreaimmo->multidir_temp = array(1 => DOL_DATA_ROOT.'/1/kreaimmo/temp', 2 => DOL_DATA_ROOT.'/2/kreaimmo/temp', 3 => DOL_DATA_ROOT.'/3/kreaimmo/temp');
$conf->mycompany->dir_output = DOL_DATA_ROOT.'/mycompany';
