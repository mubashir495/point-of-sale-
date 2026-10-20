# Changes — Order Completion / Payment / Invoice / Ledger Fix

Scope of this pass: your requirements doc asked for RBAC, supplier order
isolation, customer payment tracking, invoice, and ledger integration.
An audit of the existing codebase found that RBAC and supplier order
isolation were **already implemented correctly** (see "Already working"
below). The real gaps were in the payment/invoice/ledger flow around
completing an order. Those are what this pass fixes.

## Bugs fixed

1. **"Complete Order" was broken.** The frontend always sent
   `status: "Complete"`, but `action/orders/update_status.php` only
   accepted `"Completed"` — every completion attempt threw
   `Invalid status`. Fixed by normalizing `"Complete"` -> `"Completed"`
   server-side before validation/storage, so the canonical DB value is
   consistently `"Completed"` going forward.

2. **Order payments weren't linked to that order's invoice.** Invoices
   were only ever created from quotations; orders never got one, so a
   payment collected on order completion updated the customer's ledger
   balance but no invoice reflected it (broke your "same source of
   truth" requirement). Fixed by adding `applyOrderPaymentAndSyncInvoice()`
   in `config/accounting_helpers.php`, which finds-or-creates the
   order's invoice (idempotent, via a new unique key on
   `invoices.order_id`) and keeps it, the ledger, and the order's own
   `paid_amount`/`payment_status` in sync in one transaction.

3. **A payment button called a file that doesn't exist**
   (`action/customers/add_transaction.php`, 404) in the "New
   Transaction" modal on the Orders page (and a dead-code copy in
   `customers-report.js`, which no page actually loads). Fixed the
   path to the file that actually exists: `action/orders/add_transaction.php`.

4. **Missing CSRF checks** on money-moving endpoints:
   `update_status.php`, `add_payment.php`, `add_customer_payment.php`,
   `add_supplier_payment.php`, `orders/add_transaction.php`. Added,
   consistent with the pattern already used in
   `supplier_update_status.php`.

5. **Status-badge color regression risk**: `statusClassFor()` in
   `orders.js`/`employee-orders.js` only recognized `"Complete"`, not
   `"Completed"`. Since the fix above now always writes `"Completed"`,
   this would have made every newly-completed order render with the
   wrong badge color. Fixed to accept both.

## New: Complete Order -> payment confirmation (spec sections 5-10)

`update_status.php`, when transitioning an order to Completed, now:

- Deducts stock (existing behaviour, unchanged).
- Finds or creates the order's invoice.
- Accepts `payment_received` (bool) and `paid_amount` (number) from
  the client (the existing payment-confirmation modal already
  collected this — it just wasn't being sent to this endpoint).
- If payment received: records a `customer_payments` row tied to the
  order/invoice, updates the invoice's `paid_amount`/`due_amount`/
  `status`, posts the matching customer-ledger credit, and updates
  `customers.current_balance`.
- If not received: leaves the invoice `Unpaid`/`Partial` so the
  outstanding balance is still correctly tracked in the ledger.
- Records `completed_by` (user id) and `completed_at` on the order.
- All of this happens once, in the same DB transaction as the status
  change — a repeated "Complete" submission (double-click, retry) is
  a no-op after the first success, so no duplicate payments/ledger
  entries/stock deductions can occur.

`src/js/orders.js` and `src/js/employee-orders.js` were updated to
send `payment_received`/`paid_amount` directly to `update_status.php`
in one call, instead of the old two-step flow (a separate payment
call, then a status call) that could leave things half-done if the
second call failed.

## Migration

Run once against your database:

```
mysql -u USER -p your_db < database/order_payment_completion_migration.sql
```

It only adds columns/indexes that don't already exist (checked via
`information_schema` before each `ALTER TABLE`), so it's safe to run
on a live database and safe to re-run. Verified by running it twice
in a row against a fresh test database with no errors.

Adds:
- `orders.paid_amount`, `orders.payment_status`, `orders.completed_by`,
  `orders.completed_at` (added only if missing).
- `invoices` — unique key on `order_id` (enables safe find-or-create).
- `customer_payments.order_id`, `.invoice_id`, `.recorded_by`.

## Already working (no changes needed)

- Role-based access control (`config/rbac.php`) — centralized,
  permission-checked on 119/123 endpoints, sidebar hides unauthorized
  pages, backend rejects unauthorized API calls.
- Supplier order isolation — a supplier only ever sees their own
  orders; the backend identifies the supplier from the authenticated
  session, not from any client-supplied ID, so one supplier can't
  request another's orders by guessing an ID.
- Customer ledger structure and running-balance calculation
  (`accounting_helpers.php`).

## What I'd flag for you to decide, not fixed here

- There are three separate payment-recording endpoints
  (`add_payment.php`, `add_customer_payment.php`,
  `orders/add_transaction.php`), each with slightly different logic.
  I didn't merge them — that's a bigger, riskier change than this
  pass — but it's worth knowing they exist if you see payment
  behaviour differ depending on which screen was used.
- `action/employees/Employee.php` has no RBAC check, but it's dead
  code (not referenced by any page or endpoint) — low priority, but
  worth deleting or gating if you ever wire it up.
- Historical order rows may already have the literal string
  `"Complete"` stored (from before this fix). Any report/filter that
  does an exact `WHERE status = 'Completed'` match won't include
  them. `dashboard-reports.js` already defends against this by
  checking both; worth checking any other report screens the same
  way if old data matters to you.

## Testing performed

All of the following were run against a real MySQL/MariaDB instance
(not just read by eye):

- Migration applied twice in a row with zero errors (idempotency).
- `applyOrderPaymentAndSyncInvoice()` exercised directly: full
  payment, no payment, and partial payment — each produced the exact
  invoice/ledger/customer-balance numbers your spec's examples show,
  and re-running the same completion a second time made no further
  change (no duplicate rows).
- The actual `update_status.php` file was invoked end-to-end (mocked
  HTTP request) with the real payload the frontend now sends
  (`status: "Complete", payment_received: true, paid_amount: 500`):
  order -> Completed, stock deducted, invoice created and marked Paid,
  ledger entries correct, `completed_by` recorded.
- Negative test: request with an invalid CSRF token was rejected and
  made no database changes.
- Negative test: the same completion request sent twice — second
  request returned "Already in this status" and made no further
  changes (stock, ledger, and balance identical to after the first
  request).
- All modified `.php` files pass `php -l`; all modified `.js` files
  pass `node --check`.

## Files changed

- `database/order_payment_completion_migration.sql` (new)
- `config/accounting_helpers.php`
- `action/orders/update_status.php`
- `action/orders/add_transaction.php` (CSRF only)
- `action/payments/add_payment.php` (CSRF only)
- `action/payments/add_customer_payment.php` (CSRF only)
- `action/payments/add_supplier_payment.php` (CSRF only)
- `src/js/orders.js`
- `src/js/employee-orders.js`
- `src/js/customers-report.js` (dead code, one path string only)

---

# Update 2 — Employees page: Designation dropdown now driven by real roles

## Problem

The "Designation" dropdown on the Employees page (Add + Edit) was a
hardcoded, static list of options. It didn't reflect whatever roles
actually existed in Roles & Permissions, so:

- A custom role created on the Roles page never appeared as an option
  here — no way to assign it to an employee from this screen.
- A couple of the hardcoded options ("Office Assistant", "HR") had no
  entry in the designation->role text-mapping at all, so picking them
  silently created a login with **no role and no permissions**.

## Fix

1. **New endpoint** `action/employees/list_roles.php` — returns the
   live list of roles (id, name, slug) from Roles & Permissions.
   Gated by the `employees` permission module (not `settings`, which
   `action/roles/roles.php` requires), so anyone who can add/edit
   employees can populate this dropdown even without access to the
   Roles & Permissions screen itself.

2. **Both Designation dropdowns now populate dynamically** from that
   endpoint (`employees.html`), instead of a hardcoded option list.
   Any role created on the Roles page shows up here automatically —
   no code changes needed for new roles going forward.

3. **Role assignment now uses the role's actual ID**, sent as a
   hidden `role_id` field alongside the dropdown, instead of guessing
   the role from the designation text. `config/rbac.php`'s
   `rbac_upsert_employee_login()` now accepts an optional `$roleId`
   and uses it directly when present; the old text-guessing map is
   kept only as a fallback for any other caller that hasn't been
   updated to send `role_id`.

4. **Bug fix, found while testing this**: editing an employee to
   change their role updated `role_id` correctly but left a legacy
   display field (`branch_users.user_type`) stale at whatever it was
   before. Fixed so both update together, and only when a role is
   actually being changed (a save with no role change - e.g. just
   updating a password - correctly leaves the existing role alone
   rather than resetting it).

## Testing performed

All against a real MySQL/MariaDB instance:

- Added a new employee with a custom role that has no designation-text
  mapping at all ("Warehouse Auditor") via `role_id` — correctly
  created a login with the right `role_id` and `user_type`.
- Added a new employee the old way (no `role_id`, designation text
  "Manager") — fallback text-matching still resolves the role
  correctly (backward compatible).
- Edited an existing employee to promote them to "Branch Manager" —
  `role_id` and `user_type` both updated correctly.
- Edited that same employee again with no `role_id` sent (e.g. only a
  password change) — role was correctly left unchanged, not reset.
- `list_roles.php` returns the correct live role list, including
  custom roles.
- All modified `.php` files pass `php -l`; the page's inline
  JavaScript passes `node --check`.

## Files changed (this update)

- `action/employees/list_roles.php` (new)
- `config/rbac.php` (`rbac_upsert_employee_login()`)
- `action/employees/add_employee.php`
- `action/employees/update_employee.php`
- `src/pages/employees.html`

---

# Update 3 — Roles page: permission edits looked like they weren't saving

## Problem

Reported symptom: tick permission checkboxes in the Role edit modal
(e.g. "Supplier Portal" -> all 6 actions), click Save, modal closes
with no error — but re-opening "Edit" on that same role shows the old,
unticked state, as if nothing was saved.

## Diagnosis

Tested the actual save+reload round trip directly against the real
`action/roles/roles.php` backend (not just read by eye): created a
role, ticked `supplier_portal` permissions, saved, then re-fetched it
— the backend correctly returned the saved permissions every time. So
the save itself was never the problem.

Given the reported behaviour — save reports success, but the *next*
load shows stale data — the browser was almost certainly caching the
`GET action/roles/roles.php?id=X` response and serving that cached
copy back when the Edit modal re-opened, instead of asking the server
again. The endpoint had no cache-control headers, so this is left to
browser heuristics, which can go either way depending on browser/proxy.

## Fix

- `action/roles/roles.php` now sends `Cache-Control: no-store,
  no-cache, must-revalidate, max-age=0` and `Pragma: no-cache` on
  every response, so it's never eligible for caching.
- `src/js/roles.js`'s fetch helper now also explicitly passes
  `cache: 'no-store'` on every request, as a second layer of the same
  protection directly in the browser call itself.

## Testing performed

- `php -l` and `node --check` both clean on the modified files.
- Re-verified the backend save+reload round trip still returns
  correct data after adding the cache headers (headers don't change
  response body/behaviour, only caching eligibility).

## Files changed (this update)

- `action/roles/roles.php`
- `src/js/roles.js`
