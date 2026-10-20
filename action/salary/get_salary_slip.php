<?php
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('employees', 'view');

ob_start();

require_once '../../config/database.php';

header("Content-Type: application/json");

$database = new Database();
$pdo = $database->getConnection();

try {
    $id = (int)($_GET['id'] ?? 0);

    if ($id <= 0) {
        throw new Exception("id is required");
    }
    $sql = "SELECT s.*, e.full_name, e.designation, e.contact, e.cnic, e.shift
            FROM salaries s
            JOIN employees e ON e.id = s.employee_id
            WHERE s.id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();

    if (!$row) throw new Exception("Salary slip not found");

    ob_clean();
    echo json_encode(["success" => true, "data" => $row]);
} catch (Exception $e) {
    ob_clean();
    http_response_code(400);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
