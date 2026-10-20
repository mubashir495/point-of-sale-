<?php
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('ledger', 'create');
rbac_verify_csrf();

require_once '../../config/database.php';
require_once '../../config/accounting_helpers.php';

$database = new Database();
$db = $database->getConnection();
header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $customerId = (int)($input['customer_id'] ?? 0);
    $amount = (float)($input['amount'] ?? 0);
    $paymentDate = $input['payment_date'] ?? date('Y-m-d');
    $paymentMethod = $input['payment_method'] ?? 'Cash';
    $referenceNo = $input['reference_no'] ?? '';
    $remarks = $input['remarks'] ?? '';
    if ($customerId <= 0 || $amount <= 0) throw new Exception('customer_id and amount required');
    
    // Check customer exists
    $stmt = $db->prepare("SELECT id FROM customers WHERE id = ?");
    $stmt->execute([$customerId]);
    if (!$stmt->fetch()) throw new Exception('Customer not found');
    
    $db->beginTransaction();
    
    // Insert into customer_payments
    $stmt = $db->prepare("INSERT INTO customer_payments (customer_id, amount, payment_date, payment_method, reference_no, remarks, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $stmt->execute([$customerId, $amount, $paymentDate, $paymentMethod, $referenceNo, $remarks]);
    $paymentId = $db->lastInsertId();
    
    // Update customer balance and ledger (Credit reduces receivable)
    updateCustomerBalanceAndLedger(
        $db,
        $customerId,
        0,
        $amount,
        'Payment Received - ' . ($referenceNo ?: 'Ref#' . $paymentId),
        'payment',
        $paymentId
    );
    
    $db->commit();
    echo json_encode(['success' => true, 'message' => 'Payment recorded', 'payment_id' => $paymentId, 'new_balance' => getCustomerBalance($db, $customerId)]);
    
} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}