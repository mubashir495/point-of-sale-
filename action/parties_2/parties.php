<?php
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('suppliers', 'view');


require '../../config/database.php';

// Create database instance and get PDO connection
$database = new Database();
$pdo = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respond(['success' => false, 'message' => 'Method not allowed'], 405);
}

$branchId = $_GET['branch_id'] ?? null;
$search   = trim($_GET['search'] ?? '');

$sql = "SELECT p.id, p.name, p.contact, p.address, p.area_id, a.area_name
        FROM parties_2 p
        LEFT JOIN areas a ON a.id = p.area_id
        WHERE 1=1";
$params = [];

if ($branchId) {
    $sql .= " AND p.branch_id = ?";
    $params[] = $branchId;
}
if ($search !== '') {
    $sql .= " AND (p.name LIKE ? OR a.area_name LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

$sql .= " ORDER BY p.name ASC";

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