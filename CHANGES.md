# Changes Made — Order Completion / Payment / Invoice / Ledger Fix

## Context

Before changing anything, the existing codebase (132 PHP files) was audited
against `System Update Requirements.md`. Most of what was asked for already
existed and works correctly:

- Full RBAC (`config/rbac.php`) — 119/123 action endpoints already
  permission-checked, sidebar already role-driven, unauthorized URLs already
  denied server-side.
- Supplier order isolation already correct: a supplier's dashboard/API
  (`get_my_delivery_orders.php`, `supplier_update_status.php`) filters
  strictly by the **logged-in** supplier's own ID from the session — a
  supplier cannot see or touch another supplier's orders by sending a
  different ID.
- Customer ledger, invoice tables, and the core accounting helpers
  (`config/accounting_helpers.php`) already existed.
- A "Complete Order → payment confirmation" modal already existed on the
  frontend (`src/js/orders.js`, `src/js/employee-orders.js`).

No architecture was replaced. All changes below are additive/corrective,
in the same files and patterns the app already uses.

## Bugs found and fixed

1. **"Complete Order" was broken.** The frontend sent status `"Complete"`;
   `update_status.php` only accepted `"Completed"` — completing an order
   threw `Invalid status`. Fixed: the backend now accepts both and stores
   the canonical `"Completed"`.

2. **Order payments were never linked to that order's invoice.** Invoices
   were only ever created from quotations, never from orders — so paying
   on order completion moved money in the customer ledger but no invoice
   ever reflected it (direct violation of "invoice and ledger must share
   one source of truth"). Fixed with a new, idempotent
   `findOrCreateInvoiceForOrder()` / `applyOrderPaymentAndSyncInvoice()`
   pair in `accounting_helpers.php`, wired into `update_status.php`.

3. **A live "Pay" button called a file that doesn't exist**
   (`action/customers/add_transaction.php` → 404) in `orders.js`. Fixed by
   pointing it at the existing, working `action/orders/add_transaction.php`.

4. **Missing CSRF checks** on money-moving endpoints: `update_status.php`,
   `add_payment.php`, `add_customer_payment.php`, `add_supplier_payment.php`,
   `add_transaction.php`. Added `rbac_verify_csrf()` to all of them,
   consistent with how `supplier_update_status.php` already did it.

## Files changed

- `database/order_payment_completion_migration.sql` — **new**, additive,
  idempotent migration. Adds `orders.paid_amount` / `payment_status` /
  `completed_by` / `completed_at` if missing, widens `payment_status` /
  invoice `status` to hold `Pending/Partial/Paid` and `Unpaid/Partial/Paid`
  safely, adds a **unique key on `invoices.order_id`** (this is what makes
  "one invoice per order" and duplicate-prevention possible), and adds
  `order_id` / `invoice_id` / `recorded_by` to `customer_payments`.
  **Run this once against your database before deploying the code.**

- `config/accounting_helpers.php` — added `findOrCreateInvoiceForOrder()`
  and `applyOrderPaymentAndSyncInvoice()`. Existing helpers untouched.

- `action/orders/update_status.php` — rewritten (same shape, same
  permission model): accepts `Complete`/`Completed`, verifies CSRF, and
  when an order transitions to Completed, deducts stock (unchanged
  behaviour) **and** atomically creates/syncs the invoice + applies the
  payment-confirmation result, all in one DB transaction.

- `src/js/orders.js`, `src/js/employee-orders.js` — the Complete-order
  payment modal now sends `payment_received` / `paid_amount` directly to
  `update_status.php` in one call instead of two separate requests
  (previously: a broken/disconnected `add_transaction.php` call, then a
  status update). This removes the window where a payment could be
  recorded without the order completing, or vice versa.

- `src/js/orders.js` — fixed the 404'ing `customers/add_transaction.php`
  path in the general "Pay" modal.

## How the payment logic behaves (matches spec sections 5–10)

- **Full payment**: invoice → `paid = due? no, paid=total, due=0,
  status=Paid`; ledger debit (invoice) + credit (payment) posted once;
  `orders.payment_status = Paid`.
- **No payment**: invoice stays `Unpaid`, `due = total`; ledger carries
  the debit so the customer's outstanding balance is correct;
  `orders.payment_status = Pending`.
- **Partial payment**: invoice `status = Partial`, `due = total - paid`;
  ledger reflects the partial credit; `orders.payment_status = Partial`.
- **Duplicate submission / double-click "Complete"**: the second call is
  a no-op (`"Already in this status"`) — no duplicate ledger rows, no
  double stock deduction, no double payment. Verified directly against a
  live database (see Testing below).
- **CSRF**: a request with a missing/incorrect token is rejected before
  any DB write happens. Verified directly.

## Testing performed

All of the following were run against a real, throwaway MariaDB +
PHP 8.3 instance (not just read/reviewed):

- The migration was run **twice** in a row with no errors (idempotency).
- `applyOrderPaymentAndSyncInvoice()` was exercised standalone for: full
  payment, no payment, partial payment, and a repeated call for an
  already-paid order (confirmed no duplicate rows/entries and correct
  final invoice/order/customer-balance state each time).
- The real `action/orders/update_status.php` file was invoked end-to-end
  (simulated HTTP request, real session/CSRF/JSON body) with
  `status: "Complete"` — confirmed order status, stock, invoice, and
  ledger all ended up correct.
- Negative test: wrong CSRF token → request rejected, no state changed.
- Negative test: same "Complete" request sent twice → second call is a
  no-op, ledger has exactly the expected 2 rows (one debit, one credit),
  stock deducted exactly once, customer balance correct.
- Every `.php` file in the project (`find . -name "*.php" | xargs php -l`)
  and both edited `.js` files were syntax-checked after the changes — all
  clean.

## What I did not change (and why)

- **Three separate payment-recording endpoints still exist**
  (`add_payment.php`, `add_customer_payment.php`,
  `action/orders/add_transaction.php`), each with slightly different
  logic. I did not merge them — that's a real inconsistency worth
  addressing at some point, but doing so safely means checking every
  caller across the whole frontend, which is a larger, separate piece of
  work than this fix. I only patched the one that was outright broken
  (404) and added the missing CSRF checks to all three.
- **`action/employees/Employee.php`** has no RBAC check, but it's dead
  code — nothing in the app includes or requests it (it only defines an
  old, unused `Database` class). Left as-is; flagging here in case you
  want it deleted during cleanup.
- I did not touch the Delivered-stage ledger logic, stock deduction logic,
  RBAC config, or the supplier order-isolation logic — all already
  correct and out of scope for this fix.

## Before you deploy

1. **Back up your database.**
2. Run `database/order_payment_completion_migration.sql` against it.
3. Deploy the updated PHP/JS files.
4. Smoke-test one order through each path: complete with full payment,
   complete with no payment, complete with partial payment, and try
   double-clicking "Complete" once to confirm no duplicate ledger entries
   appear in your real environment too.
