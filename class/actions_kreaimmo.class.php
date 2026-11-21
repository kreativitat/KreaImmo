<?php
/* Copyright (C) 2018-2019 Philippe GRAND <philippe.grand@atoo-net.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * \file    kreaimmo/class/actions_kreaimmo.class.php
 * \ingroup kreaimmo
 * \brief   Example hook overload.
 *
 * Put detailed description here.
 */

/**
 * Class ActionsKreaImmo
 */
class ActionsKreaImmo
{

    /**
     * @var DoliDB Database handler.
     */
    public $db;

    /**
     * @var string Error
     */
    public $error = '';

    /**
     * @var array Errors
     */
    public $errors = array();

    /**
     * @var array Hook results. Propagated to $hookmanager->resArray for later reuse
     */
    public $results = array();

    /**
     * @var string String displayed by executeHook() immediately after return
     */
    public $resprints;

    /**
     * Constructor
     *
     *  @param		DoliDB		$db      Database handler
     */
    public function __construct($db)
    {
        $this->db = $db;
    }

	/** Include shared reference values without sharing company business records. */
	public function hookGetEntity($parameters, &$object, &$action, $hookmanager)
	{
		global $conf;
		$this->resprints = '';
		$shared = array('kreaimmo_immoproperty_type', 'c_kreaimmo_immoproperty_type',
			'kreaimmo_immorent_type', 'c_kreaimmo_immorent_type',
			'c_kreaimmo_builtdate', 'c_kreaimmo_juridique',
			'c_kreaimmo_target', 'c_kreaimmo_diagnostic',
			'kreaimmo_immocompteur_type', 'c_kreaimmo_immocompteur_type');
		if (!in_array($parameters['element'], $shared, true)) {
			return 0;
		}
		$this->resprints = '0,'.(int) $conf->entity;
		return 1;
	}

	/**
	 * Resolve module-owned elements without replacing native or other module objects.
	 *
	 * @param array{context?:string,elementType?:string,elementProperties?:array<string,mixed>} $parameters Hook parameters.
	 * @param CommonObject|null $object Current object.
	 * @param string $action Current action.
	 * @param HookManager $hookmanager Hook manager.
	 * @return int 1 when the element belongs to this module, otherwise 0.
	 */
	public function getElementProperties($parameters, &$object, &$action, $hookmanager)
	{
		$this->results = array();
		$contexts = explode(':', isset($parameters['context']) ? $parameters['context'] : '');
		if (!in_array('elementproperties', $contexts, true) || empty($parameters['elementType'])) {
			return 0;
		}

		$qualifiedElement = explode('@', dol_strtolower($parameters['elementType']));
		if (count($qualifiedElement) > 2 || (isset($qualifiedElement[1]) && $qualifiedElement[1] !== 'kreaimmo')) {
			return 0;
		}
		$elementType = $qualifiedElement[0];
		$elements = array(
			'kreaimmo_immobuilding' => array('KreaImmoBuilding', 'kreaimmobuilding', 'kreaimmo_building'),
			'kreaimmo_immocompteur' => array('KreaImmoCompteur', 'kreaimmocompteur', 'kreaimmo_immocompteur'),
			'kreaimmo_immocompteur_cost' => array('KreaImmoCompteur_Cost', 'kreaimmocompteur_cost', 'kreaimmo_immocompteur_cost'),
			'kreaimmo_immocompteur_type' => array('KreaImmoCompteur_Type', 'kreaimmocompteur_type', 'c_kreaimmo_immocompteur_type'),
			'kreaimmo_immocost' => array('KreaImmoCost', 'kreaimmocost', 'kreaimmo_immocost'),
			'kreaimmo_immocost_detail' => array('KreaImmoCost_Detail', 'kreaimmocost_detail', 'kreaimmo_immocost_detail'),
			'kreaimmo_immocost_type' => array('KreaImmoCost_Type', 'kreaimmocost_type', 'kreaimmo_immocost_type'),
			'kreaimmo_immoindice' => array('KreaImmoIndice', 'kreaimmoindice', 'kreaimmo_immoindice'),
			'kreaimmo_immoowner' => array('KreaImmoOwner', 'kreaimmoowner', 'kreaimmo_immoowner'),
			'kreaimmo_immoowner_type' => array('KreaImmoOwner_Type', 'kreaimmoowner_type', 'kreaimmo_immoowner_type'),
			'kreaimmo_immopayment' => array('KreaImmoPayment', 'kreaimmopayment', 'kreaimmo_immopayment'),
			'kreaimmo_immoproperty' => array('KreaImmoProperty', 'kreaimmoproperty', 'kreaimmo_immoproperty'),
			'kreaimmo_immoproperty_type' => array('KreaImmoProperty_Type', 'kreaimmoproperty_type', 'c_kreaimmo_immoproperty_type'),
			'kreaimmo_immoreceipt' => array('KreaImmoReceipt', 'kreaimmoreceipt', 'kreaimmo_immoreceipt'),
			'kreaimmo_immorent' => array('KreaImmoRent', 'kreaimmorent', 'kreaimmo_immorent'),
			'kreaimmo_immorenter' => array('KreaImmoRenter', 'kreaimmorenter', 'kreaimmo_immorenter'),
			'kreaimmo_immorenter_type' => array('KreaImmoRenter_Type', 'kreaimmorenter_type', 'kreaimmo_immorenter_type'),
			'kreaimmo_immospreadzone' => array('KreaImmoSpreadZone', 'kreaimmospreadzone', 'kreaimmo_immospreadzone'),
		);

		foreach ($elements as $element => $definition) {
			if ($elementType !== $element && $elementType !== dol_strtolower($definition[0])) {
				continue;
			}

			$this->results = isset($parameters['elementProperties']) ? $parameters['elementProperties'] : array();
			$this->results['module'] = 'kreaimmo';
			$this->results['element'] = $element;
			$this->results['table_element'] = $definition[2];
			$this->results['subelement'] = $definition[0];
			$this->results['classfile'] = $definition[1];
			$this->results['classname'] = $definition[0];
			$this->results['classpath'] = 'kreaimmo/class';
			$this->results['dir_output'] = getMultidirOutput(null, 'kreaimmo');
			$this->results['dir_temp'] = getMultidirTemp(null, 'kreaimmo');
			return 1;
		}

		return 0;
	}

    /**
     * Overloading the doActions function : replacing the parent's function with the one below
     *
     * @param   array()         $parameters     Hook metadatas (context, etc...)
     * @param   CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
     * @param   string          $action         Current action (if set). Generally create or edit or null
     * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
     * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
     */
//    public function doActions($parameters, &$object, &$action, $hookmanager)
//    {
//        global $conf, $user, $langs;
//
//        $error = 0; // Error counter
//
//        /* print_r($parameters); print_r($object); echo "action: " . $action; */
//        if (in_array($parameters['currentcontext'], array('somecontext1', 'somecontext2'))) {     // do something only for the context 'somecontext1' or 'somecontext2'
//            // Do what you want here...
//            // You can for example call global vars like $fieldstosearchall to overwrite them, or update database depending on $action and $_POST values.
//        }
//
//        if (!$error) {
//            $this->results   = array('myreturn' => 999);
//            $this->resprints = 'A text to show';
//            return 0;                                    // or return 1 to replace standard code
//        } else {
//            $this->errors[] = 'Error message';
//            return -1;
//        }
//    }

    /**
     * Overloading the doActions function : replacing the parent's function with the one below
     *
     * @param   array()         $parameters     Hook metadatas (context, etc...)
     * @param   CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
     * @param   string          $action         Current action (if set). Generally create or edit or null
     * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
     * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
     */
//    public function doMassActions($parameters, &$object, &$action, $hookmanager)
//    {
//        global $conf, $user, $langs;
//
//        $error = 0; // Error counter
//
//        /* print_r($parameters); print_r($object); echo "action: " . $action; */
//        if (in_array($parameters['currentcontext'], array('somecontext1', 'somecontext2'))) {  // do something only for the context 'somecontext1' or 'somecontext2'
//            foreach ($parameters['toselect'] as $objectid) {
//                // Do action on each object id
//            }
//        }
//
//        if (!$error) {
//            $this->results   = array('myreturn' => 999);
//            $this->resprints = 'A text to show';
//            return 0;                                    // or return 1 to replace standard code
//        } else {
//            $this->errors[] = 'Error message';
//            return -1;
//        }
//    }

    /**
     * Overloading the addMoreMassActions function : replacing the parent's function with the one below
     *
     * @param   array()         $parameters     Hook metadatas (context, etc...)
     * @param   CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
     * @param   string          $action         Current action (if set). Generally create or edit or null
     * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
     * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
     */
//    public function addMoreMassActions($parameters, &$object, &$action, $hookmanager)
//    {
//        global $conf, $user, $langs;
//
//        $error = 0; // Error counter
//
//        /* print_r($parameters); print_r($object); echo "action: " . $action; */
//        if (in_array($parameters['currentcontext'], array('somecontext1', 'somecontext2'))) {  // do something only for the context 'somecontext1' or 'somecontext2'
//            $this->resprints = '<option value="0"' . ($disabled ? ' disabled="disabled"' : '') . '>' . $langs->trans("KreaImmoMassAction") . '</option>';
//        }
//
//        if (!$error) {
//            return 0;                                    // or return 1 to replace standard code
//        } else {
//            $this->errors[] = 'Error message';
//            return -1;
//        }
//    }

    /**
     * Execute action
     *
     * @param	array	$parameters		Array of parameters
     * @param   Object	$object		   	Object output on PDF
     * @param   string	$action     	'add', 'update', 'view'
     * @return  int 		        	<0 if KO,
     *                          		=0 if OK but we want to process standard actions too,
     *  	                            >0 if OK and we want to replace standard actions.
     */
//    function beforePDFCreation($parameters, &$object, &$action)
//    {
//        global $langs, $conf;
//        global $hookmanager;
//
//        $outputlangs = $langs;
//
//        $ret     = 0;
//        $deltemp = array();
//        dol_syslog(get_class($this) . '::executeHooks action=' . $action);
//
//        /* print_r($parameters); print_r($object); echo "action: " . $action; */
//        if (in_array($parameters['currentcontext'], array('somecontext1', 'somecontext2'))) {  // do something only for the context 'somecontext1' or 'somecontext2'
//        }
//
//        return $ret;
//    }

    /**
     * Execute action
     *
     * @param	array	$parameters		Array of parameters
     * @param   Object	$pdfhandler   	PDF builder handler
     * @param   string	$action     	'add', 'update', 'view'
     * @return  int 		        	<0 if KO,
     *                          		=0 if OK but we want to process standard actions too,
     *  	                            >0 if OK and we want to replace standard actions.
     */
//    function afterPDFCreation($parameters, &$pdfhandler, &$action)
//    {
//        global $langs, $conf;
//        global $hookmanager;
//
//        $outputlangs = $langs;
//
//        $ret     = 0;
//        $deltemp = array();
//        dol_syslog(get_class($this) . '::executeHooks action=' . $action);
//
//        /* print_r($parameters); print_r($object); echo "action: " . $action; */
//        if (in_array($parameters['currentcontext'], array('somecontext1', 'somecontext2'))) {  // do something only for the context 'somecontext1' or 'somecontext2'
//        }
//
//        return $ret;
//    }
    /* Add here any other hooked methods... */

    function addToLandingPageList($parameters, &$object, &$action, $hookmanager)
    {
        if ($object->rights->kreaimmo->read) {
            $parameters[dol_buildpath('/custom/kreaimmo/rent/immorent_list.php', 1) . '?mainmenu=kreaimmo&leftmenu='] = 'KreaImmo';
            $this->results                                                                                                  = $parameters;
            return 1;
        }
    }

    /**
     * Do something
     *
     * @param   int				$param				0=True url, 1=Url formated with colors
     * @return	string								Url string
     */
    function addSearchEntry($parameters, $object, $action, $hookmanager)
    {
        global $user, $langs;
        $search_boxvalue = ''; // ??????????
        if ($user->rights->kreaimmo->read) {
            if (in_array($parameters['currentcontext'], array('globalcard', 'searchform', 'leftblock'))) {  // do something only for the context 'somecontext1' or 'somecontext2'
                $langs->load("kreaimmo@kreaimmo");
                $this->results = [
                    ['position' => 200, 'shortcut' => 'U', 'img' => 'immorenter.png@kreaimmo', 'label' => $langs->trans("Renter"), 'text' => img_picto('', 'immorenter@kreaimmo', 'class="pictofixedwidth"') . ' ' . $langs->trans("Renter", $search_boxvalue), 'url' => dol_buildpath('/kreaimmo/renter/immorenter_list.php', 1) . ($search_boxvalue ? '?search_all=' . urlencode($search_boxvalue) : '')],
                    ['position' => 200,
                        'shortcut' => 'U',
                        'img' => 'immoproperty.png@kreaimmo',
                        'label' => $langs->trans("Property"),
                        'text' => img_picto('', 'immoproperty@kreaimmo', 'class="pictofixedwidth"')
                        . ' ' . $langs->trans("Property", $search_boxvalue),
                        'url' => dol_buildpath('/kreaimmo/property/immoproperty_list.php', 1)
                        . ($search_boxvalue ? '?search_all=' . urlencode($search_boxvalue) : '')
                    ],
                    ['position' => 200, 'shortcut' => 'U', 'img' => 'immoowner.png@kreaimmo', 'label' => $langs->trans("Owner"), 'text' => img_picto('', 'immoowner@kreaimmo', 'class="pictofixedwidth"') . ' ' . $langs->trans("Owner", $search_boxvalue), 'url' => dol_buildpath('/kreaimmo/owner/immoowner_list.php', 1) . ($search_boxvalue ? '?search_all=' . urlencode($search_boxvalue) : '')],
                ];
            }
        }
    }
}
