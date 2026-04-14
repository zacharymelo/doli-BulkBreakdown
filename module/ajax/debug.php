<?php
/* Copyright (C) 2026 Zachary Melo */

/**
 * \file    ajax/debug.php
 * \ingroup bulkbreakdown
 * \brief   Debug diagnostics for bulkbreakdown module.
 *          Gated by admin permission + BULKBREAKDOWN_DEBUG_MODE setting.
 *
 * Modes (via ?mode=):
 *   overview    — Module config, DB tables, element properties (default)
 *   object      — Deep inspect a breakdown rule (?mode=object&id=2)
 *   links       — All element_element rows involving MOs linked to receptions
 *   settings    — All BULKBREAKDOWN_* constants
 *   classes     — Class loading + method checks
 *   sql         — Read-only diagnostic query (?mode=sql&q=SELECT...)
 *   hooks       — Hook contexts and actions class methods
 *   all         — Run every diagnostic at once
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
	http_response_code(500);
	exit;
}

if (!$user->admin) {
	http_response_code(403);
	print 'Admin only';
	exit;
}
if (!getDolGlobalInt('BULKBREAKDOWN_DEBUG_MODE')) {
	http_response_code(403);
	print 'Debug mode not enabled. Go to Bulk Breakdown > Setup and enable Debug Mode.';
	exit;
}

header('Content-Type: text/plain; charset=utf-8');

$mode = GETPOST('mode', 'alpha') ?: 'overview';
$run_all = ($mode === 'all');

$MODULE_NAME  = 'bulkbreakdown';
$MODULE_UPPER = 'BULKBREAKDOWN';

print "=== BULKBREAKDOWN DEBUG DIAGNOSTICS ===\n";
print "Timestamp: ".date('Y-m-d H:i:s T')."\n";
print "Dolibarr: ".(defined('DOL_VERSION') ? DOL_VERSION : 'unknown')."\n";
print "DB prefix: ".MAIN_DB_PREFIX."\n";
print "Mode: $mode\n";
print "Usage: ?mode=overview|object|links|settings|classes|sql|hooks|all\n";
print "       ?mode=object&id=2  (breakdown rule ID)\n";
print "       ?mode=sql&q=SELECT+rowid,fk_product+FROM+llx_bulkbreakdown_rule+LIMIT+5\n";
print str_repeat('=', 60)."\n\n";


// =====================================================================
// OVERVIEW
// =====================================================================
if ($mode === 'overview' || $run_all) {
	print "--- MODULE STATUS ---\n";
	print "isModEnabled('bulkbreakdown'): ".(isModEnabled('bulkbreakdown') ? 'YES' : 'NO')."\n";
	print "BULKBREAKDOWN_AUTO_PROCESS: ".(getDolGlobalString('BULKBREAKDOWN_AUTO_PROCESS') ?: '0 (manual)')."\n";
	print "BULKBREAKDOWN_DEBUG_MODE: ".(getDolGlobalString('BULKBREAKDOWN_DEBUG_MODE') ?: '0')."\n";

	// Dependencies
	print "\n--- DEPENDENCIES ---\n";
	foreach (array('product', 'stock', 'bom', 'mrp', 'reception') as $mod) {
		print "  isModEnabled('$mod'): ".(isModEnabled($mod) ? 'YES' : 'NO')."\n";
	}

	// File paths
	print "\n--- FILE PATHS ---\n";
	print "  DOL_DOCUMENT_ROOT: ".DOL_DOCUMENT_ROOT."\n";
	if (defined('DOL_DOCUMENT_ROOT_ALT')) {
		print "  DOL_DOCUMENT_ROOT_ALT: ".DOL_DOCUMENT_ROOT_ALT."\n";
	}
	$tab_path = dol_buildpath('/bulkbreakdown/breakdown_tab.php', 0);
	print "  breakdown_tab.php resolved: ".$tab_path."\n";
	print "  breakdown_tab.php exists: ".(file_exists($tab_path) ? 'YES' : 'NO')."\n";
	$process_path = dol_buildpath('/bulkbreakdown/process_breakdown.php', 0);
	print "  process_breakdown.php resolved: ".$process_path."\n";
	print "  process_breakdown.php exists: ".(file_exists($process_path) ? 'YES' : 'NO')."\n";
	print "  debug.php __FILE__: ".__FILE__."\n";

	// DB tables
	print "\n--- DATABASE TABLES ---\n";
	foreach (array('bulkbreakdown_rule', 'bulkbreakdown_rule_extrafields') as $tbl) {
		$sql = "SELECT COUNT(*) as cnt FROM ".MAIN_DB_PREFIX.$tbl;
		$resql = $db->query($sql);
		if ($resql) {
			$obj = $db->fetch_object($resql);
			print "  llx_$tbl: ".$obj->cnt." rows\n";
		} else {
			print "  llx_$tbl: TABLE MISSING OR ERROR\n";
		}
	}

	// All rules summary
	print "\n--- BREAKDOWN RULES ---\n";
	$sql = "SELECT br.rowid, br.fk_product, br.fk_bom, br.auto_process, br.active,";
	$sql .= " p.ref as product_ref, b.ref as bom_ref, b.status as bom_status";
	$sql .= " FROM ".MAIN_DB_PREFIX."bulkbreakdown_rule br";
	$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."product p ON p.rowid = br.fk_product";
	$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."bom_bom b ON b.rowid = br.fk_bom";
	$sql .= " WHERE br.entity IN (".getEntity('bulkbreakdown').")";
	$sql .= " ORDER BY br.rowid";
	$resql = $db->query($sql);
	if ($resql) {
		$cnt = 0;
		while ($row = $db->fetch_object($resql)) {
			$cnt++;
			$ap = $row->auto_process == 1 ? 'auto' : ($row->auto_process == 0 ? 'manual' : 'global');
			$bomOk = $row->bom_status == 1 ? 'OK' : 'INACTIVE';
			print "  [$row->rowid] product=$row->product_ref bom=$row->bom_ref($bomOk) auto=$ap active=$row->active\n";
		}
		if ($cnt == 0) {
			print "  (none configured)\n";
		}
	}
	print "\n";
}


// =====================================================================
// OBJECT
// =====================================================================
if ($mode === 'object' || $run_all) {
	$oid = GETPOSTINT('id');
	if ($oid <= 0 && !$run_all) {
		print "--- OBJECT DIAGNOSIS ---\nUsage: ?mode=object&id=2  (breakdown rule rowid)\n\n";
	} elseif ($oid > 0) {
		print "--- OBJECT DIAGNOSIS: breakdownrule id=$oid ---\n";
		dol_include_once('/bulkbreakdown/class/breakdownrule.class.php');

		$rule = new BreakdownRule($db);
		$fetch_result = $rule->fetch($oid);
		print "  fetch(): $fetch_result\n";

		if ($fetch_result > 0) {
			print "  fk_product: $rule->fk_product\n";
			print "  fk_bom: $rule->fk_bom\n";
			print "  auto_process: $rule->auto_process\n";
			print "  active: $rule->active\n";
			print "  date_creation: $rule->date_creation\n";

			// Check product
			require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
			$product = new Product($db);
			if ($product->fetch($rule->fk_product) > 0) {
				print "\n  Product: $product->ref - $product->label\n";
			} else {
				print "\n  Product: FETCH FAILED (id=$rule->fk_product)\n";
			}

			// Check BOM
			require_once DOL_DOCUMENT_ROOT.'/bom/class/bom.class.php';
			$bom = new BOM($db);
			if ($bom->fetch($rule->fk_bom) > 0) {
				$bom->fetchLines();
				$type = $bom->bomtype == 1 ? 'Disassembly' : 'Manufacturing';
				$status = $bom->status == 1 ? 'Validated' : 'INACTIVE('.$bom->status.')';
				print "  BOM: $bom->ref type=$type status=$status\n";
				print "  BOM lines (".count($bom->lines)."):\n";
				foreach ($bom->lines as $line) {
					$lp = new Product($db);
					$lp->fetch($line->fk_product);
					print "    product=$lp->ref qty=$line->qty\n";
				}
			} else {
				print "  BOM: FETCH FAILED (id=$rule->fk_bom)\n";
			}
		}
		print "\n";
	}
}


// =====================================================================
// LINKS
// =====================================================================
if ($mode === 'links' || $run_all) {
	print "--- MO ↔ RECEPTION LINKS (element_element) ---\n";
	$sql = "SELECT el.rowid, el.fk_source, el.sourcetype, el.fk_target, el.targettype,";
	$sql .= " mo.ref as mo_ref, mo.status as mo_status";
	$sql .= " FROM ".MAIN_DB_PREFIX."element_element el";
	$sql .= " INNER JOIN ".MAIN_DB_PREFIX."mrp_mo mo ON mo.rowid = el.fk_source";
	$sql .= " WHERE el.sourcetype = 'mo' AND el.targettype = 'reception'";
	$sql .= " ORDER BY el.rowid DESC LIMIT 50";
	$resql = $db->query($sql);
	if ($resql) {
		$cnt = 0;
		while ($row = $db->fetch_object($resql)) {
			$cnt++;
			print "  [$row->rowid] MO $row->mo_ref (status=$row->mo_status) → reception id=$row->fk_target\n";
		}
		print "  Total: $cnt rows (max 50)\n";
	}
	print "\n";
}


// =====================================================================
// SETTINGS
// =====================================================================
if ($mode === 'settings' || $run_all) {
	print "--- BULKBREAKDOWN SETTINGS ---\n";
	$sql = "SELECT name, value FROM ".MAIN_DB_PREFIX."const WHERE name LIKE '".$MODULE_UPPER."%' AND entity IN (0, ".((int) $conf->entity).") ORDER BY name";
	$resql = $db->query($sql);
	if ($resql) {
		while ($row = $db->fetch_object($resql)) {
			print "  $row->name = $row->value\n";
		}
	}
	print "\n";
}


// =====================================================================
// CLASSES
// =====================================================================
if ($mode === 'classes' || $run_all) {
	print "--- CLASS LOADING & METHODS ---\n";
	$classes = array(
		'BreakdownRule' => '/bulkbreakdown/class/breakdownrule.class.php',
	);
	foreach ($classes as $classname => $filepath) {
		print "  $classname:\n";
		$inc = @dol_include_once($filepath);
		print "    dol_include_once: ".($inc ? 'OK' : 'FAILED')."\n";
		print "    class_exists: ".(class_exists($classname) ? 'YES' : 'NO')."\n";
		if (class_exists($classname)) {
			$obj = new $classname($db);
			print "    \$element: ".$obj->element."\n";
			print "    \$table_element: ".$obj->table_element."\n";
			$required = array('create', 'fetch', 'fetchByProduct', 'update', 'delete');
			$missing = array();
			foreach ($required as $m) {
				if (!method_exists($obj, $m)) {
					$missing[] = $m;
				}
			}
			print "    Required methods: ".(empty($missing) ? 'ALL PRESENT' : 'MISSING: '.implode(', ', $missing))."\n";
		}
		print "\n";
	}
}


// =====================================================================
// SQL
// =====================================================================
if ($mode === 'sql') {
	$q = GETPOST('q', 'restricthtml');
	print "--- SQL QUERY ---\n";
	if (empty($q)) {
		print "Usage: ?mode=sql&q=SELECT+rowid,fk_product+FROM+llx_bulkbreakdown_rule+LIMIT+5\n\n";
		print "Useful queries:\n";
		print "  ?mode=sql&q=SELECT rowid,fk_product,fk_bom,auto_process,active FROM llx_bulkbreakdown_rule\n";
		print "  ?mode=sql&q=SELECT rowid,ref,fk_product,bomtype,status FROM llx_bom_bom WHERE bomtype=1 LIMIT 10\n";
		print "  ?mode=sql&q=SELECT rowid,ref,status,fk_product,fk_bom FROM llx_mrp_mo ORDER BY rowid DESC LIMIT 10\n";
		print "  ?mode=sql&q=SELECT * FROM llx_element_element WHERE sourcetype='mo' AND targettype='reception' ORDER BY rowid DESC LIMIT 20\n";
	} else {
		$q_trimmed = trim($q);
		if (stripos($q_trimmed, 'SELECT') !== 0) {
			print "ERROR: Only SELECT queries allowed.\n";
		} else {
			$blocked = array('INSERT', 'UPDATE', 'DELETE', 'DROP', 'ALTER', 'TRUNCATE', 'CREATE', 'GRANT');
			$safe = true;
			foreach ($blocked as $kw) {
				if (stripos($q_trimmed, $kw) !== false && stripos($q_trimmed, $kw) !== stripos($q_trimmed, 'SELECT')) {
					$safe = false;
					break;
				}
			}
			if (!$safe) {
				print "ERROR: Query contains blocked keywords.\n";
			} else {
				if (stripos($q_trimmed, 'LIMIT') === false) {
					$q_trimmed .= ' LIMIT 50';
				}
				print "Query: $q_trimmed\n\n";
				$resql = $db->query($q_trimmed);
				if ($resql) {
					$first = true;
					$row_num = 0;
					while ($obj = $db->fetch_array($resql)) {
						if ($first) {
							print implode("\t", array_keys($obj))."\n".str_repeat('-', 80)."\n";
							$first = false;
						}
						$row_num++;
						$vals = array();
						foreach ($obj as $v) {
							$vals[] = ($v === null) ? 'NULL' : (strlen($v) > 40 ? substr($v, 0, 40).'...' : $v);
						}
						print implode("\t", $vals)."\n";
					}
					print "\n$row_num rows.\n";
				} else {
					print "SQL ERROR: ".$db->lasterror()."\n";
				}
			}
		}
	}
	print "\n";
}


// =====================================================================
// HOOKS
// =====================================================================
if ($mode === 'hooks' || $run_all) {
	print "--- HOOK REGISTRATION ---\n";
	if (isset($conf->modules_parts['hooks'])) {
		foreach ($conf->modules_parts['hooks'] as $context => $modules) {
			if (is_array($modules)) {
				foreach ($modules as $mod) {
					if (stripos($mod, 'bulkbreakdown') !== false) {
						print "  context='$context' module='$mod'\n";
					}
				}
			}
		}
	}

	$actions_file = dol_buildpath('/bulkbreakdown/class/actions_bulkbreakdown.class.php', 0);
	print "\n  Actions class:\n";
	print "    File: ".(file_exists($actions_file) ? 'EXISTS' : 'MISSING')."\n";
	if (file_exists($actions_file)) {
		include_once $actions_file;
		$ac = 'ActionsBulkbreakdown';
		print "    Class: ".(class_exists($ac) ? 'YES' : 'NO')."\n";
		if (class_exists($ac)) {
			foreach (array('addMoreActionsButtons', 'formObjectOptions', 'doActions') as $m) {
				print "    $m(): ".(method_exists($ac, $m) ? 'defined' : 'not defined')."\n";
			}
		}
	}
	print "\n";
}

print "=== END DEBUG ===\n";
