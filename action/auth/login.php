<?php
require_once '../../config/rbac.php';

rbac_start_session();
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$json = json_decode(file_get_contents('php://input'), true);
$data = (json_last_error() === JSON_ERROR_NONE && is_array($json)) ? $json : $_POST;

$username = trim($data['username'] ?? '');
$password = $data['password'] ?? '';

if (empty($username) || empty($password)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Username and password are required']);
    exit;
}

try {
    $pdo = rbac_db();

    $stmt = $pdo->prepare("
        SELECT
            bu.id,
            bu.username,
            bu.password,
            bu.user_type,
            bu.branch_id,
            bu.employee_id,
            bu.role_id,
            bu.status,
            bu.profile_image,
            COALESCE(bu.full_name, e.full_name) AS name,
            r.name AS role_name,
            r.slug AS role_slug
        FROM branch_users bu
        LEFT JOIN employees e ON e.id = bu.employee_id
        LEFT JOIN roles r ON r.id = bu.role_id
        WHERE bu.username = ?
    ");

    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {

        // Block deactivated accounts, even with a correct password.
        if (isset($user['status']) && $user['status'] === 'inactive') {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'Your account has been deactivated. Contact your administrator.'
            ]);
            exit;
        }

        // Remove password hash before sending to client
        unset($user['password']);

        // Legacy session keys (kept so existing frontend code that
        // reads these does not break).
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['user_type'] = $user['user_type'];
        $_SESSION['username']  = $user['username'];
        $_SESSION['branch_id'] = $user['branch_id'];

        // New RBAC session: role + full permission grid.
        rbac_bootstrap_session($pdo, $user);

        $user['permissions'] = $_SESSION['permissions'];
        $user['is_super']    = $_SESSION['is_super'];

        echo json_encode([
            'success'    => true,
            'message'    => 'Login successful',
            'user'       => $user,
            'csrf_token' => rbac_csrf_token(),
        ]);
    } else {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid username or password'
        ]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
}
