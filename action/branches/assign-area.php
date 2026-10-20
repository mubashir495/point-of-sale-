<?php
/**
 * action/branches/assign-area.php
 * ------------------------------------------------------------------
 * Adds an existing (unassigned) area to a branch by setting
 * areas.branch_id.
 * ------------------------------------------------------------------
 */
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('users', 'edit');
rbac_verify_csrf();

header('Content-Type: application/json');

$raw = json_decode(file_get_contents('php://input'), true);
$d = is_array($raw) ? $raw : $_POST;

$areaId   = !empty($d['area_id'])   ? (int) $d['area_id']   : 0;
$branchId = !empty($d['branch_id']) ? (int) $d['branch_id'] : 0;

if (!$areaId || !$branchId) {
    echo json_encode(['success' => false, 'message' => 'area_id and branch_id are required.']);
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

    $stmt = $pdo->prepare("UPDATE areas SET branch_id = ? WHERE id = ?");
    $stmt->execute([$branchId, $areaId]);

    echo json_encode(['success' => true, 'message' => 'Area added to branch.']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
