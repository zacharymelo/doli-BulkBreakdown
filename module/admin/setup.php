<?php
/* Copyright (C) 2026 Zachary Melo
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    admin/setup.php
 * \ingroup bulkbreakdown
 * \brief   Admin setup page for Bulk Breakdown module.
 */

$res = 0;
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res && file_exists("../../../../main.inc.php")) {
	$res = @include "../../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/html.formproduct.class.php';

$langs->loadLangs(array('admin', 'bulkbreakdown@bulkbreakdown'));

// Permission check
if (!$user->admin) {
	accessforbidden();
}

$action = GETPOST('action', 'aZ09');

// ---- Actions ----

if ($action == 'update') {
	$wh = GETPOSTINT('BULKBREAKDOWN_DEFAULT_WAREHOUSE');
	dolibarr_set_const($db, 'BULKBREAKDOWN_DEFAULT_WAREHOUSE', $wh, 'chaine', 0, '', $conf->entity);

	setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
}

// ---- Display ----

llxHeader('', $langs->trans('BulkbreakdownSetup'));

print load_fiche_titre($langs->trans('BulkbreakdownSetup'), '', 'title_setup');

print '<div class="opacitymedium" style="margin-bottom:15px;">';
print $langs->trans('BulkbreakdownAbout');
print '</div>';

// Check dependencies
$missingDeps = array();
if (!isModEnabled('bom')) {
	$missingDeps[] = 'BOM';
}
if (!isModEnabled('mrp')) {
	$missingDeps[] = 'MRP';
}
if (!isModEnabled('stock')) {
	$missingDeps[] = 'Stock';
}
if (!isModEnabled('product')) {
	$missingDeps[] = 'Product';
}
if (!isModEnabled('reception')) {
	$missingDeps[] = 'Reception';
}
if (!empty($missingDeps)) {
	print '<div class="warning">';
	print img_warning().' Required modules not enabled: '.implode(', ', $missingDeps);
	print '</div><br>';
}

$form = new Form($db);
$formproduct = new FormProduct($db);

print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="update">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans('Parameter').'</td>';
print '<td>'.$langs->trans('Value').'</td>';
print '</tr>';

// Default warehouse
print '<tr class="oddeven"><td>';
print $langs->trans('DefaultWarehouse');
print '<br><span class="opacitymedium small">'.$langs->trans('DefaultWarehouseDesc').'</span>';
print '</td><td>';
print $formproduct->selectWarehouses(getDolGlobalString('BULKBREAKDOWN_DEFAULT_WAREHOUSE'), 'BULKBREAKDOWN_DEFAULT_WAREHOUSE', '', 1, 0, 0, '', 0, 0, array(), 'minwidth200');
print '</td></tr>';

print '</table>';

print '<br><div class="center">';
print '<input type="submit" class="button" value="'.$langs->trans('Save').'">';
print '</div>';
print '</form>';

llxFooter();
$db->close();
