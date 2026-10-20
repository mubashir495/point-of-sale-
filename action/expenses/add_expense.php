<?php
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('expenses', 'create');

require_once '../../config/database.php';
$database = new Database();
$db = $database->getConnection();
header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $date = trim($input['expense_date'] ?? '');
    $headId = (int)($input['head_id'] ?? 0);
    $drAccount = (int)($input['dr_account_id'] ?? 0);
    $crAccount = (int)($input['cr_account_id'] ?? 0);
    $amount = (float)($input['amount'] ?? 0);
    $desc = trim($input['description'] ?? '');
    if ($date === '' || $headId <= 0 || $drAccount <= 0 || $crAccount <= 0 || $amount <= 0) {
        throw new Exception('All fields required');
    }
    if ($drAccount === $crAccount) throw new Exception('Expense Account and Cash/Bank cannot be same');
    
    $db->beginTransaction();
    $stmt = $db->prepare("INSERT INTO expenses (expense_date, head_id, dr_account_id, cr_account_id, amount, description, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $stmt->execute([$date, $headId, $drAccount, $crAccount, $amount, $desc]);
    $expenseId = $db->lastInsertId();
    $db->commit();
    
    echo json_encode(['success' => true, 'message' => 'Expense saved', 'id' => $expenseId]);
    
} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}