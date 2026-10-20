<?php
require_once '../../config/rbac.php';

rbac_start_session();
header('Content-Type: application/json');

$user = rbac_current_user();

if ($user) {
    echo json_encode([
        'authenticated' => true,
        'user' => [
            'id'         => $user['id'],
            'username'   => $user['username'],
            'user_type'  => $_SESSION['user_type'] ?? null, // legacy field, kept for compatibility
            'branch_id'  => $_SESSION['branch_id'] ?? null, // legacy field, kept for compatibility
            'role_id'    => $user['role_id'],
            'role_name'  => $user['role_name'],
            'role_slug'  => $user['role_slug'],
            'is_super'   => $user['is_super'],
            'employee_id' => $user['employee_id'],
        ],
        'permissions' => $user['permissions'],
        'csrf_token'  => rbac_csrf_token(),
    ]);
} else {
    http_response_code(401);
    echo json_encode([
        'authenticated' => false,
        'message' => 'Not authenticated'
    ]);
}
