<?php
/**
 * action/branches/get-available-employees.php
 * ------------------------------------------------------------------
 * Employees with no branch assigned yet (branch_id IS NULL).
 * Used for:
 *   - the Branch Manager picker (no `designation` param)
 *   - the "Add Employee to Branch" picker, filtered by `designation`
 *     so only employees already registered under that exact
 *     designation in the Employees module show up.
 * ------------------------------------------------------------------
 */
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('users', 'view');

header('Content-Type: application/json');

$designation = trim($_GET['designation'] ?? '');

try {
    $pdo = rbac_db();

    $sql = "SELECT id, full_name, designation, contact, username FROM employees WHERE branch_id IS NULL";
    $params = [];

    if ($designation !== '') {
        $sql .= " AND designation = ?";
        $params[] = $designation;
    }

    $sql .= " ORDER BY full_name ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
