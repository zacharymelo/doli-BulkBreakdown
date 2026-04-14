<?php
/* Copyright (C) 2026 Zachary Melo
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

/**
 * Class BreakdownRule
 *
 * Links a bulk product to a Disassembly BOM for automated breakdown processing.
 */
class BreakdownRule extends CommonObject
{
	/**
	 * @var string Element type identifier
	 */
	public $element = 'breakdownrule';

	/**
	 * @var string Database table name (without prefix)
	 */
	public $table_element = 'bulkbreakdown_rule';

	/**
	 * @var string Icon
	 */
	public $picto = 'mrp';

	/**
	 * @var array Field definitions for CommonObject ORM
	 */
	public $fields = array(
		'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => 1, 'visible' => -2, 'position' => 1, 'notnull' => 1, 'index' => 1),
		'entity' => array('type' => 'integer', 'label' => 'Entity', 'enabled' => 1, 'visible' => 0, 'position' => 5, 'notnull' => 1, 'default' => '1', 'index' => 1),
		'fk_product' => array('type' => 'integer:Product:product/class/product.class.php:0', 'label' => 'Product', 'enabled' => 1, 'visible' => 1, 'position' => 10, 'notnull' => 1, 'index' => 1),
		'fk_bom' => array('type' => 'integer:BOM:bom/class/bom.class.php:0:(t.status:=:1)', 'label' => 'BOM', 'enabled' => 1, 'visible' => 1, 'position' => 20, 'notnull' => 1, 'index' => 1),
		'fk_warehouse_source' => array('type' => 'integer:Entrepot:product/stock/class/entrepot.class.php:0', 'label' => 'SourceWarehouse', 'enabled' => 1, 'visible' => 1, 'position' => 30, 'notnull' => -1),
		'fk_warehouse_dest' => array('type' => 'integer:Entrepot:product/stock/class/entrepot.class.php:0', 'label' => 'DestWarehouse', 'enabled' => 1, 'visible' => 1, 'position' => 40, 'notnull' => -1),
		'active' => array('type' => 'integer', 'label' => 'Active', 'enabled' => 1, 'visible' => 1, 'position' => 50, 'notnull' => 1, 'default' => '1'),
		'note' => array('type' => 'text', 'label' => 'Note', 'enabled' => 1, 'visible' => 1, 'position' => 60, 'notnull' => -1),
		'date_creation' => array('type' => 'datetime', 'label' => 'DateCreation', 'enabled' => 1, 'visible' => -2, 'position' => 500, 'notnull' => 1),
		'tms' => array('type' => 'timestamp', 'label' => 'DateModification', 'enabled' => 1, 'visible' => -2, 'position' => 510),
		'fk_user_creat' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserCreat', 'enabled' => 1, 'visible' => -2, 'position' => 520, 'notnull' => 1),
		'fk_user_modif' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserModif', 'enabled' => 1, 'visible' => -2, 'position' => 530, 'notnull' => -1),
	);

	/**
	 * @var int Product ID (bulk product)
	 */
	public $fk_product;

	/**
	 * @var int BOM ID (disassembly BOM)
	 */
	public $fk_bom;

	/**
	 * @var int Source warehouse ID
	 */
	public $fk_warehouse_source;

	/**
	 * @var int Destination warehouse ID
	 */
	public $fk_warehouse_dest;

	/**
	 * @var int Active flag
	 */
	public $active;

	/**
	 * @var string Note
	 */
	public $note;

	/**
	 * @var string Date of creation
	 */
	public $date_creation;

	/**
	 * @var int User who created
	 */
	public $fk_user_creat;

	/**
	 * @var int User who last modified
	 */
	public $fk_user_modif;

	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $conf;

		$this->db = $db;
		$this->entity = $conf->entity;
	}

	/**
	 * Create a breakdown rule
	 *
	 * @param  User $user      User creating
	 * @param  int  $notrigger Disable triggers
	 * @return int             ID if OK, <0 if KO
	 */
	public function create($user, $notrigger = 0)
	{
		$this->date_creation = dol_now();
		$this->fk_user_creat = $user->id;
		return $this->createCommon($user, $notrigger);
	}

	/**
	 * Fetch a breakdown rule by rowid
	 *
	 * @param  int    $id  Row ID
	 * @param  string $ref Not used
	 * @return int         >0 if OK, 0 if not found, <0 if KO
	 */
	public function fetch($id, $ref = null)
	{
		return $this->fetchCommon($id, $ref);
	}

	/**
	 * Fetch the active breakdown rule for a product
	 *
	 * @param  int $productId Product ID
	 * @return int            >0 if found, 0 if not found, <0 if error
	 */
	public function fetchByProduct($productId)
	{
		$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX.$this->table_element;
		$sql .= " WHERE fk_product = ".((int) $productId);
		$sql .= " AND active = 1";
		$sql .= " AND entity IN (".getEntity('bulkbreakdown').")";
		$sql .= " LIMIT 1";

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		if ($this->db->num_rows($resql) > 0) {
			$obj = $this->db->fetch_object($resql);
			return $this->fetch($obj->rowid);
		}

		return 0;
	}

	/**
	 * Update a breakdown rule
	 *
	 * @param  User $user      User modifying
	 * @param  int  $notrigger Disable triggers
	 * @return int             >0 if OK, <0 if KO
	 */
	public function update($user, $notrigger = 0)
	{
		$this->fk_user_modif = $user->id;
		return $this->updateCommon($user, $notrigger);
	}

	/**
	 * Delete a breakdown rule
	 *
	 * @param  User $user      User deleting
	 * @param  int  $notrigger Disable triggers
	 * @return int             >0 if OK, <0 if KO
	 */
	public function delete($user, $notrigger = 0)
	{
		return $this->deleteCommon($user, $notrigger);
	}
}
