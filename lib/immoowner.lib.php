<?php
 /* Copyright (C) 2018-2019 Philippe GRAND 	<philippe.grand@atoo-net.com>
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
 * \file    lib/immoowner.lib.php
 * \ingroup kreaimmo
 * \brief   Library files with common functions for KreaImmoOwner
 */

/**
 * Prepare array of tabs for KreaImmoOwner
 *
 * @param	KreaImmoOwner	$object		KreaImmoOwner
 * @return 	array					Array of tabs
 */
function kreaimmo_immoownerPrepareHead($object)
{
	global $db, $langs, $conf;

	$langs->load("kreaimmo@kreaimmo");

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath("/kreaimmo/owner/immoowner_card.php", 1).'?id='.$object->id;
	$head[$h][1] = $langs->trans("Card");
	$head[$h][2] = 'card';
	$h++;

	if (isset($object->fields['note_public']) || isset($object->fields['note_private']))
	{
		$noteCount = 0;
		if (!empty($object->note_private)) $noteCount++;
		if (!empty($object->note_public)) $noteCount++;
		$head[$h][0] = dol_buildpath('/kreaimmo/owner/immoowner_note.php', 1).'?id='.$object->id;
		$head[$h][1] = $langs->trans('Notes');
		if ($noteCount > 0) $head[$h][1].= ' <span class="badge">'.$noteCount.'</span>';
		$head[$h][2] = 'note';
		$h++;
	}

	require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
	require_once DOL_DOCUMENT_ROOT.'/core/class/link.class.php';
	$upload_dir = $conf->kreaimmo->dir_output . "/owner/" . dol_sanitizeFileName($object->ref);
	$fileCount = count(dol_dir_list($upload_dir,'files',0,'','(\.meta|_preview.*\.png)$'));
	$linkCount=Link::count($db, $object->element, $object->id);
	$head[$h][0] = dol_buildpath("/kreaimmo/owner/immoowner_document.php", 1).'?id='.$object->id;
	$head[$h][1] = $langs->trans('Documents');
	if (($fileCount+$linkCount) > 0) $head[$h][1].= ' <span class="badge">'.($fileCount+$linkCount).'</span>';
	$head[$h][2] = 'document';
	$h++;

	$head[$h][0] = dol_buildpath("/kreaimmo/owner/immoowner_agenda.php", 1).'?id='.$object->id;
	$head[$h][1] = $langs->trans("Events");
	$head[$h][2] = 'agenda';
	$h++;

	// Show more tabs from modules
	// Entries must be declared in modules descriptor with line
	//$this->tabs = array(
	//	'entity:+tabname:Title:@kreaimmo:/kreaimmo/mypage.php?id=__ID__'
	//); // to add new tab
	//$this->tabs = array(
	//	'entity:-tabname:Title:@kreaimmo:/kreaimmo/mypage.php?id=__ID__'
	//); // to remove a tab
	complete_head_from_modules($conf, $langs, $object, $head, $h, 'kreaimmo@kreaimmo');

	return $head;
}
