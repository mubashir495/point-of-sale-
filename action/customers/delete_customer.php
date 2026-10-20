<?php
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('customers', 'delete');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $customer_id = isset($_GET['id']) ? $_GET['id'] : null;
    
    if (!$customer_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Customer ID is required']);
        exit;
    }
    
    // Check if customer has orders
    $checkQuery = "SELECT COUNT(*) as order_count FROM orders WHERE customer_id = :customer_id";
    $stmt = $db->prepare($checkQuery);
    $stmt->execute([':customer_id' => $customer_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['order_count'] > 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'message' => 'Cannot delete customer with existing orders. Customer has ' . $result['order_count'] . ' order(s).'
        ]);
        exit;
    }
    
    $query = "DELETE FROM customers WHERE id = :customer_id";
    $stmt = $db->prepare($query);
    $stmt->execute([':customer_id' => $customer_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Customer deleted successfully'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>