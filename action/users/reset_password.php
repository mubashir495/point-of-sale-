<?php
/**
 * action/users/reset_password.php
 * POST { id, new_password, confirm_password }
 * Lets an admin (users:edit) reset another user's password without
 * knowing their old one.
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

$id          = $d['id'] ?? null;
$newPassword = $d['new_password'] ?? '';
$confirm     = $d['confirm_password'] ?? '';

if (!$id || $newPassword === '') {
    respond(['success' => false, 'message' => 'User id and new password are required.'], 422);
}

if (strlen($newPassword) < 8) {
    respond(['success' => false, 'message' => 'Password must be at least 8 characters.'], 422);
}

if ($confirm !== '' && $confirm !== $newPassword) {
    respond(['success' => false, 'message' => 'Password and confirm password do not match.'], 422);
}

try {
    $stmt = $pdo->prepare("UPDATE branch_users SET password = ? WHERE id = ?");
    $stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), $id]);

    if ($stmt->rowCount() === 0) {
        respond(['success' => false, 'message' => 'User not found.'], 404);
    }

    respond(['success' => true, 'message' => 'Password reset successfully.']);

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
