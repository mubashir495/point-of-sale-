<?php
/**
 * action/branches/get-branch-areas.php
 * ------------------------------------------------------------------
 * Areas currently assigned to one branch, for the "Areas in this
 * Branch" list inside the Branch modal.
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
        SELECT a.id, a.area_name, a.city_id, c.city_name
        FROM areas a
        LEFT JOIN cities c ON c.id = a.city_id
        WHERE a.branch_id = ?
        ORDER BY a.area_name ASC
    ");
    $stmt->execute([$branchId]);
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
