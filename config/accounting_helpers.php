<?php
/**
 * Accounting Helper Functions (using only existing tables)
 */

function getCustomerBalance($db, $customerId) {
    $stmt = $db->prepare("SELECT current_balance FROM customers WHERE id = ?");
    $stmt->execute([$customerId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? (float)$result['current_balance'] : 0;
}

function updateCustomerBalanceAndLedger($db, $customerId, $debit, $credit, $description, $referenceType, $referenceId) {
    $currentBalance = getCustomerBalance($db, $customerId);
    $newBalance = $currentBalance + $debit - $credit;
    
    $stmt = $db->prepare("UPDATE customers SET current_balance = ? WHERE id = ?");
    $stmt->execute([$newBalance, $customerId]);
    
    $stmt = $db->prepare("
        INSERT INTO customer_ledger 
        (customer_id, debit, credit, balance, description, reference_type, reference_id, entry_date, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");
    $stmt->execute([$customerId, $debit, $credit, $newBalance, $description, $referenceType, $referenceId]);
    return $newBalance;
}

function getSupplierBalance($db, $partyId) {
    $stmt = $db->prepare("SELECT payable_balance FROM parties WHERE id = ?");
    $stmt->execute([$partyId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? (float)$result['payable_balance'] : 0;
}

function updateSupplierBalanceAndLedger($db, $partyId, $debit, $credit, $description, $referenceType, $referenceId) {
    $currentBalance = getSupplierBalance($db, $partyId);
    $newBalance = $currentBalance + $credit - $debit;
    
    $stmt = $db->prepare("UPDATE parties SET payable_balance = ? WHERE id = ?");
    $stmt->execute([$newBalance, $partyId]);
    
    $stmt = $db->prepare("
        INSERT INTO party_ledger 
        (party_id, debit, credit, balance, description, reference_type, reference_id, transaction_date, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");
    $stmt->execute([$partyId, $debit, $credit, $newBalance, $description, $referenceType, $referenceId]);
    return $newBalance;
}

function customerLedgerExists($db, $referenceType, $referenceId) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM customer_ledger WHERE reference_type = ? AND reference_id = ?");
    $stmt->execute([$referenceType, $referenceId]);
    return (int)$stmt->fetchColumn() > 0;
}

function partyLedgerExists($db, $referenceType, $referenceId) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM party_ledger WHERE reference_type = ? AND reference_id = ?");
    $stmt->execute([$referenceType, $referenceId]);
    return (int)$stmt->fetchColumn() > 0;
}

function stockLedgerExists($db, $referenceType, $referenceId) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM stock_ledger WHERE reference_type = ? AND reference_id = ?");
    $stmt->execute([$referenceType, $referenceId]);
    return (int)$stmt->fetchColumn() > 0;
}

/**
 * Finds the invoice already linked to an order (by orders.id ->
 * invoices.order_id), or creates one from the order's own totals if
 * none exists yet. Idempotent: relies on the `uq_invoices_order_id`
 * unique key added by database/order_payment_completion_migration.sql,
 * so calling this twice for the same order never creates a duplicate
 * invoice - the second call just returns the first invoice's row.
 *
 * This is what makes "Customer Invoice" (spec #8) and "Customer
 * Ledger" (spec #9) share one source of truth for a given order,
 * instead of an order being completed with no invoice ever created.
 */
function findOrCreateInvoiceForOrder($db, array $order): array {
    $stmt = $db->prepare("SELECT * FROM invoices WHERE order_id = ? LIMIT 1");
    $stmt->execute([$order['id']]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($invoice) {
        return $invoice;
    }

    $stmt = $db->query("SELECT MAX(id) AS last_id FROM invoices");
    $last = $stmt->fetch(PDO::FETCH_ASSOC);
    $invoiceNo = 'INV' . str_pad((int)($last['last_id'] ?? 0) + 1, 5, '0', STR_PAD_LEFT);

    $stmt = $db->prepare("
        INSERT INTO invoices
            (invoice_no, order_id, customer_id, employee_id, invoice_date, shift,
             subtotal, discount, total, paid_amount, due_amount, status)
        VALUES
            (:invoice_no, :order_id, :customer_id, :employee_id, :invoice_date, :shift,
             :subtotal, :discount, :total, 0, :due_amount, 'Unpaid')
    ");
    $stmt->execute([
        ':invoice_no'    => $invoiceNo,
        ':order_id'      => $order['id'],
        ':customer_id'   => $order['customer_id'],
        ':employee_id'   => $order['employee_id'] ?? null,
        ':invoice_date'  => $order['order_date'] ?? date('Y-m-d'),
        ':shift'         => $order['shift'] ?? null,
        ':subtotal'      => $order['subtotal'] ?? $order['total'],
        ':discount'      => $order['discount'] ?? 0,
        ':total'         => $order['total'],
        ':due_amount'    => $order['total'],
    ]);

    $stmt = $db->prepare("SELECT * FROM invoices WHERE id = ?");
    $stmt->execute([(int)$db->lastInsertId()]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Single source of truth for "Complete Order -> payment confirmation"
 * (spec sections 5, 6, 7, 8, 9, 10). Called once, inside the same
 * transaction as the order's status update, when an order transitions
 * into the Completed state.
 *
 * - Ensures the order's invoice exists (find-or-create, idempotent).
 * - Ensures the customer ledger carries the invoice's debit exactly
 *   once, whichever path the order took to get here (some orders go
 *   Pending -> Delivered -> Completed and already have a 'delivery'
 *   debit; others can jump straight to Completed with no prior
 *   ledger entry at all - both are guarded against duplicates here
 *   via reference_type/reference_id, so calling this twice for the
 *   same order never double-books the debt).
 * - If $paidAmount > 0: records a customer_payments row tied to this
 *   order/invoice, applies it to the invoice's paid/due/status, and
 *   posts a matching ledger credit - all guarded by the same
 *   reference_type/reference_id idempotency check.
 * - If $paidAmount <= 0: leaves the invoice as Unpaid/Partial so the
 *   outstanding balance stays correctly reflected in the ledger
 *   (spec #6 - order can still complete without payment).
 * - Mirrors the final paid_amount/payment_status onto the orders row
 *   itself so order lists don't need to join invoices to show it.
 *
 * Returns the resulting invoice row (fresh from the DB).
 */
function applyOrderPaymentAndSyncInvoice($db, array $order, float $paidAmount, ?int $userId): array {
    $orderId = (int) $order['id'];
    $customerId = $order['customer_id'] ?? null;
    $paidAmount = round(max(0, $paidAmount), 2);

    $invoice = findOrCreateInvoiceForOrder($db, $order);
    $invoiceId = (int) $invoice['id'];

    // 1) Make sure the customer owes the invoice total exactly once,
    //    regardless of whether a 'delivery' debit was already posted
    //    earlier in this order's lifecycle.
    if ($customerId
        && !customerLedgerExists($db, 'delivery', $orderId)
        && !customerLedgerExists($db, 'invoice', $orderId)
    ) {
        updateCustomerBalanceAndLedger(
            $db,
            $customerId,
            (float) $order['total'],
            0,
            'Invoice ' . $invoice['invoice_no'] . ' for Order ' . ($order['order_no'] ?? $orderId),
            'invoice',
            $orderId
        );
    }

    // 2) Apply the payment, once, guarded against duplicate submits of
    //    the same "Complete Order" confirmation.
    if ($paidAmount > 0 && $customerId && !customerLedgerExists($db, 'order_payment', $orderId)) {
        $stmt = $db->prepare("
            INSERT INTO customer_payments
                (customer_id, order_id, invoice_id, recorded_by, amount, payment_date, payment_method, reference_no, remarks)
            VALUES
                (:customer_id, :order_id, :invoice_id, :recorded_by, :amount, :payment_date, 'Cash', :reference_no, :remarks)
        ");
        $stmt->execute([
            ':customer_id'   => $customerId,
            ':order_id'      => $orderId,
            ':invoice_id'    => $invoiceId,
            ':recorded_by'   => $userId,
            ':amount'        => $paidAmount,
            ':payment_date'  => date('Y-m-d'),
            ':reference_no'  => 'ORD-' . ($order['order_no'] ?? $orderId),
            ':remarks'       => 'Payment on completing Order ' . ($order['order_no'] ?? $orderId),
        ]);

        updateCustomerBalanceAndLedger(
            $db,
            $customerId,
            0,
            $paidAmount,
            'Payment for Order ' . ($order['order_no'] ?? $orderId),
            'order_payment',
            $orderId
        );

        $newPaid = round((float)$invoice['paid_amount'] + $paidAmount, 2);
        $newDue = round((float)$invoice['total'] - $newPaid, 2);
        $newStatus = $newDue <= 0 ? 'Paid' : ($newPaid > 0 ? 'Partial' : 'Unpaid');

        $stmt = $db->prepare("
            UPDATE invoices
            SET paid_amount = :paid, due_amount = :due, status = :status, updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            ':paid'   => $newPaid,
            ':due'    => $newDue,
            ':status' => $newStatus,
            ':id'     => $invoiceId,
        ]);
    }

    // 3) Mirror the invoice's payment state onto the order row itself.
    $stmt = $db->prepare("SELECT paid_amount, due_amount, status FROM invoices WHERE id = ?");
    $stmt->execute([$invoiceId]);
    $finalInvoice = $stmt->fetch(PDO::FETCH_ASSOC);

    $orderPaymentStatus = $finalInvoice['status'] === 'Paid' ? 'Paid'
        : (((float)$finalInvoice['paid_amount']) > 0 ? 'Partial' : 'Pending');

    $stmt = $db->prepare("
        UPDATE orders
        SET paid_amount = :paid, payment_status = :status, completed_by = :completed_by, completed_at = NOW()
        WHERE id = :id
    ");
    $stmt->execute([
        ':paid'         => $finalInvoice['paid_amount'],
        ':status'       => $orderPaymentStatus,
        ':completed_by' => $userId,
        ':id'           => $orderId,
    ]);

    $stmt = $db->prepare("SELECT * FROM invoices WHERE id = ?");
    $stmt->execute([$invoiceId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}


function updateStockAndLedger($db, $productId, $quantity, $type, $referenceType, $referenceId, $note = '') {
    // Get current stock from products table
    $stmt = $db->prepare("SELECT current_stock FROM products WHERE id = ?");
    $stmt->execute([$productId]);
    $currentStock = (float)$stmt->fetchColumn();
    
    $newStock = ($type === 'in') ? $currentStock + $quantity : $currentStock - $quantity;
    
    $stmt = $db->prepare("UPDATE products SET current_stock = ? WHERE id = ?");
    $stmt->execute([$newStock, $productId]);
    
    $stmt = $db->prepare("
        INSERT INTO stock_ledger 
        (product_id, type, qty, reference_type, reference_id, note, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$productId, $type, $quantity, $referenceType, $referenceId, $note]);
    return $newStock;
}