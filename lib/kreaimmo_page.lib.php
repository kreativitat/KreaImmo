<?php
/* Copyright (C) 2026 Kreativität Works <mail@kreativitat.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/**
 * Check a route permission, retaining the module's existing global grants.
 *
 * @param string $family Object permission family, or empty for global access
 * @param string $permission Permission name: read, write or delete
 * @return bool
 */
function kreaimmoPageHasRight($family, $permission)
{
	global $user;

	return !empty($user->id) && ($user->hasRight('kreaimmo', $permission)
		|| ($family !== '' && $user->hasRight('kreaimmo', $family, $permission)));
}

/**
 * Enforce access before a page loads objects or executes request actions.
 *
 * @param string $family Object permission family, or empty for global access
 * @param bool $adminOnly Whether this is an administrative setup route
 * @param string $minimumPermission Minimum permission for actionless requests
 * @return void
 */
function kreaimmoRequirePageAccess($family = '', $adminOnly = false, $minimumPermission = 'read')
{
	global $user, $langs;

	if (!isModEnabled('kreaimmo') || empty($user->id) || !empty($user->socid)
		|| !empty($user->societe_id) || ($adminOnly && empty($user->admin))
		|| !kreaimmoPageHasRight($family, 'read')) {
		httponly_accessforbidden($langs->trans('ErrorForbidden'));
	}

	$action = GETPOST('action', 'aZ09');
	$massaction = GETPOST('massaction', 'aZ09');
	$readActions = array('', '0', 'view', 'list', 'show', 'info', 'search', 'filter', 'stats', 'graph', 'cancel', 'logout');
	$permissions = array($minimumPermission);
	if ((GETPOST('button_createpdf', 'alpha') || GETPOST('modelselected', 'alpha')
		|| GETPOST('actionadd', 'alpha') || GETPOST('actionmodify', 'alpha'))
		&& !kreaimmoPageHasRight($family, 'write')) {
		httponly_accessforbidden($langs->trans('ErrorForbidden'));
	}
	foreach (array($action, $massaction) as $requestAction) {
		if (preg_match('/(?:delete|remove|purge|^del$|^dellink$)/i', $requestAction)) {
			$permissions[] = 'delete';
		} elseif (!in_array($requestAction, $readActions, true)) {
			$permissions[] = 'write';
		}
	}
	foreach (array_unique($permissions) as $permission) {
		if (!kreaimmoPageHasRight($family, $permission)) {
			httponly_accessforbidden($langs->trans('ErrorForbidden'));
		}
	}
}

/**
 * Authorize the authenticated tenant portal; row ownership is checked by its queries.
 *
 * @param bool $counterWrite Whether the request submits a meter reading
 * @return void
 */
function kreaimmoRequirePortalAccess($counterWrite = false)
{
	global $user, $langs;

	if (!isModEnabled('kreaimmo') || !getDolGlobalBool('KREAIMMO_ENABLE_PUBLIC_INTERFACE')
		|| empty($user->id) || empty($user->socid) || empty($user->contact_id)
		|| !kreaimmoPageHasRight('renter', 'read')
		|| ($counterWrite && !kreaimmoPageHasRight('immocompteur', 'write'))) {
		httponly_accessforbidden($langs->trans('ErrorForbidden'));
	}
}
