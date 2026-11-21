<?php
/* Copyright (C) 2004      Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2005-2006 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2013      Olivier Geffroy      <jeff@jeffinfo.com>
 * Copyright (C) 2014      Florian Henry        <florian.henry@open-concept.pro>
 * Copyright (C) 2018-2019 Philippe GRAND 		<philippe.grand@atoo-net.com>
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
 * \file htdocs/compta/ventilation/card.php
 * \ingroup compta
 * \brief Page fiche ventilation
 */

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) $res = @include($_SERVER["CONTEXT_DOCUMENT_ROOT"] . "/main.inc.php");
// Try main.inc.php into web root detected using web root caluclated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1)) . "/main.inc.php")) $res = @include(substr($tmp, 0, ($i + 1)) . "/main.inc.php");
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1))) . "/main.inc.php")) $res = @include(dirname(substr($tmp, 0, ($i + 1))) . "/main.inc.php");
// Try main.inc.php using relative path
if (!$res && file_exists("../main.inc.php")) $res = @include("../main.inc.php");
if (!$res && file_exists("../../main.inc.php")) $res = @include("../../main.inc.php");
if (!$res && file_exists("../../../main.inc.php")) $res = @include("../../../main.inc.php");
if (!$res) die("Include of main fails");

dol_include_once('/kreaimmo/lib/kreaimmo_page.lib.php');
kreaimmoRequirePageAccess('cost');

dol_include_once("/kreaimmo/class/kreaimmocost.class.php");
dol_include_once("/kreaimmo/class/kreaimmoproperty.class.php");
dol_include_once("/kreaimmo/class/kreaimmoreceipt.class.php");
dol_include_once("/kreaimmo/class/kreaimmocost_type.class.php");
dol_include_once("/kreaimmo/class/kreaimmocost_detail.class.php");
dol_include_once('/kreaimmo/class/kreaimmorent.class.php');
dol_include_once('/kreaimmo/lib/immocost.lib.php');
dol_include_once('/kreaimmo/class/html.formkreaimmo.class.php');
dol_include_once('/kreaimmo/class/kreaimmospreadzone.class.php');

$res = dol_include_once("/kreaimmo/core/modules/kreaimmo/modules_kreaimmo.php");
if (!$res)
	die("Include of kreaimmo");

// Load traductions files requiredby by page
$langs->loadLangs(array("kreaimmo@kreaimmo", "bills", "compta"));

// Get parameters
$mesg = '';
$id = GETPOST('id', 'int');
$action = GETPOST('action');


$htmlimmo = new FormKreaImmo($db);
$costDetail = new KreaImmoCost_Detail($db);

/*
 * Actions
 */

/*
 * Add ventil charge
 */

if ($action == 'ventil') {
	$selectedRows = GETPOST('mesCasesCochees');

	$processedRowCount = 0;
	$error = 0;

	$db->begin();

	foreach ($selectedRows as $selectedRow => $propertyId) {
		$costDetail = new KreaImmoCost_Detail($db);

		// main info loyer
		$costDetail->amount = GETPOST('amount_' . $propertyId);
		$costDetail->fk_cost_type = GETPOST('typecharge');
		$costDetail->fk_immocost = $id;
		$costDetail->fk_property = $propertyId;

		$result = $costDetail->create($user);

		if ($result < 0) {
			setEventMessages($costDetail->error, $costDetail->errors, 'errors');
			$error++;
		}
		$processedRowCount++;
	}

	if (empty($error)) {
		$cost = new KreaImmoCost($db);
		$result = $cost->fetch($id);
		if ($result < 0) {
			setEventMessages($cost->error, $cost->errors, 'errors');
			$error++;
		}
		$cost->dispatch = 1;
		$result = $cost->update($user);
		if ($result < 0) {
			setEventMessages($cost->error, $cost->errors, 'errors');
			$error++;
		}

	}

	if (empty($error)) {
		$db->commit();
		setEventMessages($langs->transnoentities("ReceiptPaymentsAdded"), null, 'mesgs');
		Header("Location: " . dol_buildpath('/kreaimmo/cost/immocost_card.php', 1) . "?id=" . $id);
	} else {
		$db->rollback();
	}
}

/*
 * View
 *
 */

$form = new Form($db);
$rentReceipt = new KreaImmoReceipt($db);

$help_url = '';
llxheader('', $langs->trans("newventilcharge"), $help_url);

$cost = new KreaImmoCost ($db);
$result = $cost->fetch($id);

if ($result < 0) {
	setEventMessages($cost->error, $cost->errors, 'errors');
}

$property = new KreaImmoProperty($db);
$result = $property->fetch($cost->fk_property);

if ($result < 0) {
	setEventMessages($cost->error, $cost->errors, 'errors');
}

if (!empty($property->id)) {
	$result = $property->fetchAllByBuilding();

	if ($result < 0) {
		setEventMessages($property->error, $property->errors, 'errors');
	}
}

if ($id > 0) {
	$head = kreaimmo_immocostPrepareHead($cost);

	print dol_get_fiche_head($head, 'repartition', $langs->trans("KreaImmoCost"), -1, 'kreaimmo@kreaimmo');

	// Object Charge
	// ------------------------------------------------------------
	$linkback = '<a href="' . dol_buildpath('/kreaimmo/cost/immocost_list.php', 1) . '?restore_lastsearch_values=1' . (!empty($socid) ? '&socid=' . $socid : '') . '">' . $langs->trans("BackToList") . '</a>';

	$morehtmlref = '<div class="refidno">';
	$morehtmlref .= '</div>';

	dol_banner_tab($cost, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref);

	/*
	 * List properties
	 */

	$i = 0;
	$total = 0;
	print '<form action="' . $_SERVER['PHP_SELF'] . '?id=' . $id . '" method="POST">';
	print '<input type="hidden" name="token" value="' . $_SESSION['newtoken'] . '">';
	print '<input type="hidden" name="action" value="ventil">';


	print '<br><div class="fichecenter"><div class="underbanner clearboth"></div>';
	print '<table class="border tableforfield" width="100%"><tbody>';
	print '<tr class="liste_titre">';
	print '<td>' . $langs->trans('Property') . '</td>';
	print '<td>' . $langs->trans('ImmoCostVentilNameProperty') . '</td>';
	print '<td>' . $langs->trans('MilliemeTheo') . '</td>';
	print '<td>' . $langs->trans('MilliemeCalc') . '</td>';
	print '<td>' . $langs->trans('ImmoCostVentilAmount') . '</td>';
	print '<td>' . $langs->trans('ImmoCostVentilSelect') . '</td>';
	print "</tr>\n";

	$i = 0;
	if (count($property->lines) > 0) {
		$amount = 0;
		$propertyIdsWithReceipts = array();
		// Calcul amount par appartement
		foreach ($property->lines as $propertyLine) {
			// Trouve les loyer existant sur cee lieu pour cette période
			$result = $rentReceipt->fetchByLocalId($propertyLine->id, array(
				'insidedateloyer' => $cost->datec
			));
			if ($result < 0) {
				setEventMessages($rentReceipt->error, $rentReceipt->errors, 'errors');
			}
			if (count($rentReceipt->lines) > 0) {
				$propertyIdsWithReceipts[$propertyLine->id] = $propertyLine->id;
			}
		}

		//Récupére le milliéme
		$allocatedShares=array();
		$allPropertyShares=array();
		$unallocatedShare=0;
		$unallocatedShareCount=0;
		$spreadData = new KreaImmoSpreadZone($db);
		if (count($property->lines) > 0) {
			foreach ($property->lines as $propertyLine) {
				$data = $spreadData->fetchAll('', '', 0, 0, array('t.fk_property_parent' => $property->id, 't.fk_property_child' => $propertyLine->id));
				if (!is_array($data) && $data < 0) {
					setEventMessages($spreadData->error, $spreadData->errors, 'errors');
				} elseif (count($data) > 1) {
					setEventMessages('ProblemInTableSpread', null, 'errors');
				} elseif (!empty($data)) {
					$allPropertyShares[$propertyLine->id] = reset($data)->percent_application;
					if (array_key_exists($propertyLine->id, $propertyIdsWithReceipts)) {
						//building is rent so we get the millieme
						$allocatedShares[$propertyLine->id] = reset($data)->percent_application;
					} else {
						//building is not rent so we get it to affect this millieme to all other that are rented
						$unallocatedShare += (int) reset($data)->percent_application;
						$unallocatedShareCount++;
					}
				}
			}
		}
		if (!empty($allocatedShares) && $unallocatedShareCount>0) {
			foreach ($allocatedShares as $propertyId=>$originalShare) {
				//We reafect millimeme the not located rent to to all other rented
				$allocatedShares[$propertyId] = $originalShare + ($unallocatedShare/count($propertyIdsWithReceipts));
			}
		}


		// Affichage
		foreach ($property->lines as $propertyLine) {
			//var_dump($propertyLine);

			print '<tr class="oddeven">';
			print '<td>' . $propertyLine->label . '</td>';
			print '<td>';
			if (array_key_exists($propertyLine->id, $propertyIdsWithReceipts)) {
				print ' ' . img_picto($langs->trans('Louer'), 'statut4');
			} else {
				print ' ' . img_picto($langs->trans('Vide'), 'statut0');
			}
			print '</td>';
			print '<td>' . ((array_key_exists($propertyLine->id, $allPropertyShares)) ? price($allPropertyShares[$propertyLine->id]) : ''). '</td>';
			print '<td>' . ((array_key_exists($propertyLine->id, $allocatedShares)) ? price($allocatedShares[$propertyLine->id]) : ''). '</td>';


			if (array_key_exists($propertyLine->id, $propertyIdsWithReceipts)) {
				if (array_key_exists($propertyLine->id, $allocatedShares)) {
					$amount = ($cost->amount / 1000) * $allocatedShares[$propertyLine->id];
				} elseif (count($propertyIdsWithReceipts) > 0) {
					$amount = $cost->amount / count($propertyIdsWithReceipts);
				}
				print '<td><input name="amount_' . $propertyLine->id . '" value="' . price($amount) . '" size="30"></td>';
			} else {
				print '<td><input name="amount_' . $propertyLine->id . '" value="" size="30"></td>';
			}

			// Colonne choix appartement
			print '<td align="center">';

			print '<input type="checkbox" name="mesCasesCochees[]" value="' . $propertyLine->id . '"' . ((array_key_exists($propertyLine->id, $propertyIdsWithReceipts)) ? "checked" : "") . '/>';
			print '</td>';
			print '</tr>';

			$i++;
		}
	}
	print '<tr><td colspan="5" align="center">' . $langs->trans('Type') . $costDetail->showInputField(array(), 'fk_cost_type', '', '', '', '', 0).'</td></tr>';
	print '<tr><td colspan="5" align="center"><input class="button" type="submit" value="' . $langs->trans("ImmoCostVentilAddRepartition") . '" name="addrepartition"></td></tr>';

	print "</tbody></table></div>";

	print '</form>';
}

llxFooter();

$db->close();
