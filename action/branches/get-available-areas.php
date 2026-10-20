<?php
/**
 * action/branches/get-available-areas.php
 * ------------------------------------------------------------------
 * Areas with no branch assigned yet (branch_id IS NULL). Used for
 * the "Add Area to Branch" picker, optionally filtered by `city_id`.
 * ------------------------------------------------------------------
 */
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('users', 'view');

header('Content-Type: application/json');

$cityId = !empty($_GET['city_id']) ? (int) $_GET['city_id'] : 0;

try {
    $pdo = rbac_db();

    $sql = "SELECT a.id, a.area_name, a.city_id, c.city_name
            FROM areas a
            LEFT JOIN cities c ON c.id = a.city_id
            WHERE a.branch_id IS NULL";
    $params = [];

    if ($cityId) {
        $sql .= " AND a.city_id = ?";
        $params[] = $cityId;
    }

    $sql .= " ORDER BY a.area_name ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
