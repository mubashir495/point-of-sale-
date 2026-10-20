<?php
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('sales', 'create');

require_once '../../config/database.php';
require_once '../../config/accounting_helpers.php';

$database = new Database();
$db = $database->getConnection();
header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $customerId = (int)($input['customer_id'] ?? 0);
    $subtotal = (float)($input['subtotal'] ?? 0);
    $discount = (float)($input['discount'] ?? 0);
    $total = (float)($input['total'] ?? 0);
    $paidAmount = (float)($input['paid_amount'] ?? $total);
    $remarks = $input['remarks'] ?? '';
    if (empty($input['items'])) throw new Exception('items[] required');

    $branchId = rbac_current_branch_id();
    if (rbac_is_branch_scoped() && !$branchId) {
        throw new Exception("Your account isn't linked to a branch yet - contact an admin.");
    }
    
    $db->beginTransaction();
    
    // Insert order (temporary order_no placeholder to satisfy NOT NULL constraint)
    $tempOrderNo = 'TMP-' . uniqid();

    $sql = "INSERT INTO orders (order_no, customer_id, order_date, subtotal, discount, total, paid_amount, payment_status, status, remarks, branch_id, created_at, updated_at)
            VALUES (:order_no, :customer_id, CURDATE(), :subtotal, :discount, :total, :paid_amount, 'Paid', 'Counter', :remarks, :branch_id, NOW(), NOW())";
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':order_no' => $tempOrderNo,
        ':customer_id' => $customerId ?: null,
        ':subtotal' => $subtotal,
        ':discount' => $discount,
        ':total' => $total,
        ':paid_amount' => $paidAmount,
        ':remarks' => $remarks,
        ':branch_id' => $branchId
    ]);
    $orderId = $db->lastInsertId();
    $invoiceNo = 'CNT-' . str_pad($orderId, 6, '0', STR_PAD_LEFT);
    $stmt = $db->prepare("UPDATE orders SET order_no = ? WHERE id = ?");
    $stmt->execute([$invoiceNo, $orderId]);
    
    // Insert invoice (if table exists, otherwise skip)
    // If invoices table exists, you can insert; but we'll just note it's not required.
    
    // Insert items and reduce stock
    $itemStmt = $db->prepare("INSERT INTO order_items (order_id, product_id, quantity, unit_price, total) VALUES (?, ?, ?, ?, ?)");
    foreach ($input['items'] as $item) {
        $productId = (int)$item['product_id'];
        $quantity = (int)$item['quantity'];
        $unitPrice = (float)$item['unit_price'];
        $itemTotal = (float)$item['total'];
        $itemStmt->execute([$orderId, $productId, $quantity, $unitPrice, $itemTotal]);
        
        // Update stock and create stock ledger
        updateStockAndLedger($db, $productId, $quantity, 'out', 'sale', $orderId, 'Counter Sale ' . $invoiceNo);
    }
    
    // Do NOT update customer balance – cash sale
    
    $db->commit();
    echo json_encode(['success' => true, 'message' => 'Counter sale completed', 'order_id' => $orderId, 'invoice_no' => $invoiceNo]);
    
} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}