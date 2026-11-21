<?php
/* Copyright (C) 2004-2018 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2013-2016 Olivier Geffroy      <jeff@jeffinfo.com>
 * Copyright (C) 2015-2018 Alexandre Spangaro   <aspangaro@zendsi.com>
 * Copyright (C) 2018-2021 Philippe GRAND       <philippe.grand@atoo-net.com>
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

/**
 *    \defgroup   kreaimmo     Module KreaImmo
 *  \brief      kreaimmo     module descriptor.
 *
 *  \file       htdocs/kreaimmo/core/modules/modKreaImmo.class.php
 *  \ingroup    kreaimmo
 *  \brief      Description and activation file for module KreaImmo
 */
include_once DOL_DOCUMENT_ROOT . '/core/modules/DolibarrModules.class.php';

/**
 *  Description and activation class for module KreaImmo
 */
class modKreaImmo extends DolibarrModules
{
	/**
	 * Constructor. Define names, constants, directories, boxes, permissions
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $langs, $conf;

		$this->db = $db;

		// Id for module (must be unique).
		// Use here a free id (See in Home -> System information -> Dolibarr for list of used modules id).
		$this->numero = 156571; // Kreativität Works registered range: 156550–156599.
		// Key text used to identify module (for permissions, menus, etc...)
		$this->rights_class = 'kreaimmo';
		// Gives the possibility to the module, to provide his own family info and position of this family.


		// Family can be 'crm','financial','hr','projects','products','ecm','technic','interface','other'
		// It is used to group modules by family in module setup page
		$this->family = "Kreativität Works";
		// Module position in the family on 2 digits ('01', '10', '20', ...)
		$this->module_position = '01';
		// Gives the possibility to the module, to provide his own family info and position of this family (Overwrite $this->family and $this->module_position. Avoid this)
		//$this->familyinfo = array('myownfamily' => array('position' => '01', 'label' => $langs->trans("MyOwnFamily")));

		// Module label (no space allowed), used if translation string 'ModuleKreaImmoName' not found (MyModue is name of module).
		$this->name = preg_replace('/^mod/i', '', get_class($this));
		// Module description, used if translation string 'ModuleKreaImmoDesc' not found (MyModue is name of module).
		$this->description = "ModuleKreaImmoDesc";
		// Used only if file README.md and README-LL.md not found.
		$this->descriptionlong = "Solution logicielle très puissante pour toutes les agences immobilières et autres professionnels de l'immobilier";
		$editors = array('Kreativität Works');
		$this->editor_name = implode(',', $editors);
		$editor_url = array('https://kreativitat.com');
		$this->editor_url = implode(', &nbsp;', $editor_url);

		// Possible values for version are: 'development', 'experimental', 'dolibarr', 'dolibarr_deprecated' or a version string like 'x.y.z'
		$this->version = '1.1.0';
		// Key used in const table to save module status enabled/disabled (where KREAIMMO is value of property name of module in uppercase)
		$this->const_name = 'MAIN_MODULE_' . strtoupper($this->name);
		// Name of image file used for this module.
		// If file is in theme/yourtheme/img directory under name object_pictovalue.png, use this->picto='pictovalue'
		// If file is in module/img directory under name object_pictovalue.png, use this->picto='pictovalue@module'
		$this->picto = 'kreaimmo.svg@kreaimmo';

		// Defined all module parts (triggers, login, substitutions, menus, css, etc...)
		// for default path (eg: /kreaimmo/core/xxxxx) (0=disable, 1=enable)
		// for specific path of parts (eg: /kreaimmo/core/modules/barcode)
		// for specific css file (eg: /kreaimmo/css/kreaimmo.css.php)
		$this->module_parts = array(
			'triggers' => 1, // Set this to 1 if module has its own trigger directory (core/triggers)
			'login' => 0, // Set this to 1 if module has its own login method file (core/login)
			'substitutions' => 1,
			// Set this to 1 if module has its own substitution function file (core/substitutions)
			'menus' => 0,
			// Set this to 1 if module has its own menus handler directory (core/menus)
			'theme' => 0,
			// Set this to 1 if module has its own theme directory (theme)
			'tpl' => 0,
			// Set this to 1 if module overwrite template dir (core/tpl)
			'barcode' => 0,
			// Set this to 1 if module has its own barcode directory (core/modules/barcode)
			'models' => 1,
			// Set this to 1 if module has its own models directory (core/modules/xxx)
			'css' => array('/kreaimmo/css/kreaimmo.css.php'),
			// Set this to relative path of css file if module has its own css file
			'js' => array('/kreaimmo/js/kreaimmo.js.php'),
			// Set this to relative path of js file if module must load a js on all pages
			'hooks' => array(
				'data' => array(
					'leftblock',
					'index',
					'searchform',
					'thirdpartycard',
					'commcard',
					'categorycard',
					'contactcard',
					'actioncard',
					'agendathirdparty',
					'projectthirdparty',
					'infothirdparty',
					'thirdpartybancard',
					'consumptionthirdparty',
					'thirdpartynotification',
					'thirdpartymargins',
					'thirdpartycustomerprice',
					'searchform',
					'globalcard',
					'elementproperties',
					'all'
				)
			)
			// Set here all hooks context managed by module. To find available hook context, make a "grep -r '>initHooks(' *" on source code. You can also set hook context 'all'
		);

		// Data directories to create when module is enabled.
		// Example: this->dirs = array("/kreaimmo/temp","/kreaimmo/subdir");
		$this->dirs = array(
			'/kreaimmo',
			'/kreaimmo/cost',
			'/kreaimmo/rent',
			'/kreaimmo/local',
			'/kreaimmo/photo',
			'/kreaimmo/property',
			'/kreaimmo/owner',
			'/kreaimmo/receipt',
			'/kreaimmo/renter',
			'/kreaimmo/rentmassgen'
		);

		// Config pages. Put here list of php page, stored into kreaimmo/admin directory, to use to setup module.
		$this->config_page_url = array("immoreceipt.php@kreaimmo");

		// Dependencies
		$this->hidden = false;            // A condition to hide module
		$this->depends = array(
			"modSociete",
			"modBanque"
		);        // List of module class names as string that must be enabled if this module is enabled
		$this->requiredby = array();    // List of module ids to disable if this one is disabled
		$this->conflictwith = array();    // List of module class names as string this module is in conflict with
		$this->langfiles = array("kreaimmo@kreaimmo");
		$this->phpmin = array(7, 4);                    // Minimum version of PHP required by module
		$this->need_dolibarr_version = array(19, 0);    // Minimum version of Dolibarr required by module
		$this->warnings_activation = array();                     // Warning to show when we activate module. array('always'='text') or array('FR'='textfr','ES'='textes'...)
		$this->warnings_activation_ext = array();                 // Warning to show when we activate an external module. array('always'='text') or array('FR'='textfr','ES'='textes'...)
		//$this->automatic_activation = array('FR'=>'KreaImmoWasAutomaticallyActivatedBecauseOfYourCountryChoice');
		//$this->always_enabled = true;								// If true, can't be disabled

		// Constants
		// List of particular constants to add when module is enabled (key, 'chaine', value, desc, visible, 'current' or 'allentities', deleteonunactive)
		// Example: $this->const=array(0=>array('KREAIMMO_MYNEWCONST1','chaine','myvalue','This is a constant to add',1),
		//							   1=>array('KREAIMMO_MYNEWCONST2','chaine','myvalue','This is another constant to add',0, 'current', 1)
		// );
		$this->const = array();
		$r = 0;

		$this->const[$r][0] = "KREAIMMO_ADDON_PDF";
		$this->const[$r][1] = "chaine";
		$this->const[$r][2] = "kreaimmo_quittance";
		$this->const[$r][3] = 'Name of the kreaimmo generation manager in PDF format';
		$this->const[$r][4] = 0;
		$r++;

		$this->const[$r][0] = "KREAIMMO_ADDON_NUMBER";
		$this->const[$r][1] = "chaine";
		$this->const[$r][2] = "mod_kreaimmo_standard";
		$this->const[$r][3] = 'Name for numbering manager for kreaimmo';
		$this->const[$r][4] = 0;
		$r++;

		$this->const[$r][0] = "KREAIMMO_ADDON_PDF_ODT_PATH";
		$this->const[$r][1] = "chaine";
		$this->const[$r][2] = "DOL_DATA_ROOT/doctemplates/kreaimmo";
		$this->const[$r][3] = "";
		$this->const[$r][4] = 0;
		$r++;

		if (!isset($conf->kreaimmo) || !isset($conf->kreaimmo->enabled)) {
			$conf->kreaimmo = new stdClass();
			$conf->kreaimmo->enabled = 0;
		}


		// Array to add new pages in new tabs
		$this->tabs = array();
		// Example:
		//$this->tabs[] = array('data'=>'thirdparty:+tabimmoowner_card:KreaImmoOwner:kreaimmo@kreaimmo:/kreaimmo/owner/immoowner_card.php?id=__ID__');  					// To add a new tab identified by code tabname1
		// $this->tabs[] = array('data'=>'objecttype:+tabname2:SUBSTITUTION_Title2:mylangfile@kreaimmo:$user->rights->othermodule->read:/kreaimmo/mynewtab2.php?id=__ID__',  	// To add another new tab identified by code tabname2. Label will be result of calling all substitution functions on 'Title2' key.
		// $this->tabs[] = array('data'=>'objecttype:-tabname:NU:conditiontoremove');												     										// To remove an existing tab identified by code tabname
		//
		// Where objecttype can be
		// 'categories_x'	  to add a tab in category view (replace 'x' by type of category (0=product, 1=supplier, 2=customer, 3=member)
		// 'contact'          to add a tab in contact view
		// 'contract'         to add a tab in contract view
		// 'group'            to add a tab in group view
		// 'intervention'     to add a tab in intervention view
		// 'invoice'          to add a tab in customer invoice view
		// 'invoice_supplier' to add a tab in supplier invoice view
		// 'member'           to add a tab in fundation member view
		// 'opensurveypoll'	  to add a tab in opensurvey poll view
		// 'order'            to add a tab in customer order view
		// 'order_supplier'   to add a tab in supplier order view
		// 'payment'		  to add a tab in payment view
		// 'payment_supplier' to add a tab in supplier payment view
		// 'product'          to add a tab in product view
		// 'propal'           to add a tab in propal view
		// 'project'          to add a tab in project view
		// 'stock'            to add a tab in stock view
		// 'thirdparty'       to add a tab in third party view
		// 'user'             to add a tab in user view


		// Dictionaries
		$this->dictionaries = array(
			'langs' => 'kreaimmo@kreaimmo',
			'tabname' => array(
				MAIN_DB_PREFIX . "c_kreaimmo_diagnostic",
				MAIN_DB_PREFIX . "c_kreaimmo_immorent_type",
				MAIN_DB_PREFIX . "c_kreaimmo_immoproperty_type",
				MAIN_DB_PREFIX . "c_kreaimmo_juridique",
				MAIN_DB_PREFIX . "c_kreaimmo_builtdate",
				MAIN_DB_PREFIX . "kreaimmo_immocost_type",
				MAIN_DB_PREFIX . "c_kreaimmo_immocompteur_type",
				MAIN_DB_PREFIX . "kreaimmo_immoindice",
			),
			'tablib' => array(
				"DiagnosticImmo",
				"ImmorentType",
				"KreaImmoProperty_Type",
				"Juridique",
				"BuiltDate",
				"KreaImmoCost_Type",
				"ImmoCompteurType",
				"KreaImmoIndice",
			),
			'tabsql' => array(
				'SELECT d.rowid, d.code, d.label, d.active FROM ' . MAIN_DB_PREFIX . 'c_kreaimmo_diagnostic as d',
				'SELECT t.rowid, t.code, t.label, t.active FROM ' . MAIN_DB_PREFIX . 'c_kreaimmo_immorent_type as t',
				'SELECT tp.rowid, tp.code, tp.label, tp.active FROM ' . MAIN_DB_PREFIX . 'c_kreaimmo_immoproperty_type as tp',
				'SELECT t.rowid, t.code, t.label, t.active FROM ' . MAIN_DB_PREFIX . 'c_kreaimmo_juridique as t',
				'SELECT t.rowid, t.code, t.label, t.active FROM ' . MAIN_DB_PREFIX . 'c_kreaimmo_builtdate as t',
				'SELECT t.rowid, t.ref, t.label, t.famille, t.active FROM ' . MAIN_DB_PREFIX . 'kreaimmo_immocost_type as t',
				'SELECT t.rowid, t.ref, t.label, t.active FROM ' . MAIN_DB_PREFIX . 'c_kreaimmo_immocompteur_type as t',
				'SELECT t.rowid, t.date_start, t.date_end, t.type_indice,t.amount, t.active FROM ' . MAIN_DB_PREFIX . 'kreaimmo_immoindice as t'
			),
			'tabsqlsort' => array(
				"label ASC",
				"label ASC",
				"label ASC",
				"label ASC",
				"label ASC",
				"label ASC",
				"label ASC",
				"rowid ASC"
			),
			'tabfield' => array(
				"code,label",
				"code,label",
				"code,label",
				"code,label",
				"code,label",
				"ref,label,famille",
				"ref,label",
				"date_start,date_end,type_indice,amount",
			),
			'tabfieldvalue' => array(
				"code,label",
				"code,label",
				"code,label",
				"code,label",
				"code,label",
				"ref,label,famille",
				"ref,label",
				"date_start,date_end,type_indice,amount"
			),
			'tabfieldinsert' => array(
				"code,label",
				"code,label",
				"code,label",
				"code,label",
				"code,label",
				"ref,label,famille",
				"ref,label",
				"date_start,date_end,type_indice,amount"
			),
			'tabrowid' => array(
				"rowid",
				"rowid",
				"rowid",
				"rowid",
				"rowid",
				"rowid",
				"rowid",
				"rowid"
			),
			'tabcond' => array(
				$conf->kreaimmo->enabled,
				$conf->kreaimmo->enabled,
				$conf->kreaimmo->enabled,
				$conf->kreaimmo->enabled,
				$conf->kreaimmo->enabled,
				$conf->kreaimmo->enabled,
				$conf->kreaimmo->enabled,
				$conf->kreaimmo->enabled,
			)
		);

		// Boxes/Widgets
		// Add here list of php file(s) stored in kreaimmo/core/boxes that contains class to show a widget.
		$this->boxes = array(
			//0 => array('file'               => 'box_kreaimmo_immorenter.php', 'note' => 'Widget provided by KreaImmo',
			//		   'enabledbydefaulton' => 'Home')
			//1=>array('file'=>'immobilierwidget2.php@kreaimmo','note'=>'Widget provided by kreaimmo'),
			//2=>array('file'=>'immobilierwidget3.php@kreaimmo','note'=>'Widget provided by kreaimmo')
		);


		// Cronjobs (List of cron jobs entries to add when module is enabled)
		// unit_frequency must be 60 for minute, 3600 for hour, 86400 for day, 604800 for week
		$this->cronjobs = array();
		// Example: $this->cronjobs=array(0=>array('label'=>'My label', 'jobtype'=>'method', 'class'=>'/dir/class/file.class.php', 'objectname'=>'MyClass', 'method'=>'myMethod', 'parameters'=>'param1, param2', 'comment'=>'Comment', 'frequency'=>2, 'unitfrequency'=>3600, 'status'=>0, 'test'=>true),
		//								1=>array('label'=>'My label', 'jobtype'=>'command', 'command'=>'', 'parameters'=>'param1, param2', 'comment'=>'Comment', 'frequency'=>1, 'unitfrequency'=>3600*24, 'status'=>0, 'test'=>true)
		// );


		// Permissions
		$this->rights = array();        // Permission array used by this module

		$r = 0;
		$this->rights[$r][0] = $this->numero * 1000 + $r;    // Permission id (must not be already used)
		$this->rights[$r][1] = 'Read kreaimmo';    // Permission label
		$this->rights[$r][3] = 0;                    // Permission by default for new user (0/1)
		$this->rights[$r][4] = 'read';                // In php code, permission will be checked by test if ($user->rights->kreaimmo->level1->level2)
		$this->rights[$r][5] = '';                    // In php code, permission will be checked by test if ($user->rights->kreaimmo->level1->level2)

		$r++;
		$this->rights[$r][0] = $this->numero * 1000 + $r;    // Permission id (must not be already used)
		$this->rights[$r][1] = 'Create/Update kreaimmo';    // Permission label
		$this->rights[$r][3] = 0;                    // Permission by default for new user (0/1)
		$this->rights[$r][4] = 'write';                // In php code, permission will be checked by test if ($user->rights->kreaimmo->level1->level2)
		$this->rights[$r][5] = '';                    // In php code, permission will be checked by test if ($user->rights->kreaimmo->level1->level2)

		$r++;
		$this->rights[$r][0] = $this->numero * 1000 + $r;    // Permission id (must not be already used)
		$this->rights[$r][1] = 'Delete kreaimmo';    // Permission label
		$this->rights[$r][3] = 0;                    // Permission by default for new user (0/1)
		$this->rights[$r][4] = 'delete';                // In php code, permission will be checked by test if ($user->rights->kreaimmo->level1->level2)
		$this->rights[$r][5] = '';                    // In php code, permission will be checked by test if ($user->rights->kreaimmo->level1->level2)

		$r++;
		$this->rights[$r][0] = $this->numero * 1000 + $r;    // Permission id (must not be already used)
		$this->rights[$r][1] = 'Read renter';    // Permission label
		$this->rights[$r][3] = 0;                    // Permission by default for new user (0/1)
		$this->rights[$r][4] = 'renter';                // In php code, permission will be checked by test if ($user->rights->kreaimmo->level1->level2)
		$this->rights[$r][5] = 'read';                    // In php code, permission will be checked by test if ($user->rights->kreaimmo->level1->level2)

		$r++;
		$this->rights[$r][0] = $this->numero * 1000 + $r;    // Permission id (must not be already used)
		$this->rights[$r][1] = 'Create/Update renter';    // Permission label
		$this->rights[$r][3] = 0;                    // Permission by default for new user (0/1)
		$this->rights[$r][4] = 'renter';                // In php code, permission will be checked by test if ($user->rights->kreaimmo->level1->level2)
		$this->rights[$r][5] = 'write';                    // In php code, permission will be checked by test if ($user->rights->kreaimmo->level1->level2)

		$r++;
		$this->rights[$r][0] = $this->numero * 1000 + $r;    // Permission id (must not be already used)
		$this->rights[$r][1] = 'Delete renter';    // Permission label
		$this->rights[$r][3] = 0;                    // Permission by default for new user (0/1)
		$this->rights[$r][4] = 'renter';                // In php code, permission will be checked by test if ($user->rights->kreaimmo->level1->level2)
		$this->rights[$r][5] = 'delete';                    // In php code, permission will be checked by test if ($user->rights->kreaimmo->level1->level2)

		$r++;
		$this->rights[$r][0] = $this->numero * 1000 + $r;    // Permission id (must not be already used)
		$this->rights[$r][1] = 'Read rent';    // Permission label
		$this->rights[$r][3] = 0;                    // Permission by default for new user (0/1)
		$this->rights[$r][4] = 'rent';                // In php code, permission will be checked by test if ($user->rights->kreaimmo->level1->level2)
		$this->rights[$r][5] = 'read';                    // In php code, permission will be checked by test if ($user->rights->kreaimmo->level1->level2)

		$r++;
		$this->rights[$r][0] = $this->numero * 1000 + $r;    // Permission id (must not be already used)
		$this->rights[$r][1] = 'Create/Update rent';    // Permission label
		$this->rights[$r][3] = 0;                    // Permission by default for new user (0/1)
		$this->rights[$r][4] = 'rent';                // In php code, permission will be checked by test if ($user->rights->kreaimmo->level1->level2)
		$this->rights[$r][5] = 'write';                    // In php code, permission will be checked by test if ($user->rights->kreaimmo->level1->level2)

		$r++;
		$this->rights[$r][0] = $this->numero * 1000 + $r;    // Permission id (must not be already used)
		$this->rights[$r][1] = 'Delete rent';    // Permission label
		$this->rights[$r][3] = 0;                    // Permission by default for new user (0/1)
		$this->rights[$r][4] = 'rent';                // In php code, permission will be checked by test if ($user->rights->kreaimmo->level1->level2)
		$this->rights[$r][5] = 'delete';                    // In php code, permission will be checked by test if ($user->rights->kreaimmo->level1->level2)

		$r++;
		$this->rights[$r][0] = $this->numero * 1000 + $r;    // Permission id (must not be already used)
		$this->rights[$r][1] = 'Read owner';    // Permission label
		$this->rights[$r][3] = 0;                    // Permission by default for new user (0/1)
		$this->rights[$r][4] = 'owner';                // In php code, permission will be checked by test if ($user->rights->kreaimmo->level1->level2)
		$this->rights[$r][5] = 'read';                    // In php code, permission will be checked by test if ($user->rights->kreaimmo->level1->level2)

		$r++;
		$this->rights[$r][0] = $this->numero * 1000 + $r;    // Permission id (must not be already used)
		$this->rights[$r][1] = 'Create/Update owner';    // Permission label
		$this->rights[$r][3] = 0;                    // Permission by default for new user (0/1)
		$this->rights[$r][4] = 'owner';                // In php code, permission will be checked by test if ($user->rights->kreaimmo->level1->level2)
		$this->rights[$r][5] = 'write';                    // In php code, permission will be checked by test if ($user->rights->kreaimmo->level1->level2)

		$r++;
		$this->rights[$r][0] = $this->numero * 1000 + $r;    // Permission id (must not be already used)
		$this->rights[$r][1] = 'Delete owner';    // Permission label
		$this->rights[$r][3] = 0;                    // Permission by default for new user (0/1)
		$this->rights[$r][4] = 'owner';                // In php code, permission will be checked by test if ($user->rights->kreaimmo->level1->level2)
		$this->rights[$r][5] = 'delete';                    // In php code, permission will be checked by test if ($user->rights->kreaimmo->level1->level2)

		$r++;
		$this->rights[$r][0] = $this->numero * 1000 + $r;    // Permission id (must not be already used)
		$this->rights[$r][1] = 'Read property';    // Permission label
		$this->rights[$r][3] = 0;                    // Permission by default for new user (0/1)
		$this->rights[$r][4] = 'property';                // In php code, permission will be checked by test if ($user->rights->kreaimmo->level1->level2)
		$this->rights[$r][5] = 'read';                    // In php code, permission will be checked by test if ($user->rights->kreaimmo->level1->level2)

		$r++;
		$this->rights[$r][0] = $this->numero * 1000 + $r;    // Permission id (must not be already used)
		$this->rights[$r][1] = 'Create/Update property';    // Permission label
		$this->rights[$r][3] = 0;                    // Permission by default for new user (0/1)
		$this->rights[$r][4] = 'property';                // In php code, permission will be checked by test if ($user->rights->kreaimmo->level1->level2)
		$this->rights[$r][5] = 'write';                    // In php code, permission will be checked by test if ($user->rights->kreaimmo->level1->level2)

		$r++;
		$this->rights[$r][0] = $this->numero * 1000 + $r;    // Permission id (must not be already used)
		$this->rights[$r][1] = 'Delete property';    // Permission label
		$this->rights[$r][3] = 0;                    // Permission by default for new user (0/1)
		$this->rights[$r][4] = 'property';                // In php code, permission will be checked by test if ($user->rights->kreaimmo->level1->level2)
		$this->rights[$r][5] = 'delete';                    // In php code, permission will be checked by test if ($user->rights->kreaimmo->level1->level2)

		$r++;
		$this->rights[$r][0] = $this->numero * 1000 + $r;    // Permission id (must not be already used)
		$this->rights[$r][1] = 'Read receipt';    // Permission label
		$this->rights[$r][3] = 0;                    // Permission by default for new user (0/1)
		$this->rights[$r][4] = 'receipt';                // In php code, permission will be checked by test if ($user->rights->kreaimmo->level1->level2)
		$this->rights[$r][5] = 'read';                    // In php code, permission will be checked by test if ($user->rights->kreaimmo->level1->level2)

		$r++;
		$this->rights[$r][0] = $this->numero * 1000 + $r;    // Permission id (must not be already used)
		$this->rights[$r][1] = 'Create/Update receipt';    // Permission label
		$this->rights[$r][3] = 0;                    // Permission by default for new user (0/1)
		$this->rights[$r][4] = 'receipt';                // In php code, permission will be checked by test if ($user->rights->kreaimmo->level1->level2)
		$this->rights[$r][5] = 'write';                    // In php code, permission will be checked by test if ($user->rights->kreaimmo->level1->level2)

		$r++;
		$this->rights[$r][0] = $this->numero * 1000 + $r;    // Permission id (must not be already used)
		$this->rights[$r][1] = 'Delete receipt';    // Permission label
		$this->rights[$r][3] = 0;                    // Permission by default for new user (0/1)
		$this->rights[$r][4] = 'receipt';                // In php code, permission will be checked by test if ($user->rights->kreaimmo->level1->level2)
		$this->rights[$r][5] = 'delete';                    // In php code, permission will be checked by test if ($user->rights->kreaimmo->level1->level2)

		$r++;
		$this->rights[$r][0] = $this->numero * 1000 + $r;    // Permission id (must not be already used)
		$this->rights[$r][1] = 'Read payment';    // Permission label
		$this->rights[$r][3] = 0;                    // Permission by default for new user (0/1)
		$this->rights[$r][4] = 'payment';                // In php code, permission will be checked by test if ($user->rights->kreaimmo->level1->level2)
		$this->rights[$r][5] = 'read';                    // In php code, permission will be checked by test if ($user->rights->kreaimmo->level1->level2)

		$r++;
		$this->rights[$r][0] = $this->numero * 1000 + $r;    // Permission id (must not be already used)
		$this->rights[$r][1] = 'Create/Update payment';    // Permission label
		$this->rights[$r][3] = 0;                    // Permission by default for new user (0/1)
		$this->rights[$r][4] = 'payment';                // In php code, permission will be checked by test if ($user->rights->kreaimmo->level1->level2)
		$this->rights[$r][5] = 'write';                    // In php code, permission will be checked by test if ($user->rights->kreaimmo->level1->level2)

		$r++;
		$this->rights[$r][0] = $this->numero * 1000 + $r;    // Permission id (must not be already used)
		$this->rights[$r][1] = 'Delete payment';    // Permission label
		$this->rights[$r][3] = 0;                    // Permission by default for new user (0/1)
		$this->rights[$r][4] = 'payment';                // In php code, permission will be checked by test if ($user->rights->kreaimmo->level1->level2)
		$this->rights[$r][5] = 'delete';                    // In php code, permission will be checked by test if ($user->rights->kreaimmo->level1->level2)

		$r++;
		$this->rights[$r][0] = $this->numero * 1000 + $r;    // Permission id (must not be already used)
		$this->rights[$r][1] = 'Read cost';    // Permission label
		$this->rights[$r][3] = 0;                    // Permission by default for new user (0/1)
		$this->rights[$r][4] = 'cost';                // In php code, permission will be checked by test if ($user->rights->kreaimmo->level1->level2)
		$this->rights[$r][5] = 'read';                    // In php code, permission will be checked by test if ($user->rights->kreaimmo->level1->level2)

		$r++;
		$this->rights[$r][0] = $this->numero * 1000 + $r;    // Permission id (must not be already used)
		$this->rights[$r][1] = 'Create/Update cost';    // Permission label
		$this->rights[$r][3] = 0;                    // Permission by default for new user (0/1)
		$this->rights[$r][4] = 'cost';                // In php code, permission will be checked by test if ($user->rights->kreaimmo->level1->level2)
		$this->rights[$r][5] = 'write';                    // In php code, permission will be checked by test if ($user->rights->kreaimmo->level1->level2)

		$r++;
		$this->rights[$r][0] = $this->numero * 1000 + $r;    // Permission id (must not be already used)
		$this->rights[$r][1] = 'Delete cost';    // Permission label
		$this->rights[$r][3] = 0;                    // Permission by default for new user (0/1)
		$this->rights[$r][4] = 'cost';                // In php code, permission will be checked by test if ($user->rights->kreaimmo->level1->level2)
		$this->rights[$r][5] = 'delete';                    // In php code, permission will be checked by test if ($user->rights->kreaimmo->level1->level2)

		$r++;
		$this->rights[$r][0] = $this->numero * 1000 + $r;    // Permission id (must not be already used)
		$this->rights[$r][1] = 'Read cost_type';    // Permission label
		$this->rights[$r][3] = 0;                    // Permission by default for new user (0/1)
		$this->rights[$r][4] = 'cost_type';                // In php code, permission will be checked by test if ($user->rights->kreaimmo->level1->level2)
		$this->rights[$r][5] = 'read';                    // In php code, permission will be checked by test if ($user->rights->kreaimmo->level1->level2)

		$r++;
		$this->rights[$r][0] = $this->numero * 1000 + $r;    // Permission id (must not be already used)
		$this->rights[$r][1] = 'Create/Update cost_type';    // Permission label
		$this->rights[$r][3] = 0;                    // Permission by default for new user (0/1)
		$this->rights[$r][4] = 'cost_type';                // In php code, permission will be checked by test if ($user->rights->kreaimmo->level1->level2)
		$this->rights[$r][5] = 'write';                    // In php code, permission will be checked by test if ($user->rights->kreaimmo->level1->level2)

		$r++;
		$this->rights[$r][0] = $this->numero * 1000 + $r;    // Permission id (must not be already used)
		$this->rights[$r][1] = 'Delete cost_type';    // Permission label
		$this->rights[$r][3] = 0;                    // Permission by default for new user (0/1)
		$this->rights[$r][4] = 'cost_type';                // In php code, permission will be checked by test if ($user->rights->kreaimmo->level1->level2)
		$this->rights[$r][5] = 'delete';                    // In php code, permission will be checked by test if ($user->rights->kreaimmo->level1->level2)

		$r++;
		$this->rights[$r][0] = $this->numero * 1000 + $r;    // Permission id (must not be already used)
		$this->rights[$r][1] = 'Read compteur';    // Permission label
		$this->rights[$r][3] = 0;                    // Permission by default for new user (0/1)
		$this->rights[$r][4] = 'immocompteur';                // In php code, permission will be checked by test if ($user->rights->kreaimmo->level1->level2)
		$this->rights[$r][5] = 'read';                    // In php code, permission will be checked by test if ($user->rights->kreaimmo->level1->level2)

		$r++;
		$this->rights[$r][0] = $this->numero * 1000 + $r;    // Permission id (must not be already used)
		$this->rights[$r][1] = 'Create/Update compteur';    // Permission label
		$this->rights[$r][3] = 0;                    // Permission by default for new user (0/1)
		$this->rights[$r][4] = 'immocompteur';                // In php code, permission will be checked by test if ($user->rights->kreaimmo->level1->level2)
		$this->rights[$r][5] = 'write';                    // In php code, permission will be checked by test if ($user->rights->kreaimmo->level1->level2)

		$r++;
		$this->rights[$r][0] = $this->numero * 1000 + $r;    // Permission id (must not be already used)
		$this->rights[$r][1] = 'Delete compteur';    // Permission label
		$this->rights[$r][3] = 0;                    // Permission by default for new user (0/1)
		$this->rights[$r][4] = 'immocompteur';                // In php code, permission will be checked by test if ($user->rights->kreaimmo->level1->level2)
		$this->rights[$r][5] = 'delete';

		$r++;
		$this->rights[$r][0] = $this->numero * 1000 + $r;    // Permission id (must not be already used)
		$this->rights[$r][1] = 'Read dict';    // Permission label
		$this->rights[$r][3] = 0;                    // Permission by default for new user (0/1)
		$this->rights[$r][4] = 'dict';                // In php code, permission will be checked by test if ($user->rights->kreaimmo->level1->level2)
		$this->rights[$r][5] = 'read';                    // In php code, permission will be checked by test if ($user->rights->kreaimmo->level1->level2)

		$r++;
		$this->rights[$r][0] = $this->numero * 1000 + $r;    // Permission id (must not be already used)
		$this->rights[$r][1] = 'Create/Update dict';    // Permission label
		$this->rights[$r][3] = 0;                    // Permission by default for new user (0/1)
		$this->rights[$r][4] = 'dict';                // In php code, permission will be checked by test if ($user->rights->kreaimmo->level1->level2)
		$this->rights[$r][5] = 'write';                    // In php code, permission will be checked by test if ($user->rights->kreaimmo->level1->level2)

		$r++;
		$this->rights[$r][0] = $this->numero * 1000 + $r;    // Permission id (must not be already used)
		$this->rights[$r][1] = 'Delete dict';    // Permission label
		$this->rights[$r][3] = 0;                    // Permission by default for new user (0/1)
		$this->rights[$r][4] = 'dict';                // In php code, permission will be checked by test if ($user->rights->kreaimmo->level1->level2)
		$this->rights[$r][5] = 'delete';

		// Main menu entries
		$this->menu = array();            // List of menus to add
		$r = 0;

		// Add here entries to declare new menus
		$this->menu[$r++] = array(
			'fk_menu' => '',
			// '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type' => 'top',                            // This is a Top menu entry
			'titre' => 'KreaImmo',
			'mainmenu' => 'kreaimmo',
			'leftmenu' => '',
			'url' => '/kreaimmo/dashboard.php',
			'langs' => 'kreaimmo@kreaimmo',
			// Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 1100 + $r,
			'enabled' => '$conf->kreaimmo->enabled',
			// Define condition to show or hide menu entry. Use '$conf->kreaimmo->enabled' if entry must be visible if module is enabled.
			'perms' => '1',
			// Use 'perms'=>'$user->rights->kreaimmo->level1->level2' if you want your menu with a permission rules
			'target' => '',
			'user' => 2
		);                                // 0=Menu for internal users, 1=external users, 2=both

		///Owner

		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=kreaimmo',
			// '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type' => 'left',                            // This is a Left menu entry
			'titre' => 'MenuImmoOwner',
			'mainmenu' => 'kreaimmo',
			'leftmenu' => 'kreaimmo_immoowner',
			'url' => '/kreaimmo/owner/immoowner_list.php',
			'langs' => 'kreaimmo@kreaimmo',
			// Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 1100 + $r,
			'enabled' => '$conf->kreaimmo->enabled',
			// Define condition to show or hide menu entry. Use '$conf->kreaimmo->enabled' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
			'perms' => '1',
			// Use 'perms'=>'$user->rights->kreaimmo->level1->level2' if you want your menu with a permission rules
			'target' => '',
			'user' => 2
		);                                // 0=Menu for internal users, 1=external users, 2=both
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=kreaimmo,fk_leftmenu=kreaimmo_immoowner',
			// '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type' => 'left',                            // This is a Left menu entry
			'titre' => 'MenuNewImmoOwner',
			'mainmenu' => 'kreaimmo',
			'leftmenu' => '',
			'url' => '/kreaimmo/owner/immoowner_card.php?action=create',
			'langs' => 'kreaimmo@kreaimmo',
			// Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 1100 + $r,
			'enabled' => '$conf->kreaimmo->enabled',
			// Define condition to show or hide menu entry. Use '$conf->kreaimmo->enabled' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
			'perms' => '1',
			// Use 'perms'=>'$user->rights->kreaimmo->level1->level2' if you want your menu with a permission rules
			'target' => '',
			'user' => 2
		);                                // 0=Menu for internal users, 1=external users, 2=both
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=kreaimmo,fk_leftmenu=kreaimmo_immoowner',
			// '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type' => 'left',                            // This is a Left menu entry
			'titre' => 'MenuListImmoOwnerType',
			'mainmenu' => 'kreaimmo',
			'leftmenu' => '',
			'url' => '/kreaimmo/owner_type/immoowner_type_list.php',
			'langs' => 'kreaimmo@kreaimmo',
			// Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 1100 + $r,
			'enabled' => '$conf->kreaimmo->enabled',
			// Define condition to show or hide menu entry. Use '$conf->kreaimmo->enabled' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
			'perms' => '1',
			// Use 'perms'=>'$user->rights->kreaimmo->level1->level2' if you want your menu with a permission rules
			'target' => '',
			'user' => 2
		);
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=kreaimmo,fk_leftmenu=kreaimmo_immoowner',
			// '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type' => 'left',                            // This is a Left menu entry
			'titre' => 'MenuNewImmoOwnerType',
			'mainmenu' => 'kreaimmo',
			'leftmenu' => '',
			'url' => '/kreaimmo/owner_type/immoowner_type_card.php?action=create',
			'langs' => 'kreaimmo@kreaimmo',
			// Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 1100 + $r,
			'enabled' => '$conf->kreaimmo->enabled',
			// Define condition to show or hide menu entry. Use '$conf->kreaimmo->enabled' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
			'perms' => '1',
			// Use 'perms'=>'$user->rights->kreaimmo->level1->level2' if you want your menu with a permission rules
			'target' => '',
			'user' => 2
		);

		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=kreaimmo',
			// '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type' => 'left',                            // This is a Left menu entry
			'titre' => 'MenuImmoProperty',
			'mainmenu' => 'kreaimmo',
			'leftmenu' => 'kreaimmo_immoproperty',
			'url' => '/kreaimmo/property/immoproperty_list.php',
			'langs' => 'kreaimmo@kreaimmo',
			// Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 1100 + $r,
			'enabled' => '$conf->kreaimmo->enabled',
			// Define condition to show or hide menu entry. Use '$conf->kreaimmo->enabled' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
			'perms' => '1',
			// Use 'perms'=>'$user->rights->kreaimmo->level1->level2' if you want your menu with a permission rules
			'target' => '',
			'user' => 2
		);                                // 0=Menu for internal users, 1=external users, 2=both
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=kreaimmo,fk_leftmenu=kreaimmo_immoproperty',
			// '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type' => 'left',                            // This is a Left menu entry
			'titre' => 'MenuNewImmoProperty',
			'mainmenu' => 'kreaimmo',
			'leftmenu' => 'kreaimmo_immoproperty_new',
			'url' => '/kreaimmo/property/immoproperty_card.php?action=create',
			'langs' => 'kreaimmo@kreaimmo',
			// Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 1100 + $r,
			'enabled' => '$conf->kreaimmo->enabled',
			// Define condition to show or hide menu entry. Use '$conf->kreaimmo->enabled' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
			'perms' => '1',
			// Use 'perms'=>'$user->rights->kreaimmo->level1->level2' if you want your menu with a permission rules
			'target' => '',
			'user' => 2
		);                                // 0=Menu for internal users, 1=external users, 2=both

		//Lot Fiscal
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=kreaimmo',
			// '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type' => 'left',                            // This is a Left menu entry
			'titre' => 'MenuImmoLotFiscal',
			'mainmenu' => 'kreaimmo',
			'leftmenu' => 'kreaimmo_immobuilding',
			'url' => '/kreaimmo/building/immobuilding_list.php',
			'langs' => 'kreaimmo@kreaimmo',
			// Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 1100 + $r,
			'enabled' => '$conf->kreaimmo->enabled',
			// Define condition to show or hide menu entry. Use '$conf->kreaimmo->enabled' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
			'perms' => '1',
			// Use 'perms'=>'$user->rights->kreaimmo->level1->level2' if you want your menu with a permission rules
			'target' => '',
			'user' => 2
		);                                // 0=Menu for internal users, 1=external users, 2=both 		// 0=Menu for internal users, 1=external users, 2=both

		//Compteur
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=kreaimmo',
			// '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type' => 'left',                            // This is a Left menu entry
			'titre' => 'MenuImmoCompteur',
			'mainmenu' => 'kreaimmo',
			'leftmenu' => 'kreaimmo_immocompteur',
			'url' => '/kreaimmo/compteur/immocompteur_list.php',
			'langs' => 'kreaimmo@kreaimmo',
			// Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 1100 + $r,
			'enabled' => '$conf->kreaimmo->enabled',
			// Define condition to show or hide menu entry. Use '$conf->kreaimmo->enabled' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
			'perms' => '$user->rights->kreaimmo->immocompteur->read',
			// Use 'perms'=>'$user->rights->kreaimmo->level1->level2' if you want your menu with a permission rules
			'target' => '',
			'user' => 2
		);                                // 0=Menu for internal users, 1=external users, 2=both
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=kreaimmo,fk_leftmenu=kreaimmo_immocompteur',
			// '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type' => 'left',                            // This is a Left menu entry
			'titre' => 'MenuNewImmoCompteur',
			'mainmenu' => 'kreaimmo',
			'leftmenu' => 'kreaimmo_immocompteur_new',
			'url' => '/kreaimmo/compteur/immocompteur_card.php?action=create',
			'langs' => 'kreaimmo@kreaimmo',
			// Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 1100 + $r,
			'enabled' => '$conf->kreaimmo->enabled',
			// Define condition to show or hide menu entry. Use '$conf->kreaimmo->enabled' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
			'perms' => '$user->rights->kreaimmo->immocompteur->write',
			// Use 'perms'=>'$user->rights->kreaimmo->level1->level2' if you want your menu with a permission rules
			'target' => '',
			'user' => 2
		);

		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=kreaimmo,fk_leftmenu=kreaimmo_immocompteur',
			// '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type' => 'left',                            // This is a Left menu entry
			'titre' => 'MenuImmoCompteurStats',
			'mainmenu' => 'kreaimmo',
			'leftmenu' => 'kreaimmo_immocompteur_stats',
			'url' => '/kreaimmo/compteur/stats.php',
			'langs' => 'kreaimmo@kreaimmo',
			// Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 1100 + $r,
			'enabled' => '$conf->kreaimmo->enabled',
			// Define condition to show or hide menu entry. Use '$conf->kreaimmo->enabled' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
			'perms' => '$user->rights->kreaimmo->immocompteur->read',
			// Use 'perms'=>'$user->rights->kreaimmo->level1->level2' if you want your menu with a permission rules
			'target' => '',
			'user' => 2
		);        // 0=Menu for internal users, 1=external users, 2=both
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=kreaimmo,fk_leftmenu=kreaimmo_immocompteur',
			// '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type' => 'left',                            // This is a Left menu entry
			'titre' => 'MenuImmoCompteurStatsYear',
			'mainmenu' => 'kreaimmo',
			'leftmenu' => 'kreaimmo_immocompteur_stats_year',
			'url' => '/kreaimmo/compteur/stats_by_year.php',
			'langs' => 'kreaimmo@kreaimmo',
			// Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 1100 + $r,
			'enabled' => '$conf->kreaimmo->enabled',
			// Define condition to show or hide menu entry. Use '$conf->kreaimmo->enabled' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
			'perms' => '$user->rights->kreaimmo->immocompteur->read',
			// Use 'perms'=>'$user->rights->kreaimmo->level1->level2' if you want your menu with a permission rules
			'target' => '',
			'user' => 2
		);
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=kreaimmo,fk_leftmenu=kreaimmo_immocompteur',
			// '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type' => 'left',                            // This is a Left menu entry
			'titre' => 'MenuImmoCompteurCost',
			'mainmenu' => 'kreaimmo',
			'leftmenu' => 'kreaimmo_immocompteur_cost',
			'url' => '/kreaimmo/compteurcost/immocompteur_cost_list.php',
			'langs' => 'kreaimmo@kreaimmo',
			// Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 1100 + $r,
			'enabled' => '$conf->kreaimmo->enabled',
			// Define condition to show or hide menu entry. Use '$conf->kreaimmo->enabled' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
			'perms' => '$user->rights->kreaimmo->immocompteur->read',
			// Use 'perms'=>'$user->rights->kreaimmo->level1->level2' if you want your menu with a permission rules
			'target' => '',
			'user' => 2
		);

		//// renter
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=kreaimmo',
			// '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type' => 'left',                            // This is a Left menu entry
			'titre' => 'MenuImmoRenter',
			'mainmenu' => 'kreaimmo',
			'leftmenu' => 'kreaimmo_immorenter',
			'url' => '/kreaimmo/renter/immorenter_list.php',
			'langs' => 'kreaimmo@kreaimmo',
			// Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 1100 + $r,
			'enabled' => '$conf->kreaimmo->enabled',
			// Define condition to show or hide menu entry. Use '$conf->kreaimmo->enabled' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
			'perms' => '1',
			// Use 'perms'=>'$user->rights->kreaimmo->level1->level2' if you want your menu with a permission rules
			'target' => '',
			'user' => 2
		);                                // 0=Menu for internal users, 1=external users, 2=both
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=kreaimmo,fk_leftmenu=kreaimmo_immorenter',
			// '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type' => 'left',                            // This is a Left menu entry
			'titre' => 'MenuNewImmoRenter',
			'mainmenu' => 'kreaimmo',
			'leftmenu' => 'kreaimmo_immorenter_new',
			'url' => '/kreaimmo/renter/immorenter_card.php?action=create',
			'langs' => 'kreaimmo@kreaimmo',
			// Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 1100 + $r,
			'enabled' => '$conf->kreaimmo->enabled',
			// Define condition to show or hide menu entry. Use '$conf->kreaimmo->enabled' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
			'perms' => '1',
			// Use 'perms'=>'$user->rights->kreaimmo->level1->level2' if you want your menu with a permission rules
			'target' => '',
			'user' => 2
		);                                // 0=Menu for internal users, 1=external users, 2=both


		/// rent


		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=kreaimmo',
			// '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type' => 'left',                            // This is a Left menu entry
			'titre' => 'MenuImmoRent',
			'mainmenu' => 'kreaimmo',
			'leftmenu' => 'kreaimmo_immorent',
			'url' => '/kreaimmo/rent/immorent_list.php',
			'langs' => 'kreaimmo@kreaimmo',
			// Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 1100 + $r,
			'enabled' => '$conf->kreaimmo->enabled',
			// Define condition to show or hide menu entry. Use '$conf->kreaimmo->enabled' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
			'perms' => '1',
			// Use 'perms'=>'$user->rights->kreaimmo->level1->level2' if you want your menu with a permission rules
			'target' => '',
			'user' => 2
		);                                // 0=Menu for internal users, 1=external users, 2=both
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=kreaimmo,fk_leftmenu=kreaimmo_immorent',
			// '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type' => 'left',                            // This is a Left menu entry
			'titre' => 'MenuNewImmoRent',
			'mainmenu' => 'kreaimmo',
			'leftmenu' => 'kreaimmo_immorent_new',
			'url' => '/kreaimmo/rent/immorent_card.php?action=create',
			'langs' => 'kreaimmo@kreaimmo',
			// Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 1100 + $r,
			'enabled' => '$conf->kreaimmo->enabled',
			// Define condition to show or hide menu entry. Use '$conf->kreaimmo->enabled' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
			'perms' => '1',
			// Use 'perms'=>'$user->rights->kreaimmo->level1->level2' if you want your menu with a permission rules
			'target' => '',
			'user' => 2
		);                                // 0=Menu for internal users, 1=external users, 2=both


		// receipt

		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=kreaimmo',
			// '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type' => 'left',                            // This is a Left menu entry
			'titre' => 'MenuListImmoReceipt',
			'mainmenu' => 'kreaimmo',
			'leftmenu' => 'kreaimmo_immoreceipt',
			'url' => '/kreaimmo/receipt/immoreceipt_list.php',
			'langs' => 'kreaimmo@kreaimmo',
			// Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 1100 + $r,
			'enabled' => '$conf->kreaimmo->enabled',
			// Define condition to show or hide menu entry. Use '$conf->kreaimmo->enabled' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
			'perms' => '1',
			// Use 'perms'=>'$user->rights->kreaimmo->level1->level2' if you want your menu with a permission rules
			'target' => '',
			'user' => 2
		);                                // 0=Menu for internal users, 1=external users, 2=both


		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=kreaimmo,fk_leftmenu=kreaimmo_immoreceipt',
			// '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type' => 'left',                            // This is a Left menu entry
			'titre' => 'MenuAllReceiptperContract',
			'mainmenu' => 'kreaimmo',
			'leftmenu' => 'kreaimmo_immoreceipt_createall',
			'url' => '/kreaimmo/receipt/immoreceipt_card.php?action=createall',
			'langs' => 'kreaimmo@kreaimmo',
			// Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 1100 + $r,
			'enabled' => '$conf->kreaimmo->enabled',
			// Define condition to show or hide menu entry. Use '$conf->kreaimmo->enabled' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
			'perms' => '1',
			// Use 'perms'=>'$user->rights->kreaimmo->level1->level2' if you want your menu with a permission rules
			'target' => '',
			'user' => 2
		);                                // 0=Menu for internal users, 1=external users, 2=both

		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=kreaimmo,fk_leftmenu=kreaimmo_immoreceipt',
			// '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type' => 'left',                            // This is a Left menu entry
			'titre' => 'MenuValidateReceipt',
			'mainmenu' => 'kreaimmo',
			'leftmenu' => 'kreaimmo_immoreceipt_validaterent',
			'url' => '/kreaimmo/receipt/immoreceipt_list.php?action=validaterent',
			'langs' => 'kreaimmo@kreaimmo',
			// Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 1100 + $r,
			'enabled' => '$conf->kreaimmo->enabled',
			// Define condition to show or hide menu entry. Use '$conf->kreaimmo->enabled' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
			'perms' => '1',
			// Use 'perms'=>'$user->rights->kreaimmo->level1->level2' if you want your menu with a permission rules
			'target' => '',
			'user' => 2
		);                                // 0=Menu for internal users, 1=external users, 2=both

		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=kreaimmo,fk_leftmenu=kreaimmo_immoreceipt',
			// '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type' => 'left',                            // This is a Left menu entry
			'titre' => 'MenuListImmoReceiptStats',
			'mainmenu' => 'kreaimmo',
			'leftmenu' => 'kreaimmo_immoreceipt',
			'url' => '/kreaimmo/receipt/stats.php',
			'langs' => 'kreaimmo@kreaimmo',
			// Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 1100 + $r,
			'enabled' => '$conf->kreaimmo->enabled',
			// Define condition to show or hide menu entry. Use '$conf->kreaimmo->enabled' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
			'perms' => '1',
			// Use 'perms'=>'$user->rights->kreaimmo->level1->level2' if you want your menu with a permission rules
			'target' => '',
			'user' => 2
		);                                // 0=Menu for internal users, 1=external users, 2=both

		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=kreaimmo',
			// '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type' => 'left',                            // This is a Left menu entry
			'titre' => 'MenuImmoPayment',
			'mainmenu' => 'kreaimmo',
			'leftmenu' => 'kreaimmo_immopayment',
			'url' => '/kreaimmo/payment/immopayment_list.php',
			'langs' => 'kreaimmo@kreaimmo',
			// Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 1100 + $r,
			'enabled' => '$conf->kreaimmo->enabled',
			// Define condition to show or hide menu entry. Use '$conf->kreaimmo->enabled' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
			'perms' => '1',
			// Use 'perms'=>'$user->rights->kreaimmo->level1->level2' if you want your menu with a permission rules
			'target' => '',
			'user' => 2
		);                                // 0=Menu for internal users, 1=external users, 2=both
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=kreaimmo,fk_leftmenu=kreaimmo_immopayment',
			// '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type' => 'left',                            // This is a Left menu entry
			'titre' => 'MenuNewImmoPayment',
			'mainmenu' => 'kreaimmo',
			'leftmenu' => 'kreaimmo_immopayment_new',
			'url' => '/kreaimmo/payment/immopayment_card.php?action=createall',
			'langs' => 'kreaimmo@kreaimmo',
			// Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 1100 + $r,
			'enabled' => '$conf->kreaimmo->enabled',
			// Define condition to show or hide menu entry. Use '$conf->kreaimmo->enabled' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
			'perms' => '1',
			// Use 'perms'=>'$user->rights->kreaimmo->level1->level2' if you want your menu with a permission rules
			'target' => '',
			'user' => 2
		);                                // 0=Menu for internal users, 1=external users, 2=both
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=kreaimmo,fk_leftmenu=kreaimmo_immopayment',
			// '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type' => 'left',                            // This is a Left menu entry
			'titre' => 'MenuListImmoPayment',
			'mainmenu' => 'kreaimmo',
			'leftmenu' => 'kreaimmo_immopayment_list',
			'url' => '/kreaimmo/payment/immopayment_list.php',
			'langs' => 'kreaimmo@kreaimmo',
			// Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 1100 + $r,
			'enabled' => '$conf->kreaimmo->enabled',
			// Define condition to show or hide menu entry. Use '$conf->kreaimmo->enabled' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
			'perms' => '1',
			// Use 'perms'=>'$user->rights->kreaimmo->level1->level2' if you want your menu with a permission rules
			'target' => '',
			'user' => 2
		);                                // 0=Menu for internal users, 1=external users, 2=both

		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=kreaimmo,fk_leftmenu=kreaimmo_immopayment',
			// '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type' => 'left',                            // This is a Left menu entry
			'titre' => 'MenuListImmoPaymentStats',
			'mainmenu' => 'kreaimmo',
			'leftmenu' => 'kreaimmo_immopayment_list',
			'url' => '/kreaimmo/payment/stats.php',
			'langs' => 'kreaimmo@kreaimmo',
			// Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 1100 + $r,
			'enabled' => '$conf->kreaimmo->enabled',
			// Define condition to show or hide menu entry. Use '$conf->kreaimmo->enabled' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
			'perms' => '1',
			// Use 'perms'=>'$user->rights->kreaimmo->level1->level2' if you want your menu with a permission rules
			'target' => '',
			'user' => 2
		);

		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=kreaimmo',
			// '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type' => 'left',                            // This is a Left menu entry
			'titre' => 'MenuListImmoCost',
			'mainmenu' => 'kreaimmo',
			'leftmenu' => 'kreaimmo_immocost',
			'url' => '/kreaimmo/cost/immocost_list.php',
			'langs' => 'kreaimmo@kreaimmo',
			// Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 1100 + $r,
			'enabled' => '$conf->kreaimmo->enabled',
			// Define condition to show or hide menu entry. Use '$conf->kreaimmo->enabled' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
			'perms' => '1',
			// Use 'perms'=>'$user->rights->kreaimmo->level1->level2' if you want your menu with a permission rules
			'target' => '',
			'user' => 2
		);                                // 0=Menu for internal users, 1=external users, 2=both
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=kreaimmo,fk_leftmenu=kreaimmo_immocost',
			// '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type' => 'left',                            // This is a Left menu entry
			'titre' => 'MenuNewImmoCost',
			'mainmenu' => 'kreaimmo',
			'leftmenu' => 'kreaimmo_immocost_new_cost',
			'url' => '/kreaimmo/cost/immocost_card.php?action=create',
			'langs' => 'kreaimmo@kreaimmo',
			// Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 1100 + $r,
			'enabled' => '$conf->kreaimmo->enabled',
			// Define condition to show or hide menu entry. Use '$conf->kreaimmo->enabled' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
			'perms' => '1',
			// Use 'perms'=>'$user->rights->kreaimmo->level1->level2' if you want your menu with a permission rules
			'target' => '',
			'user' => 2
		);                                // 0=Menu for internal users, 1=external users, 2=both

		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=kreaimmo,fk_leftmenu=kreaimmo_immocost',
			// '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type' => 'left',                            // This is a Left menu entry
			'titre' => 'MenuRenterCost',
			'mainmenu' => 'kreaimmo',
			'leftmenu' => 'kreaimmo_immocost_renter_cost',
			'url' => '/kreaimmo/cost/cost_renter.php',
			'langs' => 'kreaimmo@kreaimmo',
			// Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 1100 + $r,
			'enabled' => '$conf->kreaimmo->enabled',
			// Define condition to show or hide menu entry. Use '$conf->kreaimmo->enabled' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
			'perms' => '1',
			// Use 'perms'=>'$user->rights->kreaimmo->level1->level2' if you want your menu with a permission rules
			'target' => '',
			'user' => 2
		);                                // 0=Menu for internal users, 1=external users, 2=both


		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=kreaimmo,fk_leftmenu=kreaimmo_immocost',
			// '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type' => 'left',                            // This is a Left menu entry
			'titre' => 'MenuStatisticsCost',
			'mainmenu' => 'kreaimmo',
			'leftmenu' => 'kreaimmo_immocost_stats_cost',
			'url' => '/kreaimmo/cost/stats.php?type_stats=cost_type',
			'langs' => 'kreaimmo@kreaimmo',
			// Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 1100 + $r,
			'enabled' => '$conf->kreaimmo->enabled',
			// Define condition to show or hide menu entry. Use '$conf->kreaimmo->enabled' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
			'perms' => '1',
			// Use 'perms'=>'$user->rights->kreaimmo->level1->level2' if you want your menu with a permission rules
			'target' => '',
			'user' => 2
		);                                // 0=Menu for internal users, 1=external users, 2=both

		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=kreaimmo,fk_leftmenu=kreaimmo_immocost',
			// '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type' => 'left',                            // This is a Left menu entry
			'titre' => 'MenuStatisticsCostFourn',
			'mainmenu' => 'kreaimmo',
			'leftmenu' => 'kreaimmo_immocost_stats_cost',
			'url' => '/kreaimmo/cost/stats.php?type_stats=fourn_type',
			'langs' => 'kreaimmo@kreaimmo',
			// Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 1100 + $r,
			'enabled' => '$conf->kreaimmo->enabled',
			// Define condition to show or hide menu entry. Use '$conf->kreaimmo->enabled' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
			'perms' => '1',
			// Use 'perms'=>'$user->rights->kreaimmo->level1->level2' if you want your menu with a permission rules
			'target' => '',
			'user' => 2
		);                                // 0=Menu for internal users, 1=external users, 2=both

		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=kreaimmo',
			// '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type' => 'left',                            // This is a Left menu entry
			'titre' => 'MenuSpreadCharges',
			'mainmenu' => 'kreaimmo',
			'leftmenu' => 'kreaimmo_immospreadcharge',
			'url' => '/kreaimmo/cost/immopreadcharges_card.php?action=create',
			'langs' => 'kreaimmo@kreaimmo',
			// Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 1100 + $r,
			'enabled' => '$conf->kreaimmo->enabled',
			// Define condition to show or hide menu entry. Use '$conf->kreaimmo->enabled' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
			'perms' => '1',
			// Use 'perms'=>'$user->rights->kreaimmo->level1->level2' if you want your menu with a permission rules
			'target' => '',
			'user' => 2
		);


		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=kreaimmo',
			// '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type' => 'left',                            // This is a Left menu entry
			'titre' => 'MenuStatisticsResult',
			'mainmenu' => 'kreaimmo',
			'leftmenu' => 'kreaimmo_immocost_stats_result',
			'url' => '/kreaimmo/result/result.php',
			'langs' => 'kreaimmo@kreaimmo',
			// Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 1100 + $r,
			'enabled' => '$conf->kreaimmo->enabled',
			// Define condition to show or hide menu entry. Use '$conf->kreaimmo->enabled' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
			'perms' => '1',
			// Use 'perms'=>'$user->rights->kreaimmo->level1->level2' if you want your menu with a permission rules
			'target' => '',
			'user' => 2
		);                                // 0=Menu for internal users, 1=external users, 2=both

		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=kreaimmo',
			// '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type' => 'left',                            // This is a Left menu entry
			'titre' => 'MenuDictionarie',
			'mainmenu' => 'kreaimmo',
			'leftmenu' => 'kreaimmo_immocost_dictionarie',
			'url' => '/kreaimmo/dict.php',
			'langs' => 'kreaimmo@kreaimmo',
			// Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 1100 + $r,
			'enabled' => '$conf->kreaimmo->enabled',
			// Define condition to show or hide menu entry. Use '$conf->kreaimmo->enabled' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
			'perms' => '1',
			// Use 'perms'=>'$user->rights->kreaimmo->level1->level2' if you want your menu with a permission rules
			'target' => '',
			'user' => 2
		);                                // 0=Menu for internal users, 1=external users, 2=both

	}

	/**
	 *    Function called when module is enabled.
	 *    The init function add constants, boxes, permissions and menus (defined in constructor) into Dolibarr database.
	 *    It also creates data directories
	 *
	 * @param string $options Options when enabling module ('', 'noboxes')
	 * @return     int                1 if OK, 0 if KO
	 */
	public function init($options = '')
	{
		global $langs;
		if ($this->_load_tables('/kreaimmo/sql/') <= 0) {
			return -1;
		}

		$sql = [];
		// Create extrafields
		include_once DOL_DOCUMENT_ROOT . '/core/class/extrafields.class.php';
		$extrafields = new ExtraFields($this->db);

		//$result1=$extrafields->addExtraField('myattr1', "New Attr 1 label", 'boolean', 1,  3, 'thirdparty',   0, 0, '', '', 1, '', 0, 0, '', '', 'kreaimmo@kreaimmo', '$conf->kreaimmo->enabled');
		//$result2=$extrafields->addExtraField('myattr2', "New Attr 2 label", 'varchar', 1, 10, 'project',      0, 0, '', '', 1, '', 0, 0, '', '', 'kreaimmo@kreaimmo', '$conf->kreaimmo->enabled');
		//$result3=$extrafields->addExtraField('myattr3', "New Attr 3 label", 'varchar', 1, 10, 'bank_account', 0, 0, '', '', 1, '', 0, 0, '', '', 'kreaimmo@kreaimmo', '$conf->kreaimmo->enabled');
		//$result4=$extrafields->addExtraField('myattr4', "New Attr 4 label", 'select',  1,  3, 'thirdparty',   0, 1, '', array('options'=>array('code1'=>'Val1','code2'=>'Val2','code3'=>'Val3')), 1 '', 0, 0, '', '', 'kreaimmo@kreaimmo', '$conf->kreaimmo->enabled');
		//$result5=$extrafields->addExtraField('myattr5', "New Attr 5 label", 'text',    1, 10, 'user',         0, 0, '', '', 1, '', 0, 0, '', '', 'kreaimmo@kreaimmo', '$conf->kreaimmo->enabled');

		//		$sql = array(
		//			"REPLACE INTO " . MAIN_DB_PREFIX . "c_kreaimmo_immoreceipt_status (rowid, code, label, active) VALUES
		//					(1, 'STATUS_DRAFT', '" . $langs->trans("Draft") . "', 1),
		//					(2, 'STATUS_VALIDATED', '" . $langs->trans("Validate") . "', 1);"
		//		);

		// Document templates
		$moduledir = 'kreaimmo';
		$myTmpObjects = array();
		$myTmpObjects['KreaImmoCompteur'] = array('includerefgeneration' => 0, 'includedocgeneration' => 0);

		foreach ($myTmpObjects as $myTmpObjectKey => $myTmpObjectArray) {
			if ($myTmpObjectKey == 'KreaImmoCompteur') continue;
			if ($myTmpObjectArray['includerefgeneration']) {
				$src = DOL_DOCUMENT_ROOT . '/install/doctemplates/kreaimmo/template_immocompteurs.odt';
				$dirodt = DOL_DATA_ROOT . '/doctemplates/kreaimmo';
				$dest = $dirodt . '/template_immocompteurs.odt';

				if (file_exists($src) && !file_exists($dest)) {
					require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';
					dol_mkdir($dirodt);
					$result = dol_copy($src, $dest, 0, 0);
					if ($result < 0) {
						$langs->load("errors");
						$this->error = $langs->trans('ErrorFailToCopyFile', $src, $dest);
						return 0;
					}
				}

				$sql = array_merge($sql, array(
					"DELETE FROM " . MAIN_DB_PREFIX . "document_model WHERE nom = 'standard_" . strtolower($myTmpObjectKey) . "' AND type = '" . strtolower($myTmpObjectKey) . "' AND entity = " . $conf->entity,
					"INSERT INTO " . MAIN_DB_PREFIX . "document_model (nom, type, entity) VALUES('standard_" . strtolower($myTmpObjectKey) . "','" . strtolower($myTmpObjectKey) . "'," . $conf->entity . ")",
					"DELETE FROM " . MAIN_DB_PREFIX . "document_model WHERE nom = 'generic_" . strtolower($myTmpObjectKey) . "_odt' AND type = '" . strtolower($myTmpObjectKey) . "' AND entity = " . $conf->entity,
					"INSERT INTO " . MAIN_DB_PREFIX . "document_model (nom, type, entity) VALUES('generic_" . strtolower($myTmpObjectKey) . "_odt', '" . strtolower($myTmpObjectKey) . "', " . $conf->entity . ")"
				));
			}
		}

		return $this->_init($sql, $options);
	}

	/**
	 *    Function called when module is disabled.
	 *    Remove from database constants, boxes and permissions from Dolibarr database.
	 *    Data directories are not deleted
	 *
	 * @param string $options Options when enabling module ('', 'noboxes')
	 * @return     int                1 if OK, 0 if KO
	 */
	public function remove($options = '')
	{
		$sql = array();
		return $this->_remove($sql, $options);
	}
}
