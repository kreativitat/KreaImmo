<?php
/* Copyright (C) 2026 Kreativität Works <mail@kreativitat.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

require_once __DIR__.'/../../../master.inc.php';

// Native relation selectors derive this route and return parameter from the class name.
$backtopage = GETPOST('backtopage', 'alpha');
if ($backtopage !== '') {
	$backtopage = preg_replace('/([?&])fk_kreaimmoowner=/', '$1fk_owner=', $backtopage);
	if (isset($_POST['backtopage'])) {
		$_POST['backtopage'] = $backtopage;
	} else {
		$_GET['backtopage'] = $backtopage;
	}
}

require __DIR__.'/immoowner_card.php';
