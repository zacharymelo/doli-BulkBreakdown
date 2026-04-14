<?php
/* Copyright (C) 2026 Zachary Melo
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    class/actions_bulkbreakdown.class.php
 * \ingroup bulkbreakdown
 * \brief   Hook actions for Bulk Breakdown module.
 *          Injects "Process Breakdowns" button on the Reception card.
 */
class ActionsBulkbreakdown
{
	/**
	 * @var DoliDB Database handler
	 */
	public $db;

	/**
	 * @var string Error message
	 */
	public $error = '';

	/**
	 * @var array Error messages
	 */
	public $errors = array();

	/**
	 * @var array Results to return to hook manager
	 */
	public $results = array();

	/**
	 * @var string HTML to inject into page
	 */
	public $resprints = '';

	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Add "Process Breakdowns" button to Reception card action bar
	 *
	 * @param  array  $parameters Hook parameters
	 * @param  object $object     Current object (Reception)
	 * @param  string $action     Current action
	 * @param  object $hookmanager Hook manager
	 * @return int                0=continue, 1=replace
	 */
	public function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $langs, $user;

		if (!isModEnabled('bulkbreakdown')) {
			return 0;
		}

		// Only act on reception cards
		if (!isset($object->element) || $object->element !== 'reception') {
			return 0;
		}

		// Only show on validated or closed receptions
		if ($object->statut < 1) {
			return 0;
		}

		// Check permission
		if (!$user->hasRight('bulkbreakdown', 'breakdown', 'process')) {
			return 0;
		}

		$langs->load('bulkbreakdown@bulkbreakdown');

		// Check if any reception lines have active breakdown rules
		$sql = "SELECT COUNT(*) as nb";
		$sql .= " FROM ".MAIN_DB_PREFIX."receptiondet_batch rd";
		$sql .= " INNER JOIN ".MAIN_DB_PREFIX."bulkbreakdown_rule br ON br.fk_product = rd.fk_product AND br.active = 1";
		$sql .= " WHERE rd.fk_reception = ".((int) $object->id);
		$sql .= " AND br.entity IN (".getEntity('bulkbreakdown').")";

		$resql = $this->db->query($sql);
		if ($resql) {
			$obj = $this->db->fetch_object($resql);
			if ($obj->nb > 0) {
				if ($object->statut >= 2) {
					// Reception is closed — button is active
					$url = dol_buildpath('/bulkbreakdown/process_breakdown.php', 1).'?reception_id='.$object->id;
					print '<a class="butAction" href="'.$url.'">';
					print img_picto('', 'mrp', 'class="pictofixedwidth"');
					print $langs->trans('ProcessBreakdowns');
					print '</a>';
				} else {
					// Reception is validated but not closed — button is grayed out
					print '<a class="butActionRefused classfortooltip" href="#" title="'.dol_escape_htmltag($langs->trans('ReceptionMustBeClosed')).'">';
					print img_picto('', 'mrp', 'class="pictofixedwidth"');
					print $langs->trans('ProcessBreakdowns');
					print '</a>';
				}
			}
		}

		return 0;
	}
}
