<?php
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('sales', 'view');

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

require_once "../../config/database.php";

try {

    $database = new Database();
    $db = $database->getConnection();

    if (!$db) {
        throw new Exception("Database connection failed.");
    }

    // =========================
    // Overall Orders
    // =========================
    $stmt = $db->prepare("
        SELECT
            COUNT(*) AS total_orders,
            COALESCE(SUM(total), 0) AS total_sale
        FROM orders
        WHERE status = 'Complete'
    ");
    $stmt->execute();
    $overall = $stmt->fetch(PDO::FETCH_ASSOC);

    // =========================
    // Overall Products Sold
    // =========================
    $stmt = $db->prepare("
        SELECT
            COALESCE(SUM(oi.quantity), 0) AS total_products
        FROM order_items oi
        INNER JOIN orders o
            ON oi.order_id = o.id
        WHERE o.status = 'Complete'
    ");
    $stmt->execute();
    $overallProducts = $stmt->fetch(PDO::FETCH_ASSOC);

    // =========================
    // Today's Orders
    // =========================
    $stmt = $db->prepare("
        SELECT
            COUNT(*) AS total_orders,
            COALESCE(SUM(total), 0) AS total_sale
        FROM orders
        WHERE status = 'Complete'
          AND DATE(order_date) = CURDATE()
    ");
    $stmt->execute();
    $today = $stmt->fetch(PDO::FETCH_ASSOC);

    // =========================
    // Today's Products Sold
    // =========================
    $stmt = $db->prepare("
        SELECT
            COALESCE(SUM(oi.quantity), 0) AS total_products
        FROM order_items oi
        INNER JOIN orders o
            ON oi.order_id = o.id
        WHERE o.status = 'Complete'
          AND DATE(o.order_date) = CURDATE()
    ");
    $stmt->execute();
    $todayProducts = $stmt->fetch(PDO::FETCH_ASSOC);

    // =========================
    // Response
    // =========================
    echo json_encode([
        "status" => true,
        "orders" => [
            "today" => [
                "total_orders"   => (int)$today["total_orders"],
                "total_sale"     => (float)$today["total_sale"],
                "total_products" => (int)$todayProducts["total_products"]
            ],
            "overall" => [
                "total_orders"   => (int)$overall["total_orders"],
                "total_sale"     => (float)$overall["total_sale"],
                "total_products" => (int)$overallProducts["total_products"]
            ]
        ]
    ], JSON_PRETTY_PRINT);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        "status" => false,
        "message" => "Database Error",
        "error" => $e->getMessage()
    ]);

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        "status" => false,
        "message" => $e->getMessage()
    ]);
}