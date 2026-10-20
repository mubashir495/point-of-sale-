<?php
/**
 * action/users/toggle_status.php
 * POST { id, status: "active"|"inactive" }
 */

require_once '../../config/rbac.php';

header('Content-Type: application/json');
$pdo = rbac_db();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['success' => false, 'message' => 'Method Not Allowed.'], 405);
}

rbac_require_permission('users', 'edit');
rbac_verify_csrf();

$d = input();

$id     = $d['id'] ?? null;
$status = $d['status'] ?? null;

if (!$id || !in_array($status, ['active', 'inactive'], true)) {
    respond(['success' => false, 'message' => 'User id and a valid status (active/inactive) are required.'], 422);
}

$currentUser = rbac_current_user();
if ($currentUser && (int) $currentUser['id'] === (int) $id && $status === 'inactive') {
    respond(['success' => false, 'message' => 'You cannot deactivate your own account while logged in.'], 409);
}

try {
    $stmt = $pdo->prepare("UPDATE branch_users SET status = ? WHERE id = ?");
    $stmt->execute([$status, $id]);

    if ($stmt->rowCount() === 0) {
        respond(['success' => false, 'message' => 'User not found.'], 404);
    }

    respond(['success' => true, 'message' => 'User status updated successfully.']);

} catch (PDOException $e) {
    respond(['success' => false, 'message' => $e->getMessage()], 500);
}

function input()
{
    $json = json_decode(file_get_contents('php://input'), true);
    return (json_last_error() === JSON_ERROR_NONE && is_array($json)) ? $json : $_POST;
}

function respond($data, $status = 200)
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
