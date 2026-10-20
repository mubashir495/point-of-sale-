<?php
/**
 * action/branches/delete-branch.php
 * ------------------------------------------------------------------
 * Deletes a branch. Refuses if the branch still has employees
 * assigned (move or remove them first) so logins/data are never
 * silently orphaned.
 * ------------------------------------------------------------------
 */
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('users', 'delete');
rbac_verify_csrf();

header('Content-Type: application/json');

$raw = json_decode(file_get_contents('php://input'), true);
$d = is_array($raw) ? $raw : $_POST;

$id = !empty($d['id']) ? (int) $d['id'] : 0;
if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Branch id is required.']);
    exit;
}

try {
    $pdo = rbac_db();

    $stmt = $pdo->prepare("SELECT branch_name FROM branches WHERE id = ?");
    $stmt->execute([$id]);
    $branch = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$branch) {
        echo json_encode(['success' => false, 'message' => 'Branch not found.']);
        exit;
    }
    if ($branch['branch_name'] === 'Main Branch') {
        echo json_encode(['success' => false, 'message' => 'Main Branch cannot be deleted.']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE branch_id = ?");
    $stmt->execute([$id]);
    if ((int) $stmt->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => 'This branch still has employees assigned. Remove or reassign them first.']);
        exit;
    }

    $pdo->prepare("UPDATE areas SET branch_id = NULL WHERE branch_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM branches WHERE id = ?")->execute([$id]);

    echo json_encode(['success' => true, 'message' => 'Branch deleted.']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}