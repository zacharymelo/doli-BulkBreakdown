# Changelog

## [1.0.0] - 2026-04-14

### Added
- Initial release
- Breakdown Rules: link bulk products to Disassembly BOMs via Product card tab
- Process Breakdowns page: per-line selection from Reception card with warehouse controls
- Hook on Reception card injecting "Process Breakdowns" button for eligible lines
- Full MRP integration: creates Manufacturing Orders with stock movements and cost propagation
- Double-processing prevention via element_element linking
- Batch product support with auto-generated lot numbers from MO ref
- Admin setup page for default warehouse configuration
- Auto-activation of required modules (Product, Stock, BOM, MRP, Reception) on install
- 3 permissions: read rules, write rules, process breakdowns
