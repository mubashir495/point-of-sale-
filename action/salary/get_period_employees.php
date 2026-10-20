<?php
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('employees', 'view');

ob_start();

require_once '../../config/database.php';

header("Content-Type: application/json");

$database = new Database();
$pdo = $database->getConnection();

try {
    $period = isset($_GET['period']) ? trim($_GET['period']) : date('Y-m');

    if (!preg_match('/^\d{4}-\d{2}$/', $period)) {
        throw new Exception("Invalid period format, expected YYYY-MM");
    }


    $sql = "SELECT e.id, e.full_name, e.designation, e.shift, e.salary AS basic
            FROM employees e
            LEFT JOIN salaries s ON s.employee_id = e.id AND s.period = :period
            WHERE s.id IS NULL
            ORDER BY e.full_name ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':period' => $period]);
    $rows = $stmt->fetchAll();

    ob_clean();
    echo json_encode(["success" => true, "period" => $period, "data" => $rows]);
} catch (Exception $e) {
    ob_clean();
    http_response_code(400);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
