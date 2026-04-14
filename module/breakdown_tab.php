<?php
/* Copyright (C) 2026 Zachary Melo
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    breakdown_tab.php
 * \ingroup bulkbreakdown
 * \brief   Breakdown tab on the Product card — define/edit breakdown rules.
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
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/product.lib.php';
dol_include_once('/bulkbreakdown/class/breakdownrule.class.php');

$langs->loadLangs(array('products', 'mrp', 'bulkbreakdown@bulkbreakdown'));

// Parameters
$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');

// Permission check
if (!$user->hasRight('bulkbreakdown', 'breakdown', 'read')) {
	accessforbidden();
}

if (empty($id)) {
	accessforbidden('Missing product ID');
}

$permwrite = $user->hasRight('bulkbreakdown', 'breakdown', 'write');

// Load product
$product = new Product($db);
$result = $product->fetch($id);
if ($result <= 0) {
	dol_print_error($db, $product->error);
	exit;
}

// Load existing rule
$rule = new BreakdownRule($db);
$ruleExists = $rule->fetchByProduct($id);

$form = new Form($db);

// ---- Actions ----

if ($action == 'createRule' && $permwrite) {
	$rule = new BreakdownRule($db);
	$rule->fk_product = $id;
	$rule->fk_bom = GETPOSTINT('fk_bom');
	$rule->auto_process = GETPOSTINT('auto_process');
	$rule->active = 1;
	$rule->note = GETPOST('note', 'restricthtml');

	if ($rule->fk_bom <= 0) {
		setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv('SelectBOM')), null, 'errors');
	} else {
		$result = $rule->create($user);
		if ($result > 0) {
			setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
			header('Location: '.$_SERVER['PHP_SELF'].'?id='.$id);
			exit;
		} else {
			setEventMessages($rule->error, $rule->errors, 'errors');
		}
	}
}

if ($action == 'updateRule' && $permwrite && $ruleExists > 0) {
	$rule->fk_bom = GETPOSTINT('fk_bom');
	$rule->auto_process = GETPOSTINT('auto_process');
	$rule->note = GETPOST('note', 'restricthtml');

	if ($rule->fk_bom <= 0) {
		setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv('SelectBOM')), null, 'errors');
	} else {
		$result = $rule->update($user);
		if ($result > 0) {
			setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
			header('Location: '.$_SERVER['PHP_SELF'].'?id='.$id);
			exit;
		} else {
			setEventMessages($rule->error, $rule->errors, 'errors');
		}
	}
}

if ($action == 'confirmDeleteRule' && $permwrite && $ruleExists > 0) {
	$result = $rule->delete($user);
	if ($result > 0) {
		setEventMessages($langs->trans('RecordDeleted'), null, 'mesgs');
		header('Location: '.$_SERVER['PHP_SELF'].'?id='.$id);
		exit;
	} else {
		setEventMessages($rule->error, $rule->errors, 'errors');
	}
}

// ---- Display ----

llxHeader('', $langs->trans('Breakdown').' - '.$product->ref);

$head = product_prepare_head($product);
print dol_get_fiche_head($head, 'bulkbreakdown', $langs->trans('Product'), -1, $product->picto);

// Product banner
$linkback = '<a href="'.DOL_URL_ROOT.'/product/list.php?restore_lastsearch_value=1">'.$langs->trans('BackToList').'</a>';
dol_banner_tab($product, 'id', $linkback, 1, 'rowid', 'ref');

print '<div class="fichecenter">';
print '<div class="underbanner clearboth"></div>';

// Delete confirmation
if ($action == 'deleteRule' && $ruleExists > 0) {
	print $form->formconfirm(
		$_SERVER['PHP_SELF'].'?id='.$id,
		$langs->trans('DeleteBreakdownRule'),
		$langs->trans('ConfirmDeleteBreakdownRule'),
		'confirmDeleteRule',
		'',
		'',
		1
	);
}

// Fetch available disassembly BOMs for this product
$bomOptions = array();
$sql = "SELECT b.rowid, b.ref, b.label FROM ".MAIN_DB_PREFIX."bom_bom b";
$sql .= " WHERE b.fk_product = ".((int) $id);
$sql .= " AND b.bomtype = 1"; // Disassembly only
$sql .= " AND b.status = 1"; // Validated only
$sql .= " AND b.entity IN (".getEntity('bom').")";
$sql .= " ORDER BY b.ref ASC";
$resql = $db->query($sql);
if ($resql) {
	while ($obj = $db->fetch_object($resql)) {
		$bomOptions[$obj->rowid] = $obj->ref.($obj->label ? ' - '.$obj->label : '');
	}
	$db->free($resql);
}

if ($ruleExists > 0) {
	// ---- Show existing rule ----
	$isEditing = ($action == 'editRule');

	// Fetch BOM output lines for summary
	dol_include_once('/bulkbreakdown/lib/bulkbreakdown.lib.php');
	$outputLines = fetchBomOutputLines($db, $rule->fk_bom);

	// Check BOM status
	$bomActive = isset($bomOptions[$rule->fk_bom]);
	if (!$bomActive) {
		$sqlCheck = "SELECT b.rowid, b.ref, b.status FROM ".MAIN_DB_PREFIX."bom_bom b WHERE b.rowid = ".((int) $rule->fk_bom);
		$resCheck = $db->query($sqlCheck);
		if ($resCheck && $db->num_rows($resCheck) > 0) {
			$bomObj = $db->fetch_object($resCheck);
			print '<div class="warning">';
			print img_warning().' '.$langs->trans('BOMInactive');
			print ' (<a href="'.DOL_URL_ROOT.'/bom/bom_card.php?id='.$bomObj->rowid.'">'.$bomObj->ref.'</a>)';
			print '</div>';
		}
	}

	if ($isEditing && $permwrite) {
		// ---- Edit form ----
		print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'?id='.$id.'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="updateRule">';

		print '<table class="border centpercent tableforfield">';

		// BOM selector
		print '<tr><td class="titlefield fieldrequired">'.$langs->trans('SelectBOM').'</td><td>';
		if (!empty($bomOptions)) {
			print $form->selectarray('fk_bom', $bomOptions, $rule->fk_bom, 1, 0, 0, '', 0, 0, 0, '', 'minwidth200');
		} else {
			print '<span class="opacitymedium">'.$langs->trans('NoBOMFound').'</span>';
		}
		print '</td></tr>';

		// Auto-process override
		$autoOptions = array('-1' => $langs->trans('AutoProcessUseGlobal'), '0' => $langs->trans('AutoProcessManual'), '1' => $langs->trans('AutoProcessAuto'));
		print '<tr><td>'.$langs->trans('AutoProcess').'</td><td>';
		print $form->selectarray('auto_process', $autoOptions, $rule->auto_process, 0, 0, 0, '', 0, 0, 0, '', 'minwidth200');
		print '</td></tr>';

		// Note
		print '<tr><td>'.$langs->trans('Note').'</td><td>';
		print '<textarea name="note" rows="3" class="flat minwidth300">'.dol_escape_htmltag($rule->note).'</textarea>';
		print '</td></tr>';

		print '</table>';
		print '<div class="center"><input type="submit" class="button" value="'.$langs->trans('Save').'"> ';
		print '<a class="button button-cancel" href="'.$_SERVER['PHP_SELF'].'?id='.$id.'">'.$langs->trans('Cancel').'</a></div>';
		print '</form>';
	} else {
		// ---- View mode ----
		print '<table class="border centpercent tableforfield">';

		// BOM
		print '<tr><td class="titlefield">'.$langs->trans('SelectBOM').'</td><td>';
		print '<a href="'.DOL_URL_ROOT.'/bom/bom_card.php?id='.((int) $rule->fk_bom).'">';
		print img_picto('', 'bom', 'class="pictofixedwidth"');
		print dol_escape_htmltag(isset($bomOptions[$rule->fk_bom]) ? $bomOptions[$rule->fk_bom] : '#'.$rule->fk_bom);
		print '</a>';
		print '</td></tr>';

		// Breakdown summary
		if (!empty($outputLines)) {
			print '<tr><td>'.$langs->trans('BreakdownSummary', '', '', '', '').'</td><td>';
			foreach ($outputLines as $outLine) {
				print img_picto('', 'product', 'class="pictofixedwidth"');
				print '<a href="'.DOL_URL_ROOT.'/product/card.php?id='.((int) $outLine->fk_product).'">';
				print dol_escape_htmltag($outLine->product_ref).'</a>';
				print ' - '.dol_escape_htmltag($outLine->product_label);
				print ' <strong>(x'.(float) $outLine->qty.')</strong>';
				print '<br>';
			}
			print '</td></tr>';
		}

		// Auto-process
		print '<tr><td>'.$langs->trans('AutoProcess').'</td><td>';
		if ($rule->auto_process == 1) {
			print '<span class="badge badge-status4">'.$langs->trans('AutoProcessAuto').'</span>';
		} elseif ($rule->auto_process == 0) {
			print '<span class="badge badge-status0">'.$langs->trans('AutoProcessManual').'</span>';
		} else {
			print '<span class="badge badge-status1">'.$langs->trans('AutoProcessUseGlobal').'</span>';
			print ' <span class="opacitymedium">('.($langs->trans(getDolGlobalString('BULKBREAKDOWN_AUTO_PROCESS') ? 'AutoProcessAuto' : 'AutoProcessManual')).')</span>';
		}
		print '</td></tr>';

		// Note
		if (!empty($rule->note)) {
			print '<tr><td>'.$langs->trans('Note').'</td><td>'.dol_escape_htmltag($rule->note).'</td></tr>';
		}

		print '</table>';

		// Action buttons
		if ($permwrite) {
			print '<div class="tabsAction">';
			print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?id='.$id.'&action=editRule&token='.newToken().'">'.$langs->trans('EditBreakdownRule').'</a>';
			print '<a class="butActionDelete" href="'.$_SERVER['PHP_SELF'].'?id='.$id.'&action=deleteRule&token='.newToken().'">'.$langs->trans('DeleteBreakdownRule').'</a>';
			print '</div>';
		}
	}
} else {
	// ---- No rule — show create form ----
	if ($permwrite) {
		if (empty($bomOptions)) {
			print '<div class="opacitymedium">'.$langs->trans('NoBOMFound').'</div>';
			print '<br><a class="butAction" href="'.DOL_URL_ROOT.'/bom/bom_card.php?action=create&fk_product='.$id.'&bomtype=1">';
			print $langs->trans('CreateObject').' ('.$langs->trans('BOM').' - Disassembly)';
			print '</a>';
		} else {
			print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'?id='.$id.'">';
			print '<input type="hidden" name="token" value="'.newToken().'">';
			print '<input type="hidden" name="action" value="createRule">';

			print '<table class="border centpercent tableforfield">';

			// BOM selector
			print '<tr><td class="titlefield fieldrequired">'.$langs->trans('SelectBOM').'</td><td>';
			print $form->selectarray('fk_bom', $bomOptions, GETPOSTINT('fk_bom'), 1, 0, 0, '', 0, 0, 0, '', 'minwidth200');
			print '</td></tr>';

			// Auto-process override
			$autoOptions = array('-1' => $langs->trans('AutoProcessUseGlobal'), '0' => $langs->trans('AutoProcessManual'), '1' => $langs->trans('AutoProcessAuto'));
			print '<tr><td>'.$langs->trans('AutoProcess').'</td><td>';
			print $form->selectarray('auto_process', $autoOptions, GETPOST('auto_process', 'int') !== '' ? GETPOSTINT('auto_process') : -1, 0, 0, 0, '', 0, 0, 0, '', 'minwidth200');
			print '</td></tr>';

			// Note
			print '<tr><td>'.$langs->trans('Note').'</td><td>';
			print '<textarea name="note" rows="3" class="flat minwidth300">'.GETPOST('note', 'restricthtml').'</textarea>';
			print '</td></tr>';

			print '</table>';
			print '<div class="center"><input type="submit" class="button" value="'.$langs->trans('CreateBreakdownRule').'"></div>';
			print '</form>';
		}
	} else {
		print '<div class="opacitymedium">'.$langs->trans('NoBreakdownRule').'</div>';
	}
}

print '</div>'; // fichecenter
print dol_get_fiche_end();
llxFooter();
$db->close();
