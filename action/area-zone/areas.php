<?php
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('users', 'view');


require '../../config/database.php';

// Create database instance and get PDO connection
$database = new Database();
$pdo = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respond(['success' => false, 'message' => 'Method not allowed'], 405);
}

$cityId = $_GET['city_id'] ?? null;
$search = trim($_GET['search'] ?? '');

$sql    = "SELECT id, city_id, area_name, created_at FROM areas WHERE 1=1";
$params = [];

if ($cityId) {
    $sql .= " AND city_id = ?";
    $params[] = $cityId;
}
if ($search !== '') {
    $sql .= " AND area_name LIKE ?";
    $params[] = "%{$search}%";
}

$sql .= " ORDER BY area_name ASC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    respond(['success' => true, 'data' => $stmt->fetchAll()]);
} catch (PDOException $e) {
    respond(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
}

function respond($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}