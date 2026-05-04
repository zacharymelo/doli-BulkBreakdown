# UX Challenge: Replenishment for Breakdown-Derived Products

> Open problem to solve in a follow-up session.

## The problem

Bulk Breakdown produces inventory units (e.g., individual screws) that don't
have a real vendor SKU/price relationship — we don't *buy* individual screws,
we buy boxes of 100 and break them down. So when stock of the unit product
gets low, Dolibarr's standard Replenishment workflow can't help us:

- `/product/stock/replenish.php` looks at products with low stock and tries
  to find a `product_fournisseur_price` entry to generate a supplier order.
- Our module *does* create vendor price entries on the output product
  (ref `BREAKDOWN-{MO ref}`), but those reference per-unit prices and would
  create an order line for "1 screw at $0.06" instead of "1 box at $6.00".
- The supplier won't accept that order; it's not how we buy from them.

Before this module existed, the user used to manually fudge POs — typing
"600 pieces of hose at $1 each" when the vendor invoice actually said
"1 spool at $600." That made replenishment work but misrepresented the
purchase. The whole point of the module is to enter PO data faithfully.

We need a way for replenishment to follow the breakdown chain in reverse:
"low on screws → reorder N boxes of screws."

## What the user said

> "When a user uses the bulk breakdown, because it is a derivative of the
> product we buy from our vendor — there is no mechanism to use the default
> Dolibarr Replenishment workflow… we can't add the screws to the
> replenishment order as there is no vendor sku/price relationship.
> Is there some way we can make an FK relationship to the box quantity so
> that the replenishment functions can work like they normally would."

## What we already have

The breakdown rule already records the parent → child relationship:
`llx_bulkbreakdown_rule(fk_product → fk_bom)`, where the BOM's `fk_product`
is the bulk and BOM lines are the units. So the FK chain *exists*; we just
need the replenishment workflow to follow it.

## Approaches to evaluate

### A. Hook into replenish.php
Inject a hook on the replenishment page (or its `addReplenishmentLine`-style
flow) that, when a unit product appears with low stock, swaps it for the
parent bulk product. Requires:
- A reverse lookup: "given unit product X, which bulk products have a BOM
  that produces X?"
- Quantity math: required units ÷ units-per-box = boxes to order.
- Replace the line in the replenishment cart, or add a sibling line.

### B. Replenishment hint table / display-only enrichment
Less invasive: don't change the replenishment behavior; add a "Reorder via
breakdown" column or banner on the replenish page that *tells* the user
"low on screws — reorder Box of 100 Screws (need 5 boxes)."
Still requires the user to navigate to the bulk product's vendor and
order it manually. Closer to the current state but with guidance.

### C. Set parent_product via Dolibarr's `fk_parent` column
`llx_product` has a `fk_parent` field (marked "Not used" in the schema but
still exists). We could populate it during breakdown rule creation. The
problem: nothing in core actually consumes `fk_parent` for replenishment,
so this would be cosmetic unless we also do (A).

### D. Virtual SKUs that represent "1 box / 100 units"
Create a phantom supplier price on the unit product priced as the box-cost
divided by units, but with a flag/note that says "buy in increments of 100."
This is effectively what we do now. It doesn't solve the fundamental issue
that ordering "100 individual screws" isn't a valid PO line.

### E. Replace the unit product on the supplier order
Hook into supplier order creation/validation: if a draft order line
references a breakdown-derived product, swap it for the bulk parent and
adjust qty. The user adds "1ft hose" to a supplier proposal, but when it
becomes an order, it gets converted to "1 spool of 600ft." Risk: feels
magical and confusing to whoever is reviewing the PO.

### F. Custom replenishment list page in our module
Build our own replenishment list that already understands breakdown
relationships. Doesn't require modifying core but means users have to
remember to use ours instead of the standard one. Discoverable via menu
under MRP or Stock.

## Recommended starting point

Option **A** is the right long-term answer; option **B** is the right MVP
to ship first.

Suggested phased approach:
1. **Phase 1 (B)**: On the standard `/product/stock/replenish.php` page,
   inject a hook that detects breakdown-derived products and shows a
   "Reorder bulk parent: Box of 100 Screws (5 boxes needed)" link
   beside each affected row. Single round-trip enhancement, no behavior
   change.
2. **Phase 2 (A)**: Add a checkbox/toggle on the replenish page:
   "Replenish via breakdown parents." When set, the page silently
   substitutes bulk parents (with rounded-up quantities) before
   generating supplier orders. Make it opt-in so existing users aren't
   surprised.

## Things to figure out before implementing

- **Hook contexts available on `/product/stock/replenish.php`** — does
  it support `formObjectOptions`, `addMoreActionsButtons`, line-level
  hooks, or any equivalent? Need to look at the file.
- **Quantity math** — for a unit product with a `desired_stock` or
  `stock_alert` threshold, how many boxes do we need to order? Use the
  BOM's output-line qty: `ceil((desired_stock - current_stock) / bom_line_qty)`.
- **Multiple BOMs** — what if a unit product is produced by more than one
  breakdown BOM (e.g., screws come from 100-pack OR 500-pack)? Need to
  pick one (cheapest? most recent?) or expose the choice to the user.
- **Existing supplier prices on bulk** — only need vendor SKU/price on
  the bulk parent (which is normal vendor purchasing). The unit product's
  supplier prices are mostly informational/historic.
- **Don't break existing behavior** — users who *do* have a real vendor
  SKU for the unit product (they buy individually too) shouldn't be
  forced into the breakdown flow.

## Where to look in Dolibarr core

- `/htdocs/product/stock/replenish.php` — the main page
- `/htdocs/product/stock/replenishorders.php` — generates supplier orders
- `/htdocs/fourn/class/fournisseur.product.class.php` —
  `find_min_price_product_fournisseur()` is what replenishment uses to
  find a supplier
- `/htdocs/fourn/class/fournisseur.commande.class.php::create()` and
  `::addline()` — for swapping lines
