<?php
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('employees', 'view');

ob_start();

require_once '../../config/database.php';

header("Content-Type: application/json");

$database = new Database();
$pdo = $database->getConnection();

try {
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';

    $sql = "SELECT id, full_name, designation, contact, shift, photo, balance
            FROM employees";
    $params = [];

    if ($search !== '') {
        $sql .= " WHERE full_name LIKE :search OR designation LIKE :search OR contact LIKE :search";
        $params[':search'] = "%{$search}%";
    }

    $sql .= " ORDER BY full_name ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    ob_clean();
    echo json_encode(["success" => true, "data" => $rows]);
} catch (Exception $e) {
    ob_clean();
    http_response_code(400);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
