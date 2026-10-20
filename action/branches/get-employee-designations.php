<?php
/**
 * action/branches/get-employee-designations.php
 * ------------------------------------------------------------------
 * Distinct, non-empty designations that actually exist among
 * registered employees - powers the Designation filter in the
 * Branch modal's "Add Employee" picker, so it only ever lists
 * designations that real employees are registered under.
 * ------------------------------------------------------------------
 */
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('users', 'view');

header('Content-Type: application/json');

try {
    $pdo = rbac_db();
    $stmt = $pdo->query("
        SELECT DISTINCT designation
        FROM employees
        WHERE designation IS NOT NULL AND designation <> ''
        ORDER BY designation ASC
    ");
    $designations = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo json_encode(['success' => true, 'data' => $designations]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
