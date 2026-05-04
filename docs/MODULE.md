# Bulk Breakdown — Module Architecture & Current State

> Reference doc for resuming work without re-exploring the codebase.
> Last updated: v1.2.6

## Purpose

Convert bulk purchased products into individual inventory units using
Dolibarr's existing BOM/MRP system. You buy a 600ft spool of hose for $600
from a vendor, then break it down into 600x 1ft pieces at $1 each in
inventory — without faking the PO data.

## Repo & Deploy

- **Local repo**: `/Users/zacharymelo/bulkbreakdown/`
- **GitHub**: https://github.com/zacharymelo/doli-BulkBreakdown
- **Module folder in Dolibarr**: `htdocs/custom/bulkbreakdown/`
- **Module ID**: 510500
- **Module class**: `modBulkbreakdown`
- **Module name (lowercase)**: `bulkbreakdown`
- **Module name (UPPERCASE constants)**: `BULKBREAKDOWN_*`

### Dolibarr environments

- **Production** (`digitalproperties.works/manage`): v22.0.4, prefix `llxiw_`
- **Staging** (`digitalproperties.works/staging`): v23.0.0, prefix `llx_`

## File Structure

```
bulkbreakdown/
├── module/
│   ├── core/modules/modBulkbreakdown.class.php   # descriptor (auto-enables deps)
│   ├── class/
│   │   ├── breakdownrule.class.php               # CRUD for rule (CommonObject)
│   │   └── actions_bulkbreakdown.class.php       # reception card hook
│   ├── lib/bulkbreakdown.lib.php                 # core processing logic
│   ├── breakdown_tab.php                         # product card "Breakdown" tab
│   ├── process_breakdown.php                     # per-line processing UI
│   ├── admin/setup.php                           # global toggle + debug toggle
│   ├── ajax/debug.php                            # diagnostics endpoint
│   ├── langs/en_US/bulkbreakdown.lang
│   └── sql/
│       ├── llx_bulkbreakdown_rule.sql
│       ├── llx_bulkbreakdown_rule.key.sql
│       └── llx_bulkbreakdown_rule_extrafields*.sql
├── docs/
│   ├── MODULE.md                                 # this file
│   └── REPLENISHMENT-CHALLENGE.md                # known UX gap & approaches
├── README.md
├── CHANGELOG.md
└── .pre-commit-config.yaml
```

## Database

### `llx_bulkbreakdown_rule`
| Column           | Type             | Notes                                |
|------------------|------------------|--------------------------------------|
| rowid            | INT PK auto      |                                      |
| entity           | INT NOT NULL     | multi-entity                         |
| fk_product       | INT NOT NULL     | the bulk product (the spool)         |
| fk_bom           | INT NOT NULL     | linked Disassembly BOM               |
| auto_process     | TINYINT          | -1 use global, 0 manual, 1 auto      |
| active           | TINYINT          |                                      |
| note             | TEXT             |                                      |
| date_creation    | DATETIME         |                                      |
| tms              | TIMESTAMP        |                                      |
| fk_user_creat    | INT              |                                      |
| fk_user_modif    | INT              |                                      |

UNIQUE INDEX on `(fk_product, entity)` — one rule per product.

## Core Concept

A breakdown links **3 objects**:
1. **Bulk product** (vendor SKU; e.g., "600ft Hose Spool")
2. **Unit product** (sellable; e.g., "1ft Hose Piece")
3. **Disassembly BOM** (`bomtype=1`, where `fk_product` = bulk, lines = output)

Why disassembly BOM (`bomtype=1`)? Dolibarr's `Mo::createProduction()`
already routes lines correctly: main product → `toconsume`,
BOM lines → `toproduce`. We piggyback on that instead of inventing a
parallel system.

## Processing Flow

1. **Setup** (one-time per bulk product)
   - User creates a Disassembly BOM in Dolibarr (`/bom/bom_card.php`,
     bomtype=1). Main product = bulk. Lines = unit products with qty.
   - On the bulk product card → "Breakdown" tab → select the BOM.
2. **Receive goods** (normal Dolibarr flow)
   - Supplier order → reception → validate → close.
3. **Process button on reception card**
   - Stays grayed out (`butActionRefused`) with tooltip until reception
     status >= 2 (closed). Also grays out when all eligible lines are
     already processed.
4. **Processing page** (`process_breakdown.php?reception_id=X`)
   - Lists every reception line whose product has an active rule.
   - Per-line checkbox; pre-checked state follows the auto_process cascade
     (per-rule override → global default toggle).
   - Submits selected lines.
5. **For each selected line** (`processBreakdownLine` in lib):
   - Fetch + validate the BOM.
   - Create + validate an MO (`Mo::create()` then `Mo::validate()`).
   - Run stock movements: `MouvementStock::livraison()` to consume
     bulk, `MouvementStock::reception()` to produce units.
   - Use the warehouse from the reception line itself
     (`receptiondet_batch.fk_entrepot`) for both directions.
   - Compute total purchase price from
     `commande_fournisseurdet.subprice` joined via
     `receptiondet_batch.fk_elementdet`.
   - Update `product.cost_price` directly via raw SQL
     (`double(24,8)`, full precision; allows $0).
   - Insert a new vendor price entry on each output product
     (`ProductFournisseur::update_buyprice`, ref `BREAKDOWN-{MO ref}`,
     supplier = reception->socid). One per breakdown for history.
   - Close the MO (set status to PRODUCED).
   - Link MO ↔ reception via `element_element` (sourcetype=mo,
     targettype=reception).

## Key Settings (constants)

| Constant                       | Used for                                                |
|--------------------------------|---------------------------------------------------------|
| `BULKBREAKDOWN_AUTO_PROCESS`   | Default for whether checkboxes are pre-checked         |
| `BULKBREAKDOWN_DEBUG_MODE`     | Gates `/bulkbreakdown/ajax/debug.php` (admin only)     |

## Permissions

- `bulkbreakdown.breakdown.read` (510501)
- `bulkbreakdown.breakdown.write` (510502)
- `bulkbreakdown.breakdown.process` (510503)

## Auto-enable Dependencies

`init()` calls `activateModule()` for each of: Product, Stock, BOM, MRP,
Reception. So enabling Bulk Breakdown brings the rest along automatically.

## Coding Standards

User has pre-commit hooks installed via `~/.dolibarr-dev/`:
- Tabs (width 4), Unix LF, UTF-8 no BOM
- No closing `?>` tag
- `elseif` not `else if`
- Space after cast: `(int) $var`
- PEAR docblocks
- Pre-commit must pass clean before any commit

Run from any module repo: `pre-commit run --all-files`

## Release Workflow

User has a `/release` skill at `~/.claude/skills/release/SKILL.md`.
Bumps version in module descriptor, commits with conventional
prefix (fix/feat), builds zip with module dir at root, names it
`bulkbreakdown-{version}.zip`. Currently at **v1.2.6**.

## Recent Issues Resolved

- v1.0.1: rendering — `selectWarehouses()` returns string, must `print`
- v1.0.2: pulled warehouse from reception line, removed module defaults
- v1.1.0: auto-process toggle (global + per-rule override) + debug tool
- v1.1.1: cost propagation — joined supplier order line via `fk_elementdet`
  instead of bogus `commande_fournisseur_dispatch.fk_reception`
- v1.1.2: gray button until reception closed
- v1.2.0–1.2.1: vendor price entries on output products
- v1.2.2: button grays out once all lines processed (UI guard, no server lock)
- v1.2.6: write `cost_price` directly with full precision so BOM
  totals reflect real cost regardless of `MAIN_MAX_DECIMALS_UNIT`

## Debug Endpoint

Once `BULKBREAKDOWN_DEBUG_MODE` is on:

```
/bulkbreakdown/ajax/debug.php?mode=overview|object|links|settings|classes|sql|hooks|all
```

`?mode=object&id=N` for a specific rule.
`?mode=sql&q=SELECT+...` runs SELECT queries safely.
