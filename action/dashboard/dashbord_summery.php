<?php
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('dashboard', 'view');

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
require_once "../../config/database.php";

try {
    $database = new Database();
    $db = $database->getConnection();
    if (!$db) throw new Exception("Database connection failed.");

    // Employees
    $stmt = $db->query("SELECT COUNT(*) AS cnt FROM employees");
    $employeeCount = (int)$stmt->fetch(PDO::FETCH_ASSOC)['cnt'];

    // Products
    $stmt = $db->query("SELECT COUNT(*) AS cnt FROM products");
    $productCount = (int)$stmt->fetch(PDO::FETCH_ASSOC)['cnt'];

    // Orders - today
    $stmt = $db->query("SELECT COUNT(DISTINCT order_no) AS cnt, COALESCE(SUM(total),0) AS sale
                         FROM orders WHERE status='Complete' AND DATE(order_date)=CURDATE()");
    $ordersToday = $stmt->fetch(PDO::FETCH_ASSOC);

    
    $stmt = $db->query("SELECT COUNT(DISTINCT order_no) AS cnt, COALESCE(SUM(total),0) AS sale
                         FROM orders WHERE status='Complete'");
    $ordersOverall = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        "status" => true,
        "employee_count" => $employeeCount,
        "product_count" => $productCount,
        "orders" => [
            "today"   => ["count" => (int)$ordersToday['cnt'],   "sale" => (float)$ordersToday['sale']],
            "overall" => ["count" => (int)$ordersOverall['cnt'], "sale" => (float)$ordersOverall['sale']]
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => false, "message" => $e->getMessage()]);
}