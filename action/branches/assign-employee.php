<?php
/**
 * action/branches/assign-employee.php
 * ------------------------------------------------------------------
 * Adds an existing (unassigned) employee to a branch by setting
 * employees.branch_id. Used by the "Add Employee to Branch" picker
 * inside the Branch modal.
 * ------------------------------------------------------------------
 */
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('users', 'edit');
rbac_verify_csrf();

header('Content-Type: application/json');

$raw = json_decode(file_get_contents('php://input'), true);
$d = is_array($raw) ? $raw : $_POST;

$employeeId = !empty($d['employee_id']) ? (int) $d['employee_id'] : 0;
$branchId   = !empty($d['branch_id'])   ? (int) $d['branch_id']   : 0;

if (!$employeeId || !$branchId) {
    echo json_encode(['success' => false, 'message' => 'employee_id and branch_id are required.']);
    exit;
}

try {
    $pdo = rbac_db();

    $stmt = $pdo->prepare("SELECT id FROM branches WHERE id = ?");
    $stmt->execute([$branchId]);
    if (!$stmt->fetchColumn()) {
        echo json_encode(['success' => false, 'message' => 'Branch not found.']);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE employees SET branch_id = ? WHERE id = ?");
    $stmt->execute([$branchId, $employeeId]);
    rbac_sync_employee_branch_login($employeeId, $branchId);

    echo json_encode(['success' => true, 'message' => 'Employee added to branch.']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}