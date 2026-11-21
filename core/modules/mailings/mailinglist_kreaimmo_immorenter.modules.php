<?php
/* Copyright (C) 2005-2012 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2018-2019 Philippe GRAND 		<philippe.grand@atoo-net.com>
 *
 * This file is an example to follow to add your own email selector inside
 * the Dolibarr email tool.
 * Follow instructions given in README file to know what to change to build
 * your own emailing list selector.
 * Code that need to be changed in this file are marked by "CHANGE THIS" tag.
 */

require_once DOL_DOCUMENT_ROOT.'/core/modules/mailings/modules_mailings.php';
require_once DOL_DOCUMENT_ROOT.'/comm/mailing/class/mailing.class.php';
dol_include_once('/kreaimmo/class/kreaimmorenter.class.php');

/** Select current-company renters for the native mailing recipient workflow. */
class mailing_mailinglist_KreaImmo_immorenter extends MailingTargets
{
	public $name = 'mailinglist_kreaimmo_immorenter';
	public $desc = 'Contacts of renters';
	public $require_admin = 0;
	public $enabled = 0;
	public $require_module = array('mailing', 'kreaimmo');
	public $picto = 'kreaimmo@kreaimmo';

	/** @param DoliDB $db Database handler */
	public function __construct($db)
	{
		parent::__construct($db);
		$this->enabled = (int) $this->canUseSelector();
	}

	/** @return string Native renter-status filter */
	public function formFilter()
	{
		global $langs;
		if (!$this->canUseSelector()) {
			return '';
		}
		$langs->load('kreaimmo@kreaimmo');
		$renter = new KreaImmoRenter($this->db);
		$selected = GETPOST('filter', 'alphanohtml');
		$selected = is_scalar($selected) ? (string) $selected : '';
		$html = $langs->trans('Status').': <select name="filter" class="flat">';
		$html .= '<option value="none">&nbsp;</option>';
		foreach ($renter->fields['status']['arrayofkeyval'] as $status => $label) {
			$html .= '<option value="'.((int) $status).'"'.((string) $selected === (string) $status ? ' selected' : '').'>'.dol_escape_htmltag($label).'</option>';
		}
		return $html.'</select>';
	}

	/**
	 * @param int $id Renter ID
	 * @return string Link to the source renter
	 */
	public function url($id)
	{
		$url = dol_buildpath('/kreaimmo/renter/immorenter_card.php', 1).'?id='.((int) $id);
		return '<a href="'.dol_escape_htmltag($url).'">'.img_object('', 'kreaimmo@kreaimmo').'</a>';
	}

	/**
	 * @param int $mailing_id Mailing ID
	 * @param array $filtersarray Reserved for selector compatibility
	 * @return int Number of added recipients, or -1 on denial/error
	 */
	public function add_to_target($mailing_id, $filtersarray = array())
	{
		global $conf;
		if (!$this->canUseSelector(true)) {
			return $this->denyAccess();
		}
		$mailing = new Mailing($this->db);
		if ($mailing->fetch((int) $mailing_id) <= 0 || (int) $mailing->entity !== (int) $conf->entity) {
			return $this->denyAccess();
		}
		$allowedStatuses = array(Mailing::STATUS_DRAFT, Mailing::STATUS_VALIDATED, Mailing::STATUS_SENTPARTIALY, Mailing::STATUS_SENTCOMPLETELY);
		if (!in_array((int) $mailing->status, $allowedStatuses, true)) {
			return $this->denyAccess();
		}
		$sql = $this->renterQuery(false);
		if ($sql === false) {
			return -1;
		}
		$this->sql = $sql;
		$result = $this->db->query($sql);
		if (!$result) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$targets = array();
		$previousEmail = null;
		while ($renter = $this->db->fetch_object($result)) {
			if ($renter->email === $previousEmail) {
				continue;
			}
			$targets[] = array(
				'lastname' => (string) $renter->lastname,
				'firstname' => (string) $renter->firstname,
				'email' => $renter->email,
				'other' => '',
				'source_url' => $this->url($renter->rowid),
				'source_id' => (int) $renter->rowid,
				'source_type' => 'kreaimmo_immorenter'
			);
			$previousEmail = $renter->email;
		}
		$this->db->free($result);
		$this->evenunsubscribe = (int) $mailing->evenunsubscribe;
		return parent::addTargetsToDatabase((int) $mailing_id, $targets);
	}

	/** @return array Optional statistics are not provided by this selector. */
	public function getSqlArrayForStats()
	{
		return array();
	}

	/**
	 * @param string|int $filter Reserved for selector compatibility
	 * @param string $option Reserved for selector compatibility
	 * @return int Count of visible distinct addresses, or -1 on denial/error
	 */
	public function getNbOfRecipients($filter = 1, $option = '')
	{
		if (!$this->canUseSelector()) {
			return $this->denyAccess();
		}
		$sql = $this->renterQuery(true);
		if ($sql === false) {
			return -1;
		}
		$this->sql = $sql;
		return parent::getNbOfRecipients($sql);
	}

	/**
	 * @param bool $forWrite Whether native mailing creation permission is needed
	 * @return bool Whether the current internal user can use this selector
	 */
	private function canUseSelector($forWrite = false)
	{
		global $user;
		return isModEnabled('kreaimmo') && isModEnabled('mailing') && is_object($user) && !empty($user->id)
			&& empty($user->socid) && empty($user->societe_id)
			&& ($user->hasRight('kreaimmo', 'read') || $user->hasRight('kreaimmo', 'renter', 'read'))
			&& $user->hasRight('mailing', 'lire') && (!$forWrite || $user->hasRight('mailing', 'creer'));
	}

	/**
	 * @param bool $count Whether to count instead of selecting recipient fields
	 * @return string|false Tenant-scoped query, or false for an invalid filter
	 */
	private function renterQuery($count)
	{
		global $conf, $user;
		$filter = GETPOST('filter', 'alphanohtml');
		if (!is_scalar($filter)) {
			$this->error = 'ErrorBadValueForParameter';
			return false;
		}
		$sql = 'SELECT '.($count ? 'COUNT(DISTINCT r.email) AS nb' : 'r.rowid, r.email, r.firstname, r.lastname');
		$sql .= ' FROM '.$this->db->prefix().'kreaimmo_immorenter AS r';
		$sql .= " WHERE r.entity = ".((int) $conf->entity)." AND r.email IS NOT NULL AND r.email <> ''";
		if ($filter !== '' && $filter !== 'none') {
			$renter = new KreaImmoRenter($this->db);
			$status = (int) GETPOST('filter', 'int');
			if (!preg_match('/^[0-9]+$/D', (string) $filter) || !array_key_exists($status, $renter->fields['status']['arrayofkeyval'])) {
				$this->error = 'ErrorBadValueForParameter';
				return false;
			}
			$sql .= ' AND r.status = '.$status;
		}
		if (!$user->hasRight('societe', 'client', 'voir')) {
			$sql .= ' AND (r.fk_soc IS NULL OR r.fk_soc = 0 OR EXISTS (SELECT 1 FROM '.$this->db->prefix().'societe_commerciaux AS sc WHERE sc.fk_soc = r.fk_soc AND sc.fk_user = '.((int) $user->id).'))';
		}
		if (!$count) {
			$sql .= ' ORDER BY r.email';
		}
		return $sql;
	}

	/** @return int Always -1 */
	private function denyAccess()
	{
		$this->error = 'KreaImmoAccessDenied';
		return -1;
	}
}
