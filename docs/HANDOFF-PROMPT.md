# New-Conversation Handoff Prompt

Copy/paste the block below into a fresh Claude conversation. It points
Claude at the two context docs so it doesn't have to re-explore the codebase.

---

I want to address a UX challenge in my Dolibarr Bulk Breakdown module.
Before exploring anything, please read these two files in order — they
contain everything you need to know:

1. `/Users/zacharymelo/bulkbreakdown/docs/MODULE.md` — module
   architecture, current state (v1.2.6), file layout, processing flow,
   coding standards, release process. Don't re-explore the codebase
   until you've read this.
2. `/Users/zacharymelo/bulkbreakdown/docs/REPLENISHMENT-CHALLENGE.md` —
   the specific problem we're trying to solve, options A–F, and the
   recommended phased approach (B → A).

The challenge in one sentence: products produced by a breakdown
(e.g., individual screws from a box of 100) don't have a real
vendor SKU/price relationship, so Dolibarr's standard Replenishment
workflow can't reorder them. We want replenishment to follow the
breakdown chain in reverse — "low on screws → reorder N boxes."

After you've read both docs, do this:

1. Read `/Users/zacharymelo/Desktop/dolibarr-develop/htdocs/product/stock/replenish.php`
   to understand what hooks are available on the replenishment page
   and how lines are rendered/submitted.
2. Read `/Users/zacharymelo/Desktop/dolibarr-develop/htdocs/product/stock/replenishorders.php`
   to see how supplier orders get generated from the replenishment cart.
3. Report back with:
   - Which hook contexts are usable on those pages
   - Whether option B (display-only enrichment) is feasible without
     modifying core
   - Whether option A (line substitution) needs core modification
     or can be done via hook
   - A concrete proposal for Phase 1 (option B): exactly which file
     to add, which hook to register, what the user sees on screen
   - Any blockers I'm not anticipating

Then wait for me to confirm before writing any code.

Notes:
- The repo is at `/Users/zacharymelo/bulkbreakdown/`. Always commit
  here, not `/tmp`.
- Pre-commit hooks are installed; they must pass clean.
- Use the `/release` skill when bumping versions.
- Don't make UI changes that haven't been requested. If a fix needs a
  visual change, describe it and wait for approval first.
- Production runs Dolibarr 22, staging runs 23. Test on production
  semantics first.
