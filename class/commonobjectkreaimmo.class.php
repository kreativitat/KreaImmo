<?php
/* Copyright (C) 2022 Florian Henry  <florian.henry@scopen.fr>

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
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *	\file       kreaimmo/class/commonobjectkreaimmo.class.php
 *	\ingroup    core
 *	\brief      File of parent class of all kreaimmo classes
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';
/**
 *	special field type for kreaimmo
 */
class CommonObjectKreaImmo extends CommonObject
{
	/**
	 * Restrict module records to their company, including records owned through a parent.
	 * The index and renter-type dictionaries intentionally have no company column.
	 *
	 * @param string $alias Trusted table alias
	 * @return string SQL predicate
	 */
	public function getKreaImmoScopeSQL($alias = 't')
	{
		global $conf;
		$entity = (int) $conf->entity;
		if (isset($this->fields['entity'])) {
			return $alias.'.entity '.(strpos($this->table_element, 'c_kreaimmo_') === 0 ? 'IN (0, '.$entity.')' : '= '.$entity);
		}
		$parents = array(
			'kreaimmo_building' => array('fk_property', 'kreaimmo_immoproperty'),
			'kreaimmo_immocompteur' => array('fk_immoproperty', 'kreaimmo_immoproperty'),
			'kreaimmo_immocost_detail' => array('fk_immocost', 'kreaimmo_immocost'),
			'kreaimmo_immospreadzone' => array('fk_property_parent', 'kreaimmo_immoproperty'),
			'kreaimmo_immocompteur_cost' => array('fk_immocompteur_type', 'c_kreaimmo_immocompteur_type'),
		);
		if (!isset($parents[$this->table_element])) {
			return '1 = 1';
		}
		list($field, $table) = $parents[$this->table_element];
		return 'EXISTS (SELECT 1 FROM '.$this->db->prefix().$table.' AS kreaimmo_parent WHERE kreaimmo_parent.rowid = '.$alias.'.'.$field.' AND kreaimmo_parent.entity '.(strpos($table, 'c_kreaimmo_') === 0 ? 'IN (0, '.$entity.')' : '= '.$entity).')';
	}

	/**
	 * Check persisted ownership and all company-bearing references before a write.
	 *
	 * @param bool $creating Whether this is a new record
	 * @return bool True when the write is permitted
	 */
	public function checkKreaImmoWriteAccess($creating = false)
	{
		global $conf;
		if (isset($this->fields['entity']) && isset($this->entity) && (int) $this->entity !== (int) $conf->entity) {
			return $this->denyKreaImmoWrite();
		}
		if (!$creating) {
			$this->id = (int) $this->id;
			$sql = 'SELECT t.rowid FROM '.$this->db->prefix().$this->table_element.' AS t WHERE t.rowid = '.((int) $this->id).' AND '.$this->getKreaImmoScopeSQL('t');
			$resql = $this->db->query($sql);
			if (!$resql) {
				$this->error = $this->db->lasterror();
				return false;
			}
			if (!$this->db->fetch_object($resql)) {
				return $this->denyKreaImmoWrite();
			}
		}
		$references = array(
			'fk_owner' => 'kreaimmo_immoowner', 'fk_renter' => 'kreaimmo_immorenter',
			'fk_property' => 'kreaimmo_immoproperty', 'fk_immoproperty' => 'kreaimmo_immoproperty',
			'fk_property_parent' => 'kreaimmo_immoproperty', 'fk_property_child' => 'kreaimmo_immoproperty',
			'fk_rent' => 'kreaimmo_immorent', 'fk_receipt' => 'kreaimmo_immoreceipt',
			'fk_immocost' => 'kreaimmo_immocost', 'fk_cost_type' => 'kreaimmo_immocost_type',
			'fk_owner_type' => 'kreaimmo_immoowner_type',
			'property_type_id' => 'c_kreaimmo_immoproperty_type',
			'juridique_id' => 'c_kreaimmo_juridique',
			'compteur_type_id' => 'c_kreaimmo_immocompteur_type',
			'fk_immocompteur_type' => 'c_kreaimmo_immocompteur_type',
			'location_type_id' => 'c_kreaimmo_immorent_type',
			'fk_soc' => 'societe', 'fk_account' => 'bank_account',
		);
		$requiredParents = array(
			'kreaimmo_building' => array('fk_property'),
			'kreaimmo_immocompteur' => array('fk_immoproperty'),
			'kreaimmo_immocost_detail' => array('fk_immocost'),
			'kreaimmo_immospreadzone' => array('fk_property_parent', 'fk_property_child'),
			'kreaimmo_immocompteur_cost' => array('fk_immocompteur_type'),
		);
		foreach (isset($requiredParents[$this->table_element]) ? $requiredParents[$this->table_element] : array() as $field) {
			if (empty($this->$field) || (int) $this->$field < 1) {
				return $this->denyKreaImmoWrite();
			}
		}
		foreach ($references as $field => $table) {
			if (!isset($this->fields[$field]) || empty($this->$field) || (int) $this->$field < 1) {
				continue;
			}
			$entities = (string) ((int) $conf->entity);
			if (in_array($table, array('societe', 'bank_account'), true)) {
				$entities = getEntity($table);
			} elseif (strpos($table, 'c_kreaimmo_') === 0) {
				$entities = '0, '.$entities;
			}
			$sql = 'SELECT rowid FROM '.$this->db->prefix().$table.' WHERE rowid = '.((int) $this->$field).' AND entity IN ('.$entities.')';
			$resql = $this->db->query($sql);
			if (!$resql) {
				$this->error = $this->db->lasterror();
				return false;
			}
			if (!$this->db->fetch_object($resql)) {
				return $this->denyKreaImmoWrite();
			}
		}
		if (isset($this->fields['fk_bank']) && !empty($this->fk_bank)) {
			$sql = 'SELECT b.rowid, b.fk_account FROM '.$this->db->prefix().'bank AS b INNER JOIN '.$this->db->prefix().'bank_account AS ba ON ba.rowid = b.fk_account WHERE b.rowid = '.((int) $this->fk_bank).' AND ba.entity IN ('.getEntity('bank_account').')';
			$resql = $this->db->query($sql);
			$bankLine = $resql ? $this->db->fetch_object($resql) : false;
			if (!$bankLine || (int) $bankLine->fk_account !== (int) $this->fk_account) {
				return $this->denyKreaImmoWrite();
			}
		}
		return true;
	}

	/** @return false */
	protected function denyKreaImmoWrite()
	{
		$this->error = 'KreaImmoAccessDenied';
		$this->errors[] = $this->error;
		return false;
	}

	/** @inheritdoc */
	public function createCommon(User $user, $notrigger = 0)
	{
		global $conf;
		if (!$this->checkKreaImmoWriteAccess(true)) {
			return -1;
		}
		if (isset($this->fields['entity'])) {
			$this->entity = (int) $conf->entity;
		}
		if (isset($this->fields['ref']) && empty($this->ref)) {
			$this->fields['ref']['default'] = '(PROV)';
			$this->ref = '(PROV)';
		}
		if (isset($this->fields['fk_user_create']) && empty($this->fk_user_create)) {
			$this->fk_user_create = $user->id;
		}
		$requestedRef = isset($this->ref) ? $this->ref : null;
		$result = parent::createCommon($user, $notrigger);
		if ($result > 0 && $requestedRef !== null && $requestedRef !== '' && $requestedRef !== '(PROV)') {
			$this->ref = dol_string_nospecial($requestedRef);
		}
		return $result;
	}

	/** @inheritdoc */
	public function fetchCommon($id, $ref = null, $morewhere = '', $noextrafields = 0)
	{
		return parent::fetchCommon($id, $ref, $morewhere.' AND '.$this->getKreaImmoScopeSQL('t'), $noextrafields);
	}

	/** @inheritdoc */
	public function updateCommon(User $user, $notrigger = 0)
	{
		if (!$this->checkKreaImmoWriteAccess()) {
			return -1;
		}
		return parent::updateCommon($user, $notrigger);
	}

	/** @inheritdoc */
	public function deleteCommon(User $user, $notrigger = 0, $forcechilddeletion = 0)
	{
		if (!$this->checkKreaImmoWriteAccess()) {
			return -1;
		}
		return parent::deleteCommon($user, $notrigger, $forcechilddeletion);
	}

	/** @inheritdoc */
	public function setStatusCommon($user, $status, $notrigger = 0, $triggercode = '')
	{
		if (!$this->checkKreaImmoWriteAccess()) {
			return -1;
		}
		return parent::setStatusCommon($user, $status, $notrigger, $triggercode);
	}

	/** @inheritdoc */
	public function setStatut($status, $elementId = null, $elementType = '', $trigkey = '', $fieldstatus = '')
	{
		if ((!empty($elementId) && (int) $elementId !== (int) $this->id) || (!empty($elementType) && $elementType !== $this->table_element) || (!empty($fieldstatus) && !in_array($fieldstatus, array('status', 'active', 'fk_statut'), true)) || !$this->checkKreaImmoWriteAccess()) {
			$this->denyKreaImmoWrite();
			return -1;
		}
		return parent::setStatut($status, $elementId, $elementType, $trigkey, $fieldstatus);
	}

	/** @inheritdoc */
	public function setValueFrom($field, $value, $table = '', $id = null, $format = '', $id_field = '', $fuser = null, $trigkey = '', $fk_user_field = 'fk_user_modif')
	{
		if ((!empty($id) && (int) $id !== (int) $this->id) || (!empty($table) && $table !== $this->table_element) || (!empty($id_field) && $id_field !== 'rowid') || !isset($this->fields[$field]) || in_array($field, array('rowid', 'entity'), true)) {
			$this->denyKreaImmoWrite();
			return -1;
		}
		$proposed = clone $this;
		$proposed->$field = $value;
		if (!$proposed->checkKreaImmoWriteAccess()) {
			$this->error = $proposed->error;
			$this->errors = $proposed->errors;
			return -1;
		}
		return parent::setValueFrom($field, $value, $table, $id, $format, $id_field, $fuser, $trigkey, $fk_user_field);
	}

	/** @inheritdoc */
	public function setBankAccount($fk_account, $notrigger = 0, $userused = null)
	{
		$proposed = clone $this;
		$proposed->fk_account = (int) $fk_account;
		if (!isset($this->fields['fk_account']) || !$proposed->checkKreaImmoWriteAccess()) {
			$this->error = $proposed->error;
			return -1;
		}
		$result = parent::setBankAccount($fk_account, $notrigger, $userused);
		if ($result > 0) {
			$this->fk_account = (int) $fk_account;
		}
		return $result;
	}

	/**
	 * Return HTML string to put an input field into a page
	 * Code very similar with showInputField of extra fields
	 *
	 * @param  array   		$val	       Array of properties for field to show (used only if ->fields not defined)
	 * @param  string  		$key           Key of attribute
	 * @param  string|array	$value         Preselected value to show (for date type it must be in timestamp format, for amount or price it must be a php numeric value, for array type must be array)
	 * @param  string  		$moreparam     To add more parameters on html input tag
	 * @param  string  		$keysuffix     Prefix string to add into name and id of field (can be used to avoid duplicate names)
	 * @param  string  		$keyprefix     Suffix string to add into name and id of field (can be used to avoid duplicate names)
	 * @param  string|int	$morecss       Value for css to define style/length of field. May also be a numeric.
	 * @param  int			$nonewbutton   Force to not show the new button on field that are links to object
	 * @return string
	 */
	public function showInputField($val, $key, $value, $moreparam = '', $keysuffix = '', $keyprefix = '', $morecss = 0, $nonewbutton = 0)
	{
		global $form;
		if ($val['label']=='Civility') {
			$this->civility_id	= GETPOST("civility_id", 'int') ? GETPOST('civility_id', 'int') : $this->civility_id;

			if ($this->civility_id) {
				$tmparray = array();
				$tmparray = $this->getCivilityLabel($this->civility_id, 'all');
				if (in_array($tmparray['code'], $tmparray)) $this->civility_code = $tmparray['code'];
				if (in_array($tmparray['label'], $tmparray)) $this->civility = $tmparray['label'];
			}

			// civility
			print $this->select_civility(GETPOSTISSET("civility_id") != '' ? GETPOST("civility_id", 'int') : $this->civility_id, 'civility_id');
		} elseif ($val['label']=='Country') {
			$this->country_id = GETPOST('country_id', 'int') ? GETPOST('country_id', 'int') : $this->country_id;
			if ($this->country_id) {
				$tmparray = $this->getCountry($this->country_id, 'all');
				$this->country_code = $tmparray['code'];
				$this->country = $tmparray['label'];
			}
			// Country
			print $form->select_country((GETPOST('country_id') != '' ? GETPOST('country_id') : $this->country_id));
		} else {
			return parent::showInputField($val, $key, $value, $moreparam, $keysuffix, $keyprefix, $morecss, $nonewbutton);
		}
	}

	/**
	 * Return HTML string to show a field into a page
	 * Code very similar with showOutputField of extra fields
	 *
	 * @param  array   $val		       Array of properties of field to show
	 * @param  string  $key            Key of attribute
	 * @param  string  $value          Preselected value to show (for date type it must be in timestamp format, for amount or price it must be a php numeric value)
	 * @param  string  $moreparam      To add more parametes on html input tag
	 * @param  string  $keysuffix      Prefix string to add into name and id of field (can be used to avoid duplicate names)
	 * @param  string  $keyprefix      Suffix string to add into name and id of field (can be used to avoid duplicate names)
	 * @param  mixed   $morecss        Value for css to define size. May also be a numeric.
	 * @return string
	 */
	public function showOutputField($val, $key, $value, $moreparam = '', $keysuffix = '', $keyprefix = '', $morecss = '')
	{

		if ($val['label'] == 'Civility') {
			if ($this->civility_id) {
				$tmparray = $this->getCivilityLabel($this->civility_id, 'all');
				$this->civility_code = $tmparray['code'];
				$this->civility = $tmparray['label'];
			}
			print $this->civility;
		} elseif ($val['label']=='MenuImmoOwnerType') {

			dol_include_once('/kreaimmo/class/kreaimmoowner_type.class.php');

			$staticownertype = new KreaImmoOwner_Type($this->db);
			$staticownertype->fetch($this->fk_owner_type);
			if ($staticownertype->ref) {
				print $staticownertype->ref;
			}
		} elseif ($val['label']=='Country') {
			if ($this->country_id) {
				$tmparray = $this->getCountry($this->country_id, 'all');
				$this->country_code = $tmparray['code'];
				$this->country = $tmparray['label'];
				// Country
				print $this->country;
			}
		} else {
			return parent::showOutputField($val, $key, $value, $moreparam, $keysuffix, $keyprefix, $morecss);
		}
	}
}
