<?php
/**
 * action/branches/remove-area.php
 * ------------------------------------------------------------------
 * Removes an area from its current branch (sets areas.branch_id
 * back to NULL), making it "available" again.
 * ------------------------------------------------------------------
 */
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('users', 'edit');
rbac_verify_csrf();

header('Content-Type: application/json');

$raw = json_decode(file_get_contents('php://input'), true);
$d = is_array($raw) ? $raw : $_POST;

$areaId = !empty($d['area_id']) ? (int) $d['area_id'] : 0;

if (!$areaId) {
    echo json_encode(['success' => false, 'message' => 'area_id is required.']);
    exit;
}

try {
    $pdo = rbac_db();
    $stmt = $pdo->prepare("UPDATE areas SET branch_id = NULL WHERE id = ?");
    $stmt->execute([$areaId]);

    echo json_encode(['success' => true, 'message' => 'Area removed from branch.']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
