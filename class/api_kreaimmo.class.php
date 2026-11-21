<?php
/* Copyright (C) 2015   Jean-François Ferry     <jfefe@aternatik.fr>
 * Copyright (C) 2018 Philippe GRAND <philippe.grand@atoo-net.com>
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

use Luracast\Restler\RestException;

dol_include_once('/kreaimmo/class/kreaimmorenter.class.php');



/**
 * \file    kreaimmo/class/api_kreaimmo.class.php
 * \ingroup kreaimmo
 * \brief   File for API management of immorenter.
 */

/**
 * API class for kreaimmo immorenter
 *
 * @smart-auto-routing false
 * @access protected
 * @class  DolibarrApiAccess {@requires user,external}
 */
class KreaImmoApi extends DolibarrApi
{
	/** @var string[] Required create fields */
	public static $FIELDS = array('lastname');

	/** @var KreaImmoRenter */
	public $immorenter;

	public function __construct()
	{
		global $db;
		$this->db = $db;
		$this->immorenter = new KreaImmoRenter($db);
	}

	/**
	 * Fetch a renter belonging to the active company and permitted third party.
	 *
	 * @param int $id Renter ID
	 * @return object
	 * @url GET immorenters/{id}
	 */
	public function get($id)
	{
		$this->requirePermission('read');
		$this->loadRenter($id);
		return $this->_cleanObjectDatas($this->immorenter);
	}

	/**
	 * List renters with native filters and bounded pagination.
	 *
	 * @param string $sortfield Sort field
	 * @param string $sortorder Sort order
	 * @param int $limit Maximum rows, from 1 to 1000
	 * @param int $page Zero-based page
	 * @param string $sqlfilters Native universal search criteria
	 * @return array
	 * @url GET /immorenters/
	 */
	public function index($sortfield = 't.rowid', $sortorder = 'ASC', $limit = 100, $page = 0, $sqlfilters = '')
	{
		$this->requirePermission('read');
		$field = preg_replace('/^t\./', '', $sortfield);
		if (!isset($this->immorenter->fields[$field]) || !in_array(strtoupper($sortorder), array('ASC', 'DESC'), true)) {
			throw new RestException(400, 'Invalid sort field or order');
		}
		$limit = max(1, min(1000, (int) $limit));
		$page = max(0, (int) $page);
		$sql = 'SELECT '.$this->immorenter->getFieldList('t').' FROM '.$this->db->prefix().'kreaimmo_immorenter AS t';
		$sql .= ' WHERE '.$this->immorenter->getKreaImmoScopeSQL('t').$this->visibilitySQL('t');
		if ($sqlfilters !== '') {
			$error = '';
			$filter = forgeSQLFromUniversalSearchCriteria($sqlfilters, $error);
			if ($error) {
				throw new RestException(400, 'Invalid search criteria');
			}
			$sql .= $filter;
		}
		$sql .= $this->db->order('t.'.$field, $sortorder).$this->db->plimit($limit, $page * $limit);
		$resql = $this->db->query($sql);
		if (!$resql) {
			throw new RestException(500, 'Unable to list renters');
		}
		$records = array();
		while ($row = $this->db->fetch_object($resql)) {
			$renter = new KreaImmoRenter($this->db);
			$renter->setVarsFromFetchObj($row);
			$records[] = $this->_cleanObjectDatas($renter);
		}
		return $records;
	}

	/**
	 * Create a renter.
	 *
	 * @param array $request_data Renter fields
	 * @return int
	 * @url POST immorenters/
	 */
	public function post($request_data = null)
	{
		$this->requirePermission('write');
		$this->immorenter = new KreaImmoRenter($this->db);
		$data = $this->_validate($request_data);
		$this->assignFields($data);
		if (!isset($this->immorenter->civility_id)) {
			$this->immorenter->civility_id = 0;
		}
		if ($this->immorenter->create(DolibarrApiAccess::$user) <= 0) {
			$this->writeFailed();
		}
		return $this->immorenter->id;
	}

	/**
	 * Update a renter.
	 *
	 * @param int $id Renter ID
	 * @param array $request_data Editable renter fields
	 * @return object
	 * @url PUT immorenters/{id}
	 */
	public function put($id, $request_data = null)
	{
		$this->requirePermission('write');
		$this->loadRenter($id);
		$this->assignFields($request_data);
		if ($this->immorenter->update(DolibarrApiAccess::$user) <= 0) {
			$this->writeFailed();
		}
		$this->loadRenter($id);
		return $this->_cleanObjectDatas($this->immorenter);
	}

	/**
	 * Delete a renter.
	 *
	 * @param int $id Renter ID
	 * @return array
	 * @url DELETE immorenter/{id}
	 */
	public function delete($id)
	{
		$this->requirePermission('delete');
		$this->loadRenter($id);
		if ($this->immorenter->delete(DolibarrApiAccess::$user) <= 0) {
			$this->writeFailed();
		}
		return array('success' => array('code' => 200, 'message' => 'KreaImmoRenter deleted'));
	}

	/** @param string $permission Permission name */
	private function requirePermission($permission)
	{
		if (!DolibarrApiAccess::$user->hasRight('kreaimmo', $permission) && !DolibarrApiAccess::$user->hasRight('kreaimmo', 'renter', $permission)) {
			throw new RestException(401, 'Renter permission required');
		}
	}

	/** @return string SQL enforcing native third-party visibility */
	private function visibilitySQL($alias)
	{
		$user = DolibarrApiAccess::$user;
		if (!empty($user->societe_id)) {
			return ' AND '.$alias.'.fk_soc = '.((int) $user->societe_id);
		}
		if (!$user->hasRight('societe', 'client', 'voir')) {
			return ' AND EXISTS (SELECT 1 FROM '.$this->db->prefix().'societe_commerciaux AS sc WHERE sc.fk_soc = '.$alias.'.fk_soc AND sc.fk_user = '.((int) $user->id).')';
		}
		return '';
	}

	/** @param int $id Renter ID */
	private function loadRenter($id)
	{
		$this->immorenter = new KreaImmoRenter($this->db);
		if ($this->immorenter->fetch((int) $id) <= 0) {
			throw new RestException(404, 'KreaImmoRenter not found');
		}
		$sql = 'SELECT t.rowid FROM '.$this->db->prefix().'kreaimmo_immorenter AS t WHERE t.rowid = '.((int) $id).$this->visibilitySQL('t');
		$resql = $this->db->query($sql);
		if (!$resql) {
			throw new RestException(500, 'Unable to check renter access');
		}
		if (!$this->db->fetch_object($resql)) {
			throw new RestException(404, 'KreaImmoRenter not found');
		}
	}

	/** @param array $data Editable values */
	private function assignFields($data)
	{
		if (!is_array($data)) {
			throw new RestException(400, 'Expected a renter object');
		}
		$protected = array('rowid', 'entity', 'date_creation', 'tms', 'fk_user_creat', 'fk_user_modif', 'import_key', 'status');
		foreach ($data as $field => $value) {
			if (!isset($this->immorenter->fields[$field]) || in_array($field, $protected, true)) {
				throw new RestException(403, 'Field is not editable: '.$field);
			}
			if (!is_scalar($value) && $value !== null) {
				throw new RestException(400, 'Invalid field value: '.$field);
			}
			$this->immorenter->$field = $value;
		}
		$user = DolibarrApiAccess::$user;
		if (!empty($user->societe_id) && (int) $this->immorenter->fk_soc !== (int) $user->societe_id) {
			throw new RestException(403, 'Third-party access denied');
		}
	}

	/** @return void */
	private function writeFailed()
	{
		if ($this->immorenter->error === 'KreaImmoAccessDenied') {
			throw new RestException(403, 'Company access denied');
		}
		throw new RestException(500, 'Unable to save renter');
	}

	/**
	 * @param array $data Create values
	 * @return array
	 */
	public function _validate($data)
	{
		if (!is_array($data)) {
			throw new RestException(400, 'Expected a renter object');
		}
		foreach (self::$FIELDS as $field) {
			if (!isset($data[$field]) || !is_scalar($data[$field]) || trim((string) $data[$field]) === '') {
				throw new RestException(400, $field.' field missing');
			}
		}
		return $data;
	}
}
