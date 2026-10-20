<?php
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('sales', 'view');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $status = isset($_GET['status']) ? $_GET['status'] : '';
    $customer = isset($_GET['customer']) ? $_GET['customer'] : '';
    $dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
    $dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';
    
    $query = "SELECT o.*, 
                     c.name as customer_name,
                     c.customer_code as customer_code,
                     c.phone as customer_phone,
                     c.address as customer_address,
                     c.current_balance as current_balance,
                     c.opening_balance as opening_balance,
                     a.area_name as area_name,
                     e.full_name as employee_name,
                     GROUP_CONCAT(DISTINCT p.product_name SEPARATOR ', ') as product_names,
                     GROUP_CONCAT(DISTINCT p.sale_price SEPARATOR ', ') as product_rates,
                     SUM(oi.quantity) as total_qty
              FROM orders o
              LEFT JOIN customers c ON o.customer_id = c.id
              LEFT JOIN areas a ON c.area_id = a.id
              LEFT JOIN employees e ON o.employee_id = e.id
              LEFT JOIN order_items oi ON oi.order_id = o.id
              LEFT JOIN products p ON oi.product_id = p.id
              WHERE 1=1";
    
    $params = [];

    if (rbac_is_branch_scoped()) {
        $branchId = rbac_current_branch_id();
        if (!$branchId) {
            echo json_encode([]);
            exit;
        }
        $query .= " AND o.branch_id = :branch_id";
        $params[':branch_id'] = $branchId;
    }
    
    if (!empty($search)) {
        $query .= " AND (o.order_no LIKE :search OR c.name LIKE :search)";
        $params[':search'] = "%$search%";
    }
    
    if (!empty($status)) {
        $query .= " AND o.status = :status";
        $params[':status'] = $status;
    }
    
    if (!empty($customer)) {
        $query .= " AND o.customer_id = :customer";
        $params[':customer'] = $customer;
    }
    
    if (!empty($dateFrom)) {
        $query .= " AND o.order_date >= :date_from";
        $params[':date_from'] = $dateFrom;
    }
    
    if (!empty($dateTo)) {
        $query .= " AND o.order_date <= :date_to";
        $params[':date_to'] = $dateTo;
    }
    
    $query .= " GROUP BY o.id ORDER BY o.id DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($orders);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>