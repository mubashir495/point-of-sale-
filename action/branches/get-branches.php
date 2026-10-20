<?php
/**
 * action/branches/get-branches.php
 * ------------------------------------------------------------------
 * Lists all branches with their manager's name and how many
 * employees are currently assigned to each - used to populate the
 * Branch Users page (branch dropdown + name-autocomplete suggestions).
 * ------------------------------------------------------------------
 */
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('users', 'view');

header('Content-Type: application/json');

try {
    $pdo = rbac_db();
    $stmt = $pdo->query("
        SELECT
            b.id,
            b.branch_name,
            b.manager_id,
            b.address,
            b.phone,
            b.status,
            m.full_name AS manager_name,
            (SELECT COUNT(*) FROM employees e WHERE e.branch_id = b.id) AS employee_count,
            (SELECT COUNT(*) FROM areas a WHERE a.branch_id = b.id) AS area_count
        FROM branches b
        LEFT JOIN employees m ON m.id = b.manager_id
        ORDER BY b.branch_name ASC
    ");
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}