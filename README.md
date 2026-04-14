# Bulk Breakdown — Dolibarr Module

**Version 1.0.0** | [GitHub Repository](https://github.com/zacharymelo/doli-BulkBreakdown) | License: GPL-3.0

## Overview

Convert bulk purchased products into individual inventory units using Dolibarr's existing BOM/MRP system. Receive vendor goods as-is (e.g., 1x 600ft spool of hose at $600) and break them down into sellable units (600x 1ft pieces at $1 each) in one or two clicks.

## Features

- **Breakdown Rules** on the Product card — link any bulk product to a Disassembly BOM
- **"Process Breakdowns" button** on the Reception card for products with active rules
- **Per-line selection** — choose which received items to break down, skip the rest
- **Warehouse control** — set source/destination warehouses per line, per rule, or globally
- **Full MRP integration** — creates real Manufacturing Orders with proper stock movements and cost propagation
- **Double-processing prevention** — already-processed lines show linked MO references
- **Batch product support** — auto-generates batch/lot numbers for batch-tracked output products
- **Auto-enables dependencies** — automatically activates Product, Stock, BOM, MRP, and Reception modules on install

## Requirements

| Requirement | Details |
|---|---|
| Dolibarr | Version 16.0 or higher |
| PHP | Version 7.0 or higher |
| **Required modules** | Product, Stock, BOM, MRP, Reception (auto-enabled on install) |

## Installation

1. Download or clone this repository
2. Copy the `module/` directory contents into your Dolibarr `htdocs/bulkbreakdown/` directory
3. Go to **Home > Setup > Modules/Applications**
4. Search for "Bulk Breakdown" and enable the module
5. Required modules (Product, Stock, BOM, MRP, Reception) will be activated automatically

### Alternative: Deploy via ZIP

1. Build the zip: from the parent of `bulkbreakdown/`, run:
   ```
   zip -r bulkbreakdown/module_bulkbreakdown-1.0.0.zip bulkbreakdown/module/ -x "*.git*" "*.DS_Store"
   ```
2. In Dolibarr, go to **Home > Setup > Modules > Deploy external module**
3. Upload the zip file

## Configuration

1. Go to **Home > Setup > Modules > Bulk Breakdown** (click the gear icon)
2. Set the **Default Source Warehouse** (where bulk products are consumed from)
3. Set the **Default Destination Warehouse** (where individual units are produced into)

These defaults can be overridden per breakdown rule or per line during processing.

## Usage Guide

### 1. Create a Disassembly BOM

Before using this module, create a Disassembly BOM in the standard BOM module:

1. Go to **MRP/Manufacturing > Bill of Materials > New**
2. Set **Type** to "Disassemble"
3. Set **Product** to the bulk product (e.g., "600ft Hose Spool")
4. Set **Qty** to 1 (one unit of the bulk product)
5. Add a BOM line for the output product (e.g., "1ft Hose Piece", qty 600)
6. Validate the BOM

### 2. Define a Breakdown Rule

1. Navigate to the bulk product's card (e.g., "600ft Hose Spool")
2. Click the **Breakdown** tab
3. Select the Disassembly BOM from the dropdown
4. Optionally set warehouse overrides
5. Save

### 3. Process a Reception

1. Create a supplier order for the bulk product (e.g., 1x spool at $600)
2. Create and validate a reception when goods arrive
3. On the reception card, click the **"Process Breakdowns"** button
4. Review the listed lines — each shows the conversion summary (e.g., "1x Spool -> 600x Piece")
5. **Uncheck** any lines you want to keep as whole bulk items (e.g., whole spools)
6. Select warehouses if different from defaults
7. Click **"Process Selected"**
8. Stock is adjusted: bulk product consumed, individual units produced, MO created and closed

### Skipping Breakdowns

- **Uncheck a line** on the processing page to keep a whole spool in inventory
- **Don't click the button** at all to skip breakdown entirely — normal reception behavior
- Previously processed lines show "Already Processed" with a link to the Manufacturing Order

## How It Works

Each breakdown creates a Dolibarr Manufacturing Order (Disassembly type):

1. MO is created from the linked BOM
2. MO is validated (gets a proper MO reference number)
3. Stock movements are executed:
   - `MouvementStock::livraison()` — consumes the bulk product from source warehouse
   - `MouvementStock::reception()` — produces individual units into destination warehouse
4. MoLine records are created (consumed/produced) for full audit trail
5. MO status is set to "Produced" (closed)
6. MO is linked to the reception via `element_element`

Cost is propagated: the purchase price is divided by the output quantity and passed to the stock reception movement, updating the weighted average price (PMP) on the output product.

## License

GNU General Public License v3.0 — see [LICENSE](https://www.gnu.org/licenses/gpl-3.0.en.html)
