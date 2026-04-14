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
} else {
	print '<div class="info">';
	print $langs->trans('AllDependenciesEnabled');
	print '</div>';
}

llxFooter();
$db->close();
