<?php
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('purchase', 'create');

header('Content-Type: application/json');
require_once '../../config/Database.php';

$rawBody = file_get_contents('php://input');
$input = json_decode($rawBody, true);

if (!is_array($input)) {
    echo json_encode(['success' => false, 'message' => 'Invalid request body.']);
    exit;
}

// ---- Read input ----
$party_id      = isset($input['party_id']) ? (int) $input['party_id'] : 0;
$purchase_date = trim($input['purchase_date'] ?? date('Y-m-d'));
$items         = $input['items'] ?? [];
$paid_amount   = (float) ($input['paid_amount'] ?? 0);
$remarks       = trim($input['remarks'] ?? '');

// ---- Validation ----
if ($party_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Party is required.']);
    exit;
}

if (empty($items)) {
    echo json_encode(['success' => false, 'message' => 'At least one item is required.']);
    exit;
}

$cleanItems = [];
$total = 0;
foreach ($items as $item) {
    $material_id = (int) ($item['raw_material_id'] ?? 0);
    $qty = (float) ($item['quantity'] ?? 0);
    $rate = (float) ($item['rate'] ?? 0);

    if ($material_id <= 0 || $qty <= 0 || $rate <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid item data.']);
        exit;
    }
    $line_total = round($qty * $rate, 2);
    $total += $line_total;
    $cleanItems[] = [
        'raw_material_id' => $material_id,
        'quantity' => $qty,
        'rate' => $rate,
        'line_total' => $line_total,
    ];
}

$total = round($total, 2);
$paid_amount = round($paid_amount, 2);

if ($paid_amount > $total) {
    echo json_encode(['success' => false, 'message' => 'Paid amount cannot exceed total amount.']);
    exit;
}

$remaining = round($total - $paid_amount, 2);
$status = $remaining <= 0 ? 'paid' : ($paid_amount > 0 ? 'partial' : 'unpaid');

try {
    $db   = new Database();
    $conn = $db->getConnection();

    // ---- Verify party exists ----
    $check = $conn->prepare("SELECT id, payable_balance FROM parties WHERE id = ?");
    $check->execute([$party_id]);
    $party = $check->fetch(PDO::FETCH_ASSOC);
    if (!$party) {
        echo json_encode(['success' => false, 'message' => 'Party not found.']);
        exit;
    }

    $conn->beginTransaction();

    // ---- 1. Insert purchase_master ----
    $stmt = $conn->prepare("
        INSERT INTO purchase_master
            (party_id, purchase_date, total_amount, paid_amount, balance_amount, status, remarks, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$party_id, $purchase_date, $total, $paid_amount, $remaining, $status, $remarks]);
    $purchase_id = $conn->lastInsertId();

    // ---- 2. Generate PO number ----
    $po_no = 'PO-' . str_pad($purchase_id, 5, '0', STR_PAD_LEFT);
    $conn->prepare("UPDATE purchase_master SET po_no = ? WHERE id = ?")->execute([$po_no, $purchase_id]);

    // ---- 3. Insert purchase_details & update stock ----
    $detailStmt = $conn->prepare("
        INSERT INTO purchase_details (purchase_id, raw_material_id, quantity, rate, line_total)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stockStmt = $conn->prepare("
        UPDATE raw_materials
        SET current_stock = current_stock + ?, last_purchase_rate = ?
        WHERE id = ?
    ");

    $detailIds = [];
    foreach ($cleanItems as $idx => $item) {
        $detailStmt->execute([
            $purchase_id,
            $item['raw_material_id'],
            $item['quantity'],
            $item['rate'],
            $item['line_total']
        ]);
        $detailIds[$idx] = $conn->lastInsertId(); // <-- added: needed for invoice items FK
        $stockStmt->execute([$item['quantity'], $item['rate'], $item['raw_material_id']]);
    }

    // ---- 4. Update party payable balance ----
    if ($remaining > 0) {
        $updateParty = $conn->prepare("
            UPDATE parties
            SET payable_balance = payable_balance + ?, updated_at = NOW()
            WHERE id = ?
        ");
        $updateParty->execute([$remaining, $party_id]);
        $newPayable = $party['payable_balance'] + $remaining;
    } else {
        $newPayable = $party['payable_balance'];
    }

    // ---- 5. Create purchase invoice ----
    $invoiceStmt = $conn->prepare("
        INSERT INTO purchase_invoices
            (purchase_id, party_id, invoice_no, invoice_date, subtotal, total_amount, paid_amount, balance_amount, status, remarks, created_at)
        VALUES (?, ?, '', ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $invoiceStmt->execute([
        $purchase_id,
        $party_id,
        $purchase_date,
        $total,
        $total,
        $paid_amount,
        $remaining,
        $status,
        $remarks
    ]);
    $invoice_id = $conn->lastInsertId();

    $invoice_no = 'PINV-' . str_pad($invoice_id, 6, '0', STR_PAD_LEFT);
    $conn->prepare("UPDATE purchase_invoices SET invoice_no = ? WHERE id = ?")
         ->execute([$invoice_no, $invoice_id]);

    // ---- 6. Insert purchase invoice items ----
    $invoiceItemStmt = $conn->prepare("
        INSERT INTO purchase_invoice_items
            (invoice_id, purchase_detail_id, raw_material_id, quantity, rate, line_total, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    foreach ($cleanItems as $idx => $item) {
        $invoiceItemStmt->execute([
            $invoice_id,
            $detailIds[$idx],
            $item['raw_material_id'],
            $item['quantity'],
            $item['rate'],
            $item['line_total']
        ]);
    }

    // ---- 7. Party ledger: purchase entry ----
    $prevBalance = (float) $party['payable_balance'];
    $purchaseLedgerBalance = round($prevBalance + $total, 2);

    $ledgerStmt = $conn->prepare("
        INSERT INTO party_ledger
            (party_id, purchase_id, invoice_id, transaction_date, reference_no, description, transaction_type, debit, credit, balance, created_at)
        VALUES (?, ?, ?, ?, ?, ?, 'purchase', ?, 0, ?, NOW())
    ");
    $ledgerStmt->execute([
        $party_id,
        $purchase_id,
        $invoice_id,
        $purchase_date,
        $invoice_no,
        'Purchase Invoice ' . $invoice_no,
        $total,
        $purchaseLedgerBalance
    ]);

    // ---- 8. Party ledger: payment entry (only if paid_amount > 0) ----
    if ($paid_amount > 0) {
        $paymentLedgerBalance = round($purchaseLedgerBalance - $paid_amount, 2);

        $paymentLedgerStmt = $conn->prepare("
            INSERT INTO party_ledger
                (party_id, purchase_id, invoice_id, transaction_date, reference_no, description, transaction_type, debit, credit, balance, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 'payment', 0, ?, ?, NOW())
        ");
        $paymentLedgerStmt->execute([
            $party_id,
            $purchase_id,
            $invoice_id,
            $purchase_date,
            $invoice_no,
            'Purchase Payment ' . $invoice_no,
            $paid_amount,
            $paymentLedgerBalance
        ]);
    }

    $conn->commit();

    // ---- Response ----
    echo json_encode([
        'success' => true,
        'message' => 'Purchase saved successfully.',
        'data' => [
            'purchase_id' => $purchase_id,
            'party_id' => $party_id,
            'total_amount' => $total,
            'paid_amount' => $paid_amount,
            'remaining_balance' => $remaining,
            'updated_payable_balance' => $newPayable,
            'invoice_id' => $invoice_id,
            'invoice_no' => $invoice_no,
            'status' => $status,
        ]
    ]);

} catch (Throwable $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}