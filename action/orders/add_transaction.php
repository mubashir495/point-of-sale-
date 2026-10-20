<?php
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('sales', 'create');
rbac_verify_csrf();

require_once '../../config/database.php';

header('Content-Type: application/json');

try {
    $database = new Database();
    $db = $database->getConnection();

    $input = json_decode(file_get_contents("php://input"), true);

    $customer_id = isset($input['customer_id']) ? (int)$input['customer_id'] : 0;
    $type        = isset($input['type']) ? trim($input['type']) : '';
    $amount      = isset($input['amount']) ? (float)$input['amount'] : 0;
    $remarks     = isset($input['remarks']) ? trim($input['remarks']) : '';

    if ($customer_id <= 0 || $amount <= 0 || !in_array($type, ['in', 'out'])) {
        echo json_encode([
            "success" => false,
            "message" => "Invalid data"
        ]);
        exit;
    }

    $db->beginTransaction();

    // 1. Save transaction (customer_transactions is a general log, NOT the same
    //    table as customer_payments — so its ID must NOT be used as payment_id)
    $stmt = $db->prepare("
        INSERT INTO customer_transactions
        (customer_id, type, amount, remarks)
        VALUES (?, ?, ?, ?)
    ");

    $stmt->execute([
        $customer_id,
        $type,
        $amount,
        $remarks
    ]);

    // Ledger values
    $debit = ($type === 'out') ? $amount : 0;
    $credit = ($type === 'in') ? $amount : 0;

    $desc = !empty($remarks)
        ? $remarks
        : ($type === 'in' ? 'Payment Received' : 'Payment Made');

    $today = date('Y-m-d');

    // 2. Ledger Entry
    // payment_id is FK -> customer_payments(id). We are not inserting into
    // customer_payments here, so we must pass NULL (column allows NULL per
    // "ON DELETE SET NULL" in your constraint) instead of the
    // customer_transactions insert id, otherwise the FK constraint fails.
    $stmt = $db->prepare("
        INSERT INTO customer_ledger
        (
            customer_id,
            invoice_id,
            payment_id,
            entry_date,
            description,
            debit,
            credit,
            balance
        )
        VALUES (?, NULL, NULL, ?, ?, ?, ?, 0)
    ");

    $stmt->execute([
        $customer_id,
        $today,
        $desc,
        $debit,
        $credit
    ]);

    // 3. Update customer balance
    if ($type == 'in') {

        $stmt = $db->prepare("
            UPDATE customers
            SET current_balance = current_balance - ?
            WHERE id = ?
        ");

        $stmt->execute([$amount, $customer_id]);

    } else {

        $stmt = $db->prepare("
            UPDATE customers
            SET current_balance = current_balance + ?
            WHERE id = ?
        ");

        $stmt->execute([$amount, $customer_id]);
    }

    // 4. Apply payment to invoices (oldest unpaid first)
    if ($type == 'in') {

        $stmt = $db->prepare("
            SELECT id, due_amount
            FROM invoices
            WHERE customer_id = ?
            AND due_amount > 0
            ORDER BY invoice_date ASC, id ASC
        ");

        $stmt->execute([$customer_id]);

        $remaining = $amount;

        while (($invoice = $stmt->fetch(PDO::FETCH_ASSOC)) && $remaining > 0) {

            $apply = min($remaining, $invoice['due_amount']);

            $update = $db->prepare("
                UPDATE invoices
                SET
                    paid_amount = paid_amount + ?,
                    due_amount = due_amount - ?,
                    status =
                        CASE
                            WHEN due_amount - ? <= 0 THEN 'Paid'
                            WHEN paid_amount + ? > 0 THEN 'Partial'
                            ELSE 'Unpaid'
                        END
                WHERE id = ?
            ");

            $update->execute([
                $apply,
                $apply,
                $apply,
                $apply,
                $invoice['id']
            ]);

            $remaining -= $apply;
        }
    }

    $db->commit();

    echo json_encode([
        "success" => true,
        "message" => "Transaction posted successfully."
    ]);

} catch (Exception $e) {

    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}