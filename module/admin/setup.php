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

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';

$langs->loadLangs(array('admin', 'bulkbreakdown@bulkbreakdown'));

// Permission check
if (!$user->admin) {
	accessforbidden();
}

$action = GETPOST('action', 'aZ09');

// ---- Actions ----

if ($action == 'update') {
	dolibarr_set_const($db, 'BULKBREAKDOWN_AUTO_PROCESS', GETPOST('BULKBREAKDOWN_AUTO_PROCESS', 'alpha') ? '1' : '0', 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'BULKBREAKDOWN_DEBUG_MODE', GETPOST('BULKBREAKDOWN_DEBUG_MODE', 'alpha') ? '1' : '0', 'chaine', 0, '', $conf->entity);

	setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
}

// ---- Display ----

llxHeader('', $langs->trans('BulkbreakdownSetup'));

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans('BackToModuleList').'</a>';
print load_fiche_titre($langs->trans('BulkbreakdownSetup'), $linkback, 'title_setup');

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

print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="update">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td>'.$langs->trans('Parameter').'</td><td>'.$langs->trans('Value').'</td></tr>';

// Auto-process toggle
print '<tr class="oddeven"><td>';
print $langs->trans('AutoProcessDefault');
print '<br><span class="opacitymedium">'.$langs->trans('AutoProcessDefaultDesc').'</span>';
print '</td><td>';
$chk_auto = getDolGlobalString('BULKBREAKDOWN_AUTO_PROCESS') ? ' checked' : '';
print '<input type="checkbox" name="BULKBREAKDOWN_AUTO_PROCESS" value="1"'.$chk_auto.'>';
print '</td></tr>';

// Debug mode
print '<tr class="oddeven"><td>';
print $langs->trans('DebugMode');
print '<br><span class="opacitymedium">'.$langs->trans('DebugModeDesc').'</span>';
print '</td><td>';
$chk_debug = getDolGlobalString('BULKBREAKDOWN_DEBUG_MODE') ? ' checked' : '';
print '<input type="checkbox" name="BULKBREAKDOWN_DEBUG_MODE" value="1"'.$chk_debug.'>';
print '</td></tr>';

print '</table>';

print '<br>';
print '<input type="submit" class="button" value="'.$langs->trans('Save').'">';
print '</form>';

llxFooter();
$db->close();
