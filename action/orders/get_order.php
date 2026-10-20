<?php
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('sales', 'view');


error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');
require_once '../../config/database.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Order ID is required'
    ]);
    exit;
}

$orderId = filter_var($_GET['id'], FILTER_VALIDATE_INT);
if ($orderId === false || $orderId <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid order ID'
    ]);
    exit;
}

try {
    // Create database connection using your class
    $database = new Database();
    $db = $database->getConnection();

    // Main order query with all joins
    $query = "
        SELECT 
            -- Order details
            o.id AS order_id,
            o.order_no,
            o.order_date,
            o.delivery_date,
            o.shift AS order_shift,
            o.subtotal,
            o.discount,
            o.total AS grand_total,
            o.status AS order_status,
            o.remarks AS order_remarks,
            o.created_at,
            o.updated_at,
            
            -- Customer details
            c.id AS customer_id,
            c.customer_code,
            c.name AS customer_name,
            c.phone AS customer_phone,
            c.email AS customer_email,
            c.address AS customer_address,
            c.opening_balance,
            c.current_balance,
            
            -- City details
            ci.city_name,
            
            -- Area details
            a.area_name,
            
            -- Employee details
            e.id AS employee_id,
            e.full_name AS employee_name,
            e.shift AS employee_shift
            
        FROM orders o
        
        -- Join customer
        LEFT JOIN customers c ON o.customer_id = c.id
        
        -- Join city
        LEFT JOIN cities ci ON c.city_id = ci.id
        
        -- Join area
        LEFT JOIN areas a ON c.area_id = a.id
        
        -- Join employee
        LEFT JOIN employees e ON o.employee_id = e.id
        
        WHERE o.id = :order_id
    ";

    $stmt = $db->prepare($query);
    $stmt->execute([':order_id' => $orderId]);
    $orderData = $stmt->fetch(PDO::FETCH_ASSOC);

    // Check if order exists
    if (!$orderData) {
        echo json_encode([
            'success' => false,
            'message' => 'Order not found'
        ]);
        exit;
    }

   
    $itemsQuery = "
        SELECT 
            oi.id AS item_id,
            oi.quantity,
            oi.unit_price,
            oi.total AS item_total,
            p.id AS product_id,
            p.product_name,
            p.product_code,
            p.sale_price
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = :order_id
        ORDER BY oi.id ASC
    ";

    $itemsStmt = $db->prepare($itemsQuery);
    $itemsStmt->execute([':order_id' => $orderId]);
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

   
    // Build complete address
    $completeAddress = trim(
        ($orderData['customer_address'] ?? '') . ', ' . 
        ($orderData['area_name'] ?? '') . ', ' . 
        ($orderData['city_name'] ?? '')
    );
    $completeAddress = trim($completeAddress, ', ');

    // Customer information
   $customer = [
    'customer_id' => intval($orderData['customer_id'] ?? 0),
    'customer_code' => $orderData['customer_code'] ?? 'N/A',
    'name' => $orderData['customer_name'] ?? 'N/A',
    'phone' => $orderData['customer_phone'] ?? 'N/A',
    'email' => $orderData['customer_email'] ?? 'N/A',
    'city_name' => $orderData['city_name'] ?? 'N/A',
    'area_name' => $orderData['area_name'] ?? 'N/A',
    'complete_address' => $completeAddress ?: 'N/A',
    'opening_balance' => floatval($orderData['opening_balance'] ?? 0),
    'current_balance' => floatval($orderData['current_balance'] ?? 0)
];
    // Order information
    $order = [
        'order_id' => intval($orderData['order_id']),
        'order_no' => $orderData['order_no'] ?? 'N/A',
        'order_date' => $orderData['order_date'] ?? 'N/A',
        'delivery_date' => $orderData['delivery_date'] ?? 'N/A',
        'employee_name' => $orderData['employee_name'] ?? 'N/A',
        'employee_shift' => $orderData['employee_shift'] ?? 'N/A',
        'shift' => $orderData['order_shift'] ?? 'N/A',
        'status' => $orderData['order_status'] ?? 'pending',
        'remarks' => $orderData['order_remarks'] ?? 'N/A',
        'subtotal' => floatval($orderData['subtotal'] ?? 0),
        'discount' => floatval($orderData['discount'] ?? 0),
        'grand_total' => floatval($orderData['grand_total'] ?? 0)
    ];

    // Order items
    $orderItems = [];
    foreach ($items as $item) {
        $orderItems[] = [
            'product_name' => $item['product_name'] ?? 'Unknown Product',
            'product_code' => $item['product_code'] ?? 'N/A',
            'quantity' => intval($item['quantity'] ?? 0),
            'unit_price' => floatval($item['unit_price'] ?? 0),
            'total' => floatval($item['item_total'] ?? 0)
        ];
    }

   
    echo json_encode([
        'success' => true,
        'customer' => $customer,
        'order' => $order,
        'items' => $orderItems
    ]);

} catch (PDOException $e) {
    // Database error
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    // General error
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>