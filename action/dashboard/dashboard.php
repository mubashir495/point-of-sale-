<?php
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('dashboard', 'view');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    $branchScoped = rbac_is_branch_scoped();
    $branchId = rbac_current_branch_id();
    if ($branchScoped && !$branchId) {
        echo json_encode(["success" => false, "message" => "Your account isn't linked to a branch yet - contact an admin."]);
        exit;
    }
    $prodBranchWhere = $branchScoped ? "WHERE branch_id = :branch_id" : "";
    $orderBranchAnd  = $branchScoped ? "AND branch_id = :branch_id" : "";
    $custBranchWhere = $branchScoped ? "WHERE branch_id = :branch_id" : "";
    $custBranchAnd   = $branchScoped ? "AND branch_id = :branch_id" : "";

    // 1. Stock
    $stmt = $db->prepare("
        SELECT
            SUM(current_stock * purchase_price) AS stock_cost,
            SUM(current_stock * sale_price) AS stock_price,
            SUM(current_stock) AS warehouse_stock
        FROM products
        $prodBranchWhere
    ");
    if ($branchScoped) $stmt->execute([':branch_id' => $branchId]); else $stmt->execute();
    $stock = $stmt->fetch(PDO::FETCH_ASSOC);

    // 2. Sales (Completed Orders)
    $stmt = $db->prepare("
        SELECT
            COUNT(*) AS total_orders,
            IFNULL(SUM(total),0) AS total_sales
        FROM orders
        WHERE status='Completed' $orderBranchAnd
    ");
    if ($branchScoped) $stmt->execute([':branch_id' => $branchId]); else $stmt->execute();
    $sales = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $db->prepare("
        SELECT IFNULL(SUM(total),0) AS today_sales
        FROM orders
        WHERE status='Completed'
        AND DATE(order_date)=CURDATE() $orderBranchAnd
    ");
    if ($branchScoped) $stmt->execute([':branch_id' => $branchId]); else $stmt->execute();
    $todaySales = $stmt->fetch(PDO::FETCH_ASSOC);

    // 2b. Purchases (not branch-scoped yet - Purchase/Raw Materials
    // weren't part of this pass; shown as a combined total for now)
    $purchases = $db->query("
        SELECT
            COUNT(*) AS total_purchases,
            IFNULL(SUM(total_amount),0) AS total_purchase_amount
        FROM purchase_master
    ")->fetch(PDO::FETCH_ASSOC);

    $todayPurchases = $db->query("
        SELECT IFNULL(SUM(total_amount),0) AS today_purchase_amount
        FROM purchase_master
        WHERE DATE(purchase_date) = CURDATE()
    ")->fetch(PDO::FETCH_ASSOC);

    // 3. Receivable
    $stmt = $db->prepare("
        SELECT IFNULL(SUM(current_balance),0) AS total_receivable
        FROM customers
        WHERE current_balance > 0 $custBranchAnd
    ");
    if ($branchScoped) $stmt->execute([':branch_id' => $branchId]); else $stmt->execute();
    $receivable = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $db->prepare("
        SELECT IFNULL(SUM(current_balance),0) AS today_receivable
        FROM customers
        WHERE DATE(created_at)=CURDATE() $custBranchAnd
    ");
    if ($branchScoped) $stmt->execute([':branch_id' => $branchId]); else $stmt->execute();
    $todayReceivable = $stmt->fetch(PDO::FETCH_ASSOC);

    // 4. Payable (Suppliers aren't branch-scoped yet)
    $payable = $db->query("
        SELECT IFNULL(SUM(balance),0) AS total_payable
        FROM employees
        WHERE designation='Supplier'
    ")->fetch(PDO::FETCH_ASSOC);

    // 5. Parties
    $stmt = $db->prepare("
        SELECT COUNT(*) total_customers
        FROM customers
        $custBranchWhere
    ");
    if ($branchScoped) $stmt->execute([':branch_id' => $branchId]); else $stmt->execute();
    $customers = $stmt->fetch(PDO::FETCH_ASSOC);

    $suppliers = $db->query("
        SELECT COUNT(*) total_suppliers
        FROM employees
        WHERE designation='Supplier'
    ")->fetch(PDO::FETCH_ASSOC);

    // 6. Delivery Summary
    $stmt = $db->prepare("
        SELECT
            -- Overall Delivered
            COUNT(CASE WHEN status = 'Completed' THEN 1 END) AS overall_delivered_orders,
            IFNULL(SUM(CASE WHEN status = 'Completed' THEN total END), 0) AS overall_delivered_amount,
            -- Today Delivered
            COUNT(CASE WHEN status = 'Completed' AND DATE(order_date) = CURDATE() THEN 1 END) AS today_delivered_orders,
            IFNULL(SUM(CASE WHEN status = 'Completed' AND DATE(order_date) = CURDATE() THEN total END), 0) AS today_delivered_amount,
            -- Overall Returned
            COUNT(CASE WHEN status = 'Returned' THEN 1 END) AS overall_returned_orders,
            IFNULL(SUM(CASE WHEN status = 'Returned' THEN total END), 0) AS overall_returned_amount,
            -- Today Returned
            COUNT(CASE WHEN status = 'Returned' AND DATE(order_date) = CURDATE() THEN 1 END) AS today_returned_orders,
            IFNULL(SUM(CASE WHEN status = 'Returned' AND DATE(order_date) = CURDATE() THEN total END), 0) AS today_returned_amount
        FROM orders
        " . ($branchScoped ? "WHERE branch_id = :branch_id" : "") . "
    ");
    if ($branchScoped) $stmt->execute([':branch_id' => $branchId]); else $stmt->execute();
    $deliverySummary = $stmt->fetch(PDO::FETCH_ASSOC);

    // 7. Recent Orders (Last 10)
    $stmt = $db->prepare("
        SELECT 
            o.id,
            o.order_no,
            o.order_date,
            o.delivery_date,
            o.shift,
            o.subtotal,
            o.discount,
            o.total,
            o.status,
            c.name as customer_name,
            e.full_name as employee_name
        FROM orders o
        LEFT JOIN customers c ON o.customer_id = c.id
        LEFT JOIN employees e ON o.employee_id = e.id
        " . ($branchScoped ? "WHERE o.branch_id = :branch_id" : "") . "
        ORDER BY o.order_date DESC, o.id DESC
        LIMIT 10
    ");
    if ($branchScoped) $stmt->execute([':branch_id' => $branchId]); else $stmt->execute();
    $recentOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 8. Order Status Summary
    $stmt = $db->prepare("
        SELECT 
            status,
            COUNT(*) as count,
            IFNULL(SUM(total), 0) as total_amount
        FROM orders
        " . ($branchScoped ? "WHERE branch_id = :branch_id" : "") . "
        GROUP BY status
    ");
    if ($branchScoped) $stmt->execute([':branch_id' => $branchId]); else $stmt->execute();
    $statusSummary = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Final Response
    echo json_encode([
        "success" => true,
        "data" => [
            // Dashboard Cards
            "cards" => [
                "stock" => [
                    "cost" => (float)($stock['stock_cost'] ?? 0),
                    "price" => (float)($stock['stock_price'] ?? 0),
                    "warehouse_stock" => (int)($stock['warehouse_stock'] ?? 0)
                ],
                "sales" => [
                    "overall" => (float)($sales['total_sales'] ?? 0),
                    "today" => (float)($todaySales['today_sales'] ?? 0),
                    "orders" => (int)($sales['total_orders'] ?? 0)
                ],
                "purchase" => [
                    "overall" => (float)($purchases['total_purchase_amount'] ?? 0),
                    "today" => (float)($todayPurchases['today_purchase_amount'] ?? 0),
                    "count" => (int)($purchases['total_purchases'] ?? 0)
                ],
                "receivable" => [
                    "overall" => (float)($receivable['total_receivable'] ?? 0),
                    "today" => (float)($todayReceivable['today_receivable'] ?? 0)
                ],
                "payable" => [
                    "overall" => (float)($payable['total_payable'] ?? 0),
                    "today" => 0
                ],
                "parties" => [
                    "customers" => (int)($customers['total_customers'] ?? 0),
                    "suppliers" => (int)($suppliers['total_suppliers'] ?? 0)
                ]
            ],
            
            // Delivery Summary
            "delivery_summary" => [
                "delivered" => [
                    "today" => [
                        "orders" => (int)($deliverySummary['today_delivered_orders'] ?? 0),
                        "amount" => (float)($deliverySummary['today_delivered_amount'] ?? 0)
                    ],
                    "overall" => [
                        "orders" => (int)($deliverySummary['overall_delivered_orders'] ?? 0),
                        "amount" => (float)($deliverySummary['overall_delivered_amount'] ?? 0)
                    ]
                ],
                "returned" => [
                    "today" => [
                        "orders" => (int)($deliverySummary['today_returned_orders'] ?? 0),
                        "amount" => (float)($deliverySummary['today_returned_amount'] ?? 0)
                    ],
                    "overall" => [
                        "orders" => (int)($deliverySummary['overall_returned_orders'] ?? 0),
                        "amount" => (float)($deliverySummary['overall_returned_amount'] ?? 0)
                    ]
                ]
            ],
            
            // Order Status Summary
            "order_status" => $statusSummary,
            
            // Recent Orders
            "recent_orders" => $recentOrders
        ]
    ]);

} catch(PDOException $e) {
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
} catch(Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
?>