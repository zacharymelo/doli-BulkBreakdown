<?php
/* Copyright (C) 2026 Zachary Melo
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    process_breakdown.php
 * \ingroup bulkbreakdown
 * \brief   Dedicated page for processing bulk breakdowns from a reception.
 *          Shows per-line checkboxes so the user can select which to convert.
 */

$res = 0;
if (!$res && file_exists("../main.inc.php")) {
	$res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/reception/class/reception.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
dol_include_once('/bulkbreakdown/lib/bulkbreakdown.lib.php');

$langs->loadLangs(array('products', 'mrp', 'receptions', 'bulkbreakdown@bulkbreakdown'));

// Parameters
$receptionId = GETPOSTINT('reception_id');
$action = GETPOST('action', 'aZ09');

// Permission check
if (!$user->hasRight('bulkbreakdown', 'breakdown', 'process')) {
	accessforbidden();
}

if (empty($receptionId)) {
	accessforbidden($langs->trans('ErrorReceptionNotFound'));
}

// Load reception
$reception = new Reception($db);
$result = $reception->fetch($receptionId);
if ($result <= 0) {
	dol_print_error($db, $reception->error);
	exit;
}

$form = new Form($db);

// Fetch lines with breakdown rules
$breakdownLines = fetchBreakdownRulesForReception($db, $receptionId);

// For each line, check if already processed
$lineStatus = array();
foreach ($breakdownLines as $line) {
	$linkedMOs = getLinkedMOsForReceptionProduct($db, $receptionId, $line->fk_product);
	$lineStatus[$line->receptiondet_id] = $linkedMOs;
}

// ---- Process action ----

if ($action == 'confirmProcess') {
	$selectedLines = GETPOST('process_line', 'array');
	if (empty($selectedLines)) {
		setEventMessages($langs->trans('NoLinesSelected'), null, 'warnings');
	} else {
		$processedCount = 0;
		$createdMOs = array();
		$errors = array();

		foreach ($selectedLines as $receptiondetId) {
			$receptiondetId = (int) $receptiondetId;

			// Find the matching line data
			$lineData = null;
			foreach ($breakdownLines as $bl) {
				if ((int) $bl->receptiondet_id == $receptiondetId) {
					$lineData = $bl;
					break;
				}
			}
			if (!$lineData) {
				continue;
			}

			// Skip already processed
			if (!empty($lineStatus[$receptiondetId])) {
				continue;
			}

			// Use the warehouse from the reception line (where the goods were received)
			$warehouse = (int) $lineData->fk_entrepot;
			if ($warehouse <= 0) {
				$errors[] = $langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv('Warehouse')).' ('.$lineData->product_ref.')';
				continue;
			}

			// Calculate total purchase price for cost propagation
			// The reception line (receptiondet_batch.fk_elementdet) points to the
			// supplier order line (commande_fournisseurdet) which has the subprice.
			$totalPrice = 0;
			$sqlPrice = "SELECT cfd.subprice";
			$sqlPrice .= " FROM ".MAIN_DB_PREFIX."commande_fournisseurdet cfd";
			$sqlPrice .= " INNER JOIN ".MAIN_DB_PREFIX."receptiondet_batch rd ON rd.fk_elementdet = cfd.rowid";
			$sqlPrice .= " WHERE rd.rowid = ".((int) $receptiondetId);
			$sqlPrice .= " LIMIT 1";
			$resPrice = $db->query($sqlPrice);
			if ($resPrice && $db->num_rows($resPrice) > 0) {
				$priceObj = $db->fetch_object($resPrice);
				$totalPrice = (float) $priceObj->subprice * (float) $lineData->qty;
			}

			$moId = processBreakdownLine(
				$db,
				$user,
				(int) $lineData->fk_bom,
				(int) $lineData->fk_product,
				(float) $lineData->qty,
				$warehouse,
				$receptionId,
				$totalPrice
			);

			if ($moId > 0) {
				$processedCount++;
				// Fetch MO ref for the success message
				$sqlRef = "SELECT ref FROM ".MAIN_DB_PREFIX."mrp_mo WHERE rowid = ".((int) $moId);
				$resRef = $db->query($sqlRef);
				if ($resRef && $db->num_rows($resRef) > 0) {
					$refObj = $db->fetch_object($resRef);
					$createdMOs[] = $refObj->ref;
				}
			} else {
				switch ($moId) {
					case -1:
						$errors[] = $langs->trans('ErrorBOMInactive', $lineData->bom_ref);
						break;
					case -2:
						$errors[] = $langs->trans('ErrorBOMNotDisassembly', $lineData->bom_ref);
						break;
					case -3:
						$errors[] = $langs->trans('ErrorMOCreateFailed').' ('.$lineData->product_ref.')';
						break;
					case -4:
						$errors[] = $langs->trans('ErrorMOValidateFailed').' ('.$lineData->product_ref.')';
						break;
					default:
						$errors[] = $langs->trans('ErrorStockMoveFailed').' ('.$lineData->product_ref.')';
						break;
				}
			}
		}

		if ($processedCount > 0) {
			$msg = $langs->trans('BreakdownsProcessed', $processedCount);
			if (!empty($createdMOs)) {
				$msg .= ' MO: '.implode(', ', $createdMOs);
			}
			setEventMessages($msg, null, 'mesgs');
		}
		if (!empty($errors)) {
			setEventMessages(null, $errors, 'errors');
		}

		// Redirect back to reception
		header('Location: '.DOL_URL_ROOT.'/reception/card.php?id='.$receptionId);
		exit;
	}
}

// ---- Display ----

llxHeader('', $langs->trans('ProcessBreakdowns').' - '.$reception->ref);

print load_fiche_titre(
	$langs->trans('ProcessBreakdowns'),
	'<a href="'.DOL_URL_ROOT.'/reception/card.php?id='.$receptionId.'">'.$langs->trans('BackToList').'</a>',
	'mrp'
);

// Reception info
print '<div class="fichecenter">';
print '<table class="border centpercent tableforfield">';
print '<tr><td class="titlefield">'.$langs->trans('Reception').'</td><td>';
print '<a href="'.DOL_URL_ROOT.'/reception/card.php?id='.$receptionId.'">';
print img_picto('', 'dollyrevert', 'class="pictofixedwidth"');
print dol_escape_htmltag($reception->ref).'</a>';
print '</td></tr>';
print '</table>';
print '</div>';

print '<br>';

if (empty($breakdownLines)) {
	print '<div class="opacitymedium">'.$langs->trans('NoBreakdownProducts').'</div>';
} else {
	print '<div class="opacitymedium" style="margin-bottom:10px;">';
	print $langs->trans('SelectLinesToProcess');
	print '</div>';

	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'?reception_id='.$receptionId.'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="confirmProcess">';

	print '<table class="tagtable nobottomiftotal liste">';
	print '<tr class="liste_titre">';
	print '<th class="liste_titre center" width="30"><input type="checkbox" id="checkall"></th>';
	print '<th class="liste_titre">'.$langs->trans('Product').'</th>';
	print '<th class="liste_titre right">'.$langs->trans('Qty').'</th>';
	print '<th class="liste_titre">'.$langs->trans('BOM').'</th>';
	print '<th class="liste_titre">'.$langs->trans('BreakdownSummary', '', '', '', '').'</th>';
	print '<th class="liste_titre">'.$langs->trans('Warehouse').'</th>';  // From reception line
	print '<th class="liste_titre center">'.$langs->trans('Status').'</th>';
	print '</tr>';

	foreach ($breakdownLines as $line) {
		$alreadyProcessed = !empty($lineStatus[$line->receptiondet_id]);

		// Fetch output lines for this BOM
		$outputLines = fetchBomOutputLines($db, $line->fk_bom);

		print '<tr class="oddeven">';

		// Checkbox — pre-checked based on auto_process (per-rule override > global default)
		print '<td class="center">';
		if (!$alreadyProcessed && $line->bom_status == 1) {
			$autoProcess = (int) $line->auto_process;
			if ($autoProcess == -1) {
				// Use global default
				$preChecked = getDolGlobalString('BULKBREAKDOWN_AUTO_PROCESS') ? true : false;
			} else {
				$preChecked = ($autoProcess == 1);
			}
			$chk = $preChecked ? ' checked' : '';
			print '<input type="checkbox" name="process_line[]" value="'.$line->receptiondet_id.'"'.$chk.' class="process-checkbox">';
		}
		print '</td>';

		// Product
		print '<td class="nowraponall">';
		print '<a href="'.DOL_URL_ROOT.'/product/card.php?id='.((int) $line->fk_product).'">';
		print img_picto('', 'product', 'class="pictofixedwidth"');
		print dol_escape_htmltag($line->product_ref).'</a>';
		print ' - '.dol_escape_htmltag($line->product_label);
		print '</td>';

		// Qty received
		print '<td class="right">'.(float) $line->qty.'</td>';

		// BOM
		print '<td class="nowraponall">';
		print '<a href="'.DOL_URL_ROOT.'/bom/bom_card.php?id='.((int) $line->fk_bom).'">';
		print img_picto('', 'bom', 'class="pictofixedwidth"');
		print dol_escape_htmltag($line->bom_ref).'</a>';
		if ($line->bom_status != 1) {
			print ' '.img_warning($langs->trans('BOMInactive'));
		}
		print '</td>';

		// Breakdown summary (output products)
		print '<td>';
		if (!empty($outputLines)) {
			$summaryParts = array();
			foreach ($outputLines as $outLine) {
				// Calculate actual output qty based on received qty
				$bomQty = (float) $line->bom_qty > 0 ? (float) $line->bom_qty : 1;
				$outputQty = ((float) $outLine->qty / $bomQty) * (float) $line->qty;
				$summaryParts[] = (float) $outputQty.'x '.dol_escape_htmltag($outLine->product_ref);
			}
			print '&rarr; '.implode(', ', $summaryParts);
		}
		print '</td>';

		// Warehouse (from reception line)
		print '<td class="nowraponall">';
		if ((int) $line->fk_entrepot > 0) {
			require_once DOL_DOCUMENT_ROOT.'/product/stock/class/entrepot.class.php';
			$tmpwh = new Entrepot($db);
			$tmpwh->fetch((int) $line->fk_entrepot);
			print img_picto('', 'stock', 'class="pictofixedwidth"');
			print dol_escape_htmltag($tmpwh->ref);
		}
		print '</td>';

		// Status
		print '<td class="center nowraponall">';
		if ($alreadyProcessed) {
			$linkedMOs = $lineStatus[$line->receptiondet_id];
			print '<span class="badge badge-status4">'.$langs->trans('AlreadyProcessed').'</span><br>';
			foreach ($linkedMOs as $lmo) {
				print '<a href="'.DOL_URL_ROOT.'/mrp/mo_card.php?id='.((int) $lmo->rowid).'">';
				print img_picto('', 'mrp', 'class="pictofixedwidth"');
				print dol_escape_htmltag($lmo->ref).'</a> ';
			}
		} elseif ($line->bom_status != 1) {
			print '<span class="badge badge-status8">'.$langs->trans('BOMInactive').'</span>';
		} else {
			print '<span class="badge badge-status0">'.$langs->trans('Draft').'</span>';
		}
		print '</td>';

		print '</tr>';
	}

	print '</table>';

	print '<br>';
	print '<div class="center">';
	print '<input type="submit" class="button" value="'.$langs->trans('ProcessBreakdowns').'">';
	print ' <a class="button button-cancel" href="'.DOL_URL_ROOT.'/reception/card.php?id='.$receptionId.'">'.$langs->trans('Cancel').'</a>';
	print '</div>';
	print '</form>';

	// JS for check-all toggle
	print '<script>
	$(document).ready(function() {
		function syncCheckAll() {
			var total = $(".process-checkbox").length;
			var checked = $(".process-checkbox:checked").length;
			$("#checkall").prop("checked", total > 0 && checked === total);
		}
		syncCheckAll();
		$("#checkall").on("change", function() {
			$(".process-checkbox").prop("checked", $(this).prop("checked"));
		});
		$(".process-checkbox").on("change", function() {
			syncCheckAll();
		});
	});
	</script>';
}

llxFooter();
$db->close();
