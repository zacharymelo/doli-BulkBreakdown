<?php
/* Copyright (C) 2026 Zachary Melo
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    lib/bulkbreakdown.lib.php
 * \ingroup bulkbreakdown
 * \brief   Helper functions for Bulk Breakdown module
 */

/**
 * Fetch all reception lines that have active breakdown rules
 *
 * @param  DoliDB $db          Database handler
 * @param  int    $receptionId Reception ID
 * @return array               Array of objects with line info + rule info, or empty array
 */
function fetchBreakdownRulesForReception($db, $receptionId)
{
	$lines = array();

	// Get reception lines joined with breakdown rules and BOM info
	$sql = "SELECT rd.rowid as receptiondet_id, rd.fk_product, rd.qty,";
	$sql .= " p.ref as product_ref, p.label as product_label,";
	$sql .= " br.rowid as rule_id, br.fk_bom, br.fk_warehouse_source, br.fk_warehouse_dest,";
	$sql .= " b.ref as bom_ref, b.label as bom_label, b.status as bom_status, b.bomtype,";
	$sql .= " b.fk_product as bom_product_id, b.qty as bom_qty";
	$sql .= " FROM ".MAIN_DB_PREFIX."receptiondet_batch rd";
	$sql .= " INNER JOIN ".MAIN_DB_PREFIX."bulkbreakdown_rule br ON br.fk_product = rd.fk_product AND br.active = 1";
	$sql .= " INNER JOIN ".MAIN_DB_PREFIX."product p ON p.rowid = rd.fk_product";
	$sql .= " INNER JOIN ".MAIN_DB_PREFIX."bom_bom b ON b.rowid = br.fk_bom";
	$sql .= " WHERE rd.fk_reception = ".((int) $receptionId);
	$sql .= " AND br.entity IN (".getEntity('bulkbreakdown').")";
	$sql .= " ORDER BY rd.rowid ASC";

	$resql = $db->query($sql);
	if (!$resql) {
		return $lines;
	}

	while ($obj = $db->fetch_object($resql)) {
		$lines[] = $obj;
	}
	$db->free($resql);

	return $lines;
}

/**
 * Fetch BOM output lines (products produced by a disassembly BOM)
 *
 * @param  DoliDB $db    Database handler
 * @param  int    $bomId BOM ID
 * @return array         Array of objects with fk_product, qty, product ref/label
 */
function fetchBomOutputLines($db, $bomId)
{
	$lines = array();

	$sql = "SELECT bl.fk_product, bl.qty, bl.qty_frozen, bl.efficiency,";
	$sql .= " p.ref as product_ref, p.label as product_label";
	$sql .= " FROM ".MAIN_DB_PREFIX."bom_bomline bl";
	$sql .= " INNER JOIN ".MAIN_DB_PREFIX."product p ON p.rowid = bl.fk_product";
	$sql .= " WHERE bl.fk_bom = ".((int) $bomId);
	$sql .= " ORDER BY bl.position ASC";

	$resql = $db->query($sql);
	if (!$resql) {
		return $lines;
	}

	while ($obj = $db->fetch_object($resql)) {
		$lines[] = $obj;
	}
	$db->free($resql);

	return $lines;
}

/**
 * Check if a reception line has already been processed (has a linked MO)
 *
 * @param  DoliDB $db              Database handler
 * @param  int    $receptionId     Reception ID
 * @param  int    $productId       Product ID of the bulk product
 * @return array                   Array of linked MO objects (rowid, ref), empty if none
 */
function getLinkedMOsForReceptionProduct($db, $receptionId, $productId)
{
	$mos = array();

	// Check element_element for MOs linked to this reception
	$sql = "SELECT mo.rowid, mo.ref";
	$sql .= " FROM ".MAIN_DB_PREFIX."element_element el";
	$sql .= " INNER JOIN ".MAIN_DB_PREFIX."mrp_mo mo ON mo.rowid = el.fk_source";
	$sql .= " WHERE el.sourcetype = 'mo'";
	$sql .= " AND el.targettype = 'reception'";
	$sql .= " AND el.fk_target = ".((int) $receptionId);
	$sql .= " AND mo.fk_product = ".((int) $productId);

	$resql = $db->query($sql);
	if (!$resql) {
		return $mos;
	}

	while ($obj = $db->fetch_object($resql)) {
		$mos[] = $obj;
	}
	$db->free($resql);

	return $mos;
}

/**
 * Process a single breakdown line: create MO, validate, produce stock movements
 *
 * @param  DoliDB $db          Database handler
 * @param  User   $user        Current user
 * @param  int    $bomId       BOM ID (disassembly type)
 * @param  int    $productId   Bulk product ID (consumed)
 * @param  float  $receivedQty Quantity received of the bulk product
 * @param  int    $whSource    Source warehouse ID (consume from)
 * @param  int    $whDest      Destination warehouse ID (produce into)
 * @param  int    $receptionId Reception ID (for linking)
 * @param  float  $unitPrice   Total purchase price for cost propagation
 * @return int                 MO ID if OK, <0 if error
 */
function processBreakdownLine($db, $user, $bomId, $productId, $receivedQty, $whSource, $whDest, $receptionId, $unitPrice = 0)
{
	global $langs;

	require_once DOL_DOCUMENT_ROOT.'/bom/class/bom.class.php';
	require_once DOL_DOCUMENT_ROOT.'/mrp/class/mo.class.php';
	require_once DOL_DOCUMENT_ROOT.'/mrp/class/moline.class.php';
	require_once DOL_DOCUMENT_ROOT.'/product/stock/class/mouvementstock.class.php';
	require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';

	$error = 0;

	// 1. Fetch and validate BOM
	$bom = new BOM($db);
	$bom->fetch($bomId);
	$bom->fetchLines();

	if ($bom->status != $bom::STATUS_VALIDATED) {
		return -1; // BOM not validated
	}
	if ($bom->bomtype != 1) {
		return -2; // Not a disassembly BOM
	}

	// 2. For disassembly BOM: fk_product = the bulk product (consumed)
	// BOM lines = output products (produced)
	// MO qty = quantity of the bulk product to disassemble
	$db->begin();

	// 3. Create Manufacturing Order
	$mo = new Mo($db);
	$mo->fk_bom = $bom->id;
	$mo->fk_product = $bom->fk_product; // The bulk product
	$mo->qty = $receivedQty;
	$mo->fk_warehouse = $whDest;
	$mo->mrptype = 1; // Disassembly (will be overridden by create() from BOM anyway)
	$mo->label = $langs->trans('Breakdown').' - '.$bom->ref;
	$mo->date_start_planned = dol_now();

	$resultCreate = $mo->create($user);
	if ($resultCreate <= 0) {
		$db->rollback();
		return -3;
	}

	// 4. Validate the MO
	$resultValidate = $mo->validate($user);
	if ($resultValidate < 0) {
		$db->rollback();
		return -4;
	}

	// Reload MO with lines
	$mo->fetch($mo->id);
	$mo->fetchLines();

	// 5. Process consumption (bulk product) and production (unit products)
	$pos = 0;

	foreach ($mo->lines as $line) {
		if ($line->role == 'toconsume') {
			// Consume the bulk product from source warehouse
			$stockmove = new MouvementStock($db);
			$stockmove->setOrigin($mo->element, $mo->id);
			$stockmove->context['mrp_role'] = 'toconsume';

			$labelmovement = $langs->trans('Breakdown').' - MO '.$mo->ref;

			$idstockmove = $stockmove->livraison(
				$user,
				$line->fk_product,
				$whSource,
				$line->qty,
				0,
				$labelmovement,
				dol_now()
			);

			if ($idstockmove < 0) {
				$error++;
				break;
			}

			// Record consumed MoLine
			$moline = new MoLine($db);
			$moline->fk_mo = $mo->id;
			$moline->position = $pos;
			$moline->fk_product = $line->fk_product;
			$moline->fk_warehouse = $whSource;
			$moline->qty = $line->qty;
			$moline->role = 'consumed';
			$moline->fk_mrp_production = $line->id;
			$moline->fk_stock_movement = $idstockmove;
			$moline->fk_user_creat = $user->id;

			$resultmoline = $moline->create($user);
			if ($resultmoline <= 0) {
				$error++;
				break;
			}
			$pos++;
		} elseif ($line->role == 'toproduce') {
			// Produce the unit product into destination warehouse
			$stockmove = new MouvementStock($db);
			$stockmove->origin_type = $mo->element;
			$stockmove->origin_id = $mo->id;
			$stockmove->context['mrp_role'] = 'toproduce';

			// Calculate unit cost for PMP update
			$pricePerUnit = 0;
			if ($unitPrice > 0 && $line->qty > 0) {
				$pricePerUnit = $unitPrice / $line->qty;
			}

			$labelmovement = $langs->trans('Breakdown').' - MO '.$mo->ref;

			// Check if product is batch-tracked
			$tmpproduct = new Product($db);
			$tmpproduct->fetch($line->fk_product);
			$batch = '';
			if (isModEnabled('productbatch') && $tmpproduct->status_batch) {
				// Auto-generate batch from MO ref
				$batch = $mo->ref;
			}

			$idstockmove = $stockmove->reception(
				$user,
				$line->fk_product,
				$whDest,
				$line->qty,
				$pricePerUnit,
				$labelmovement,
				'',
				'',
				$batch,
				dol_now()
			);

			if ($idstockmove < 0) {
				$error++;
				break;
			}

			// Record produced MoLine
			$moline = new MoLine($db);
			$moline->fk_mo = $mo->id;
			$moline->position = $pos;
			$moline->fk_product = $line->fk_product;
			$moline->fk_warehouse = $whDest;
			$moline->qty = $line->qty;
			$moline->batch = $batch;
			$moline->role = 'produced';
			$moline->fk_mrp_production = $line->id;
			$moline->fk_stock_movement = $idstockmove;
			$moline->fk_user_creat = $user->id;

			$resultmoline = $moline->create($user);
			if ($resultmoline <= 0) {
				$error++;
				break;
			}
			$pos++;
		}
	}

	// 6. Close the MO (set to Produced)
	if (!$error) {
		$sql = "UPDATE ".MAIN_DB_PREFIX."mrp_mo SET status = ".Mo::STATUS_PRODUCED;
		$sql .= " WHERE rowid = ".((int) $mo->id);
		$resql = $db->query($sql);
		if (!$resql) {
			$error++;
		}
	}

	// 7. Link MO to reception via element_element
	if (!$error) {
		$mo->add_object_linked('reception', $receptionId);
	}

	if ($error) {
		$db->rollback();
		return -5;
	}

	$db->commit();
	return $mo->id;
}
