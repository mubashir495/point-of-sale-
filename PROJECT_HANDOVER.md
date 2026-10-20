# Marketing Booster — Project Handover

## Current state (2026-10-19)
Original ZIP extracted and inspected. Existing application is PHP/MySQL with vanilla JavaScript, HTML, Tailwind CSS and RBAC. Existing order endpoint `action/orders/add_order.php` accepts client-provided totals and is still unchanged. Existing customer payments endpoint `action/payments/add_customer_payment.php` records customer-level payments and is unchanged. Branch scoping exists in order API and RBAC. Historical database contents are not included/verified.

## Completed
- Added `config/printing_calculator.php`, a reusable server-side calculator with square-foot, fixed, per-piece and custom pricing, feet/inches conversion, item charges, discounts, order total, payments and status.
- Added `tests/printing_calculator_test.php`; all six assertions passed using PHP CLI. PHP lint passed for calculator.
- No destructive changes to existing database or application workflows.

## Not yet implemented
- Wire calculator into existing order API and JS order form, using authoritative database-configured rates.
- Configurable Panaflex category rates and database migrations.
- Transactional invoice-level advance and later payments with idempotency.
- Remove branch, second counter and delivery-boy UI/routes safely after dependency mapping; retain historic data.
- Marketing Booster branding, invoice print design, dashboard/report adaptations.
- Full integration, RBAC, UI, build and database migration tests.

## Files changed
New: `config/printing_calculator.php`, `tests/printing_calculator_test.php`, `PROJECT_HANDOVER.md`, `IMPLEMENTATION_SUMMARY.md`. Original source files remain unchanged.

## Commands
Run unit checks: `php tests/printing_calculator_test.php` and `php -l config/printing_calculator.php`.
Original setup: see `QUICK_START.md`, `config/database.php`, and database migration files. Requires configured MySQL database to run application end-to-end. No live database or web-server tests performed.

## Next exact task
Inspect `src/js/orders.js`, `src/pages/orders.html`, `action/orders/add_order.php`, `action/invoices/invoice_api.php`, and actual MySQL schema. Design non-destructive migration for printing rates, order-item dimensions and invoice-linked payment records, then integrate calculator into server-side order save in a transaction. Do not trust posted rate/totals. Confirm current customer ledger logic to avoid double postings.

## Transfer prompt
Read PROJECT_HANDOVER.md and the master prompt in `Pasted text(3).txt` first. Inspect actual source code and Git status, verify this handover against code, and resume the first incomplete task without rebuilding. Preserve original functionality, authentication, historical data and all master requirements. Run appropriate tests and builds, record exact results, and update PROJECT_HANDOVER.md after each meaningful stage. Prepare another full handover before context limits become restrictive. Do not claim unverified completion.

## Delivery feature removal (2026-10-19)
Dedicated delivery UI, schedule endpoints, and supplier delivery endpoints removed. See DELIVERY_REMOVAL.md. No DB changes; live integration tests not run.

## Single sales-counter UI cleanup (2026-10-19)
- Kept `src/pages/counter-sale.html` as the single dedicated counter-sale screen and its existing JS/API.
- Renamed duplicate `Sale 2 (Counter)` navigation labels to `Sale`.
- Renamed misleading `Sale 2` links pointing at `orders.html` to `Orders`.
- Retained `orders.html`, `sale2.html` (completed-order history), existing endpoints, and historical Counter order statuses; no database schema/data deletion.
- This is navigation consolidation, NOT a verified backend counter/branch consolidation. Live DB verification remains pending.

## Single-counter database migration (2026-10-19)
Added `database/single_counter_migration.sql`, a non-destructive, idempotent singleton configuration table (`marketing_booster_counter_config`, id=1). Existing SQL and order API indicate counter sales use `orders.status='Counter'`; no distinct Counter 1 / Counter 2 database entities were confirmed. The migration has NOT been run against a live MySQL database. Backend must still be integrated with the singleton configuration; migration alone does not enforce a single counter in all workflows. Do not delete historical orders or branches.
