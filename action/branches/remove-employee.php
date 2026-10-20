<?php
/**
 * action/branches/remove-employee.php
 * ------------------------------------------------------------------
 * Removes an employee from their current branch (sets
 * employees.branch_id back to NULL), making them "available" again.
 * ------------------------------------------------------------------
 */
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('users', 'edit');
rbac_verify_csrf();

header('Content-Type: application/json');

$raw = json_decode(file_get_contents('php://input'), true);
$d = is_array($raw) ? $raw : $_POST;

$employeeId = !empty($d['employee_id']) ? (int) $d['employee_id'] : 0;

if (!$employeeId) {
    echo json_encode(['success' => false, 'message' => 'employee_id is required.']);
    exit;
}

try {
    $pdo = rbac_db();
    $stmt = $pdo->prepare("UPDATE employees SET branch_id = NULL WHERE id = ?");
    $stmt->execute([$employeeId]);
    rbac_sync_employee_branch_login($employeeId, null);

    echo json_encode(['success' => true, 'message' => 'Employee removed from branch.']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}