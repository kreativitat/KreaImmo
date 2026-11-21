<?php
/* Copyright (C) 2013-2016	Olivier Geffroy    <jeff@jeffinfo.com>
 * Copyright (C) 2015-2016  Alexandre Spangaro <aspangaro@zendsi.com>
 * Copyright (C) 2018-2019  Philippe GRAND 	   <philippe.grand@atoo-net.com>
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
 * \file		kreaimmo/core/modules/modules_kreaimmo.php
 * \ingroup		kreaimmo
 * \brief		File that contain parent class for projects models
 * 				and parent class for projects numbering models
 */
require_once (DOL_DOCUMENT_ROOT . "/core/class/commondocgenerator.class.php");

/**
 *	Parent class for kreaimmo models
 */
abstract class ModelePDFKreaImmo extends CommonDocGenerator
{
	public $error = '';

	/**
	 * Return list of active generation modules
	 *
	 * @param DoliDB $db handler
	 * @param string $maxfilenamelength length of value to show
	 * @return array of templates
	 */
	static function liste_modeles($db, $maxfilenamelength = 0)
	{
		global $conf;

		$type='kreaimmo';
		$models=array();

		include_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
		$models=getListOfModels($db,$type,$maxfilenamelength);

		return $models;
	}
}

/**
 * \brief Crée un document PDF
 * \param db objet base de donnee
 * \param modele modele à utiliser
 * \param		outputlangs		objet lang a utiliser pour traduction
 * \return int <0 if KO, >0 if OK
 */
function kreaimmo_pdf_create($db, $id, $message, $modelType, $outputlangs, $file) {
	global $conf, $langs;
	$langs->load ( 'kreaimmo@kreaimmo' );

	// Charge le modele
	$modelFile = dol_buildpath ( '/kreaimmo/core/modules/kreaimmo/doc/pdf_' . $modelType . '.modules.php' );

	if (file_exists ( $modelFile )) {
		require_once ($modelFile);

		$classname = "pdf_" . $modelType;

		$obj = new $classname ( $db );
		$obj->message = $message;

		// We save charset_output to restore it because write_file can change it if needed for
		// output format that does not support UTF8.
		$sav_charset_output = $outputlangs->charset_output;
		if ($obj->write_file ( $id, $outputlangs, $file, $socid, $letter ) > 0) {
			$outputlangs->charset_output = $sav_charset_output;
			return 1;
		} else {
			$outputlangs->charset_output = $sav_charset_output;
			dol_print_error ( $db, "pdf_create Error: " . $obj->error );
			return - 1;
		}
	} else {
		dol_print_error ( '', $langs->trans ( "Error" ) . " " . $langs->trans ( "ErrorFileDoesNotExists", $file ) );
		return - 1;
	}
}


/**
 *  Classe mere des modeles de numerotation des references de kreaimmo
 */
abstract class ModeleNumRefKreaImmo
{
	/**
	 * @var string Error code (or message)
	 */
	public $error='';

	/**
	 *  Return if a model can be used or not
	 *
	 *  @return		boolean     true if model can be used
	 */
	function isEnabled()
	{
		return true;
	}

	/**
	 *  Renvoi la description par defaut du modele de numerotation
	 *
	 *  @return     string      Texte descripif
	 */
	function info()
	{
		global $langs;
		$langs->load("kreaimmo@kreaimmo");
		return $langs->trans("NoDescription");
	}

	/**
	 *  Renvoi un exemple de numerotation
	 *
	 *  @return     string      Example
	 */
	function getExample()
	{
		global $langs;
		$langs->load("kreaimmo@kreaimmo");
		return $langs->trans("NoExample");
	}

	/**
	 *  Test si les numeros deja en vigueur dans la base ne provoquent pas de
	 *  de conflits qui empecheraient cette numerotation de fonctionner.
	 *
	 *  @return     boolean     false si conflit, true si ok
	 */
	function canBeActivated()
	{
		return true;
	}

	/**
	 *  Renvoi prochaine valeur attribuee
	 *
	 *	@param	Societe		$thirdParty		Object third party
	 *	@param	Receipt		$object		Object receipt
	 *	@return	string					Valeur
	 */
	function getNextValue($object)
	{
		global $langs;
		return $langs->trans("NotAvailable");
	}

	/**
	 *  Renvoi version du module numerotation
	 *
	 *  @return     string      Valeur
	 */
	function getVersion()
	{
		global $langs;
		$langs->load("admin");

		if ($this->version == 'development') return $langs->trans("VersionDevelopment");
		if ($this->version == 'experimental') return $langs->trans("VersionExperimental");
		if ($this->version == 'dolibarr') return DOL_VERSION;
		if ($this->version) return $this->version;
		return $langs->trans("NotAvailable");
	}
}

/**
 *  Create an receipt document on disk using template defined into KREAIMMO_ADDON_PDF
 *
 *  @param	DoliDB		$db  			objet base de donnees
 *  @param	Object		$object			Object kreaimmo
 *  @param	string		$modelName			force le modele a utiliser ('' par defaut)
 *  @param	Translate	$outputlangs	objet lang a utiliser pour traduction
 *  @param  int			$hidedetails    Hide details of lines
 *  @param  int			$hidedesc       Hide description
 *  @param  int			$hideref        Hide ref
 *  @return int         				0 if KO, 1 if OK

function kreaimmo_create($db, $object, $modelName, $outputlangs, $hidedetails=0, $hidedesc=0, $hideref=0)
{
    // phpcs:enable
	global $conf, $langs, $user;
	$langs->load("kreaimmo@kreaimmo");

	$error=0;

	$srctemplatepath='';

	// Positionne modele sur le nom du modele de fichinter a utiliser
	if (! dol_strlen($modelName))
	{
		if (! empty($conf->global->KREAIMMO_ADDON_PDF))
		{
			$modelName = $conf->global->KREAIMMO_ADDON_PDF;
		}
		else
		{
			$modelName = 'kreaimmo_quittance';
		}
	}

	// If selected modele is a filename template (then $modelName="modelname:filename")
	$tmp=explode(':',$modelName,2);
    if (! empty($tmp[1]))
    {
        $modelName=$tmp[0];
        $srctemplatepath=$tmp[1];
    }

	// Search template files
	$file=''; $classname=''; $filefound=0;
	$dirmodels=array('/');
	if (is_array($conf->modules_parts['models'])) $dirmodels=array_merge($dirmodels,$conf->modules_parts['models']);
	foreach($dirmodels as $reldir)
	{
		foreach(array('doc','pdf') as $prefix)
		{
			$file = $prefix."_".$modelName.".modules.php";

			// On verifie l'emplacement du modele
			foreach(array('kreaimmo_quittance','bail') as $spessificdir)
			{
				$file=dol_buildpath($reldir."kreaimmo/core/modules/kreaimmo/doc/".$spessificdir.'/'.$file,0);
			}
			if (file_exists($file))
			{
				$filefound=1;
				$classname=$prefix.'_'.$modelName;
				break;
			}
		}
		if ($filefound) break;
    }

	// Charge le modele
	if ($filefound)
	{
		require_once $file;

		$obj = new $classname($db);

		// We save charset_output to restore it because write_file can change it if needed for
		// output format that does not support UTF8.
		$sav_charset_output=$outputlangs->charset_output;
		if ($obj->write_file($object, $outputlangs, $srctemplatepath, $hidedetails, $hidedesc, $hideref) > 0)
		{
			$outputlangs->charset_output=$sav_charset_output;

			// We delete old preview
			require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
			dol_delete_preview($object);

			return 1;
		}
		else
		{
			$outputlangs->charset_output=$sav_charset_output;
			dol_print_error($db,"kreaimmo_pdf_create Error: ".$obj->error);
			return 0;
		}
	}
	else
	{
		print $langs->trans("Error")." ".$langs->trans("ErrorFileDoesNotExists",$file);
		return 0;
	}
}*/

/**
 * \brief Crée un document PDF
 * \param db objet base de donnees
 * \param modele modele à utiliser
 * \param		outputlangs		objet lang a utiliser pour traduction
 * \return int <0 if KO, >0 if OK
 */
function kreaimmo_quittance_pdf_create($db, $id, $message, $modelType, $outputlangs, $file)
{
	global $conf, $langs;
	$langs->load ( 'kreaimmo@kreaimmo' );

	// Charge le modele
	$modelFile = dol_buildpath ( '/kreaimmo/core/modules/kreaimmo/doc/pdf_' . $modelType . '.modules.php' );

	if (file_exists ( $modelFile )) {
		require_once ($modelFile);

		$classname = "pdf_" . $modelType;

		$obj = new $classname ( $db );
		$obj->message = $message;

		// We save charset_output to restore it because write_file can change it if needed for
		// output format that does not support UTF8.
		$sav_charset_output = $outputlangs->charset_output;
		if ($obj->write_file ( $id, $outputlangs, $file, $socid, $letter ) > 0) {
			$outputlangs->charset_output = $sav_charset_output;
			return 1;
		} else {
			$outputlangs->charset_output = $sav_charset_output;
			dol_print_error ( $db, "pdf_create Error: " . $obj->error );
			return - 1;
		}
	} else {
		dol_print_error ( '', $langs->trans ( "Error" ) . " " . $langs->trans ( "ErrorFileDoesNotExists", $file ) );
		return - 1;
	}
}

/**
 * \brief Crée un document PDF
 * \param db objet base de donnees
 * \param modele modele à utiliser
 * \param		outputlangs		objet lang a utiliser pour traduction
 * \return int <0 if KO, >0 if OK
 */
function kreaimmo_bail_vide_pdf_create($db, $year, $modelType, $outputlangs, $filedir, $filename) {
	global $conf, $langs;
	$langs->load ( 'kreaimmo@kreaimmo' );

	// Charge le modele
	$modelFile = dol_buildpath ( '/kreaimmo/core/modules/kreaimmo/doc/pdf_' . $modelType . '.modules.php' );

	if (file_exists ( $modelFile )) {
		require_once ($modelFile);

		$classname = "pdf_" . $modelType;

		$obj = new $classname ( $db );
		$obj->message = $message;

		// We save charset_output to restore it because write_file can change it if needed for
		// output format that does not support UTF8.
		$sav_charset_output = $outputlangs->charset_output;
		if ($obj->write_file ( $year, $outputlangs, $filedir, $filename) > 0) {
			$outputlangs->charset_output = $sav_charset_output;
			return 1;
		} else {
			$outputlangs->charset_output = $sav_charset_output;
			dol_print_error ( $db, "pdf_create Error: " . $obj->error );
			return - 1;
		}
	} else {
		dol_print_error ( '', $langs->trans ( "Error" ) . " " . $langs->trans ( "ErrorFileDoesNotExists", $file ) );
		return - 1;
	}
}
