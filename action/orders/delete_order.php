<?php
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('sales', 'delete');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $order_id = isset($_GET['id']) ? $_GET['id'] : null;
    
    if (!$order_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Order ID is required']);
        exit;
    }
    
    $db->beginTransaction();
    
    try {
        // Delete order items first (cascade will handle if foreign key has ON DELETE CASCADE)
        $deleteItems = "DELETE FROM order_items WHERE order_id = :order_id";
        $stmt = $db->prepare($deleteItems);
        $stmt->execute([':order_id' => $order_id]);
        
        // Delete order
        $deleteOrder = "DELETE FROM orders WHERE id = :order_id";
        $stmt = $db->prepare($deleteOrder);
        $stmt->execute([':order_id' => $order_id]);
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Order deleted successfully'
        ]);
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>