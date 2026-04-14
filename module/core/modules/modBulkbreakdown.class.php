<?php
/* Copyright (C) 2026 Zachary Melo
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/**
 * Class modBulkbreakdown
 *
 * Module descriptor for Bulk Breakdown
 */
class modBulkbreakdown extends DolibarrModules
{
	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $langs, $conf;

		$this->db = $db;

		$this->numero = 510500;
		$this->family = 'products';
		$this->module_position = '90';
		$this->name = preg_replace('/^mod/i', '', get_class($this));
		$this->description = 'Convert bulk purchased products into individual inventory units using BOM/MRP';
		$this->version = '1.1.0';
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		$this->picto = 'mrp';

		$this->module_parts = array(
			'triggers' => 0,
			'hooks' => array(
				'data' => array('receptioncard', 'productcard'),
				'entity' => '0',
			),
		);

		$this->dirs = array();
		$this->config_page_url = array('setup.php@bulkbreakdown');

		$this->depends = array('modProduct', 'modStock', 'modBom', 'modMrp', 'modReception');
		$this->requiredby = array();
		$this->conflictwith = array();

		$this->langfiles = array('bulkbreakdown@bulkbreakdown');

		$this->phpmin = array(7, 0);
		$this->need_dolibarr_version = array(16, 0);

		// No module-level constants needed — warehouse is taken from reception line
		$this->const = array();

		// Tabs
		$this->tabs = array();
		$this->tabs[] = array(
			'data' => 'product:+bulkbreakdown:Breakdown:bulkbreakdown@bulkbreakdown:$conf->bulkbreakdown->enabled:/bulkbreakdown/breakdown_tab.php?id=__ID__'
		);

		// Permissions
		$this->rights = array();
		$this->rights_class = 'bulkbreakdown';
		$r = 0;

		$r++;
		$this->rights[$r][0] = 510501;
		$this->rights[$r][1] = 'Read breakdown rules';
		$this->rights[$r][2] = 'r';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'breakdown';
		$this->rights[$r][5] = 'read';

		$r++;
		$this->rights[$r][0] = 510502;
		$this->rights[$r][1] = 'Create and edit breakdown rules';
		$this->rights[$r][2] = 'w';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'breakdown';
		$this->rights[$r][5] = 'write';

		$r++;
		$this->rights[$r][0] = 510503;
		$this->rights[$r][1] = 'Process breakdowns (create Manufacturing Orders)';
		$this->rights[$r][2] = 'w';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'breakdown';
		$this->rights[$r][5] = 'process';

		// No top-level menus — functionality is on product tab and reception page
		$this->menu = array();
	}

	/**
	 * Enable module
	 *
	 * Automatically activates required modules (Product, Stock, BOM, MRP, Reception)
	 * if they are not already enabled.
	 *
	 * @param  string $options Options when enabling module
	 * @return int             1 if OK, 0 if KO
	 */
	public function init($options = '')
	{
		global $conf;

		// Auto-enable required modules if not already active
		include_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';

		$requiredModules = array(
			'product'   => 'modProduct',
			'stock'     => 'modStock',
			'bom'       => 'modBom',
			'mrp'       => 'modMrp',
			'reception' => 'modReception',
		);

		foreach ($requiredModules as $modKey => $modClass) {
			if (!isModEnabled($modKey)) {
				$res = activateModule($modClass);
				if (!empty($res['errors'])) {
					$this->error = 'Failed to activate required module: '.$modClass;
					dol_syslog($this->error, LOG_ERR);
					return -1;
				}
			}
		}

		$result = $this->_load_tables('/bulkbreakdown/sql/');
		if ($result < 0) {
			return -1;
		}
		$this->delete_menus();
		return $this->_init(array(), $options);
	}

	/**
	 * Disable module
	 *
	 * @param  string $options Options when disabling module
	 * @return int             1 if OK, 0 if KO
	 */
	public function remove($options = '')
	{
		return $this->_remove(array(), $options);
	}
}
