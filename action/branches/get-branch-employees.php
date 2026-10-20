<?php
/**
 * action/branches/get-branch-employees.php
 * ------------------------------------------------------------------
 * Employees currently assigned to one branch, for the "Employees in
 * this Branch" list inside the Branch modal. Every row carries its
 * designation so the (mixed) list can show a designation tag next to
 * each name.
 * ------------------------------------------------------------------
 */
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('users', 'view');

header('Content-Type: application/json');

$branchId = !empty($_GET['branch_id']) ? (int) $_GET['branch_id'] : 0;
if (!$branchId) {
    echo json_encode(['success' => true, 'data' => []]);
    exit;
}

try {
    $pdo = rbac_db();
    $stmt = $pdo->prepare("
        SELECT id, full_name, designation, contact, username
        FROM employees
        WHERE branch_id = ?
        ORDER BY full_name ASC
    ");
    $stmt->execute([$branchId]);
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
