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
    $partyId = (int)($input['party_id'] ?? 0);
    $amount = (float)($input['amount'] ?? 0);
    $paymentDate = $input['payment_date'] ?? date('Y-m-d');
    $referenceNo = $input['reference_no'] ?? '';
    $remarks = $input['remarks'] ?? '';
    if ($partyId <= 0 || $amount <= 0) throw new Exception('party_id and amount required');
    
    // Check party exists and is supplier
    $stmt = $db->prepare("SELECT id FROM parties WHERE id = ? AND type = 'supplier'");
    $stmt->execute([$partyId]);
    if (!$stmt->fetch()) throw new Exception('Supplier not found');
    
    $db->beginTransaction();
    
    // Payment reduces payable => Debit in party_ledger
    updateSupplierBalanceAndLedger(
        $db,
        $partyId,
        $amount,
        0,
        'Payment Made - ' . ($referenceNo ?: 'Payment'),
        'payment',
        $partyId // or a unique id, we can use party_id as reference
    );
    
    $db->commit();
    echo json_encode(['success' => true, 'message' => 'Supplier payment recorded', 'new_balance' => getSupplierBalance($db, $partyId)]);
    
} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}