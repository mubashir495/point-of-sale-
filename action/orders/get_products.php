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
    
    $category_id = isset($_GET['category_id']) ? $_GET['category_id'] : null;
    
    $query = "SELECT p.id, p.product_name, p.product_code, p.category, p.purchase_price, p.sale_price, p.current_stock, p.status 
              FROM products p
              LEFT JOIN categories c ON p.category = c.name
              WHERE p.status = 'Active'";
    $params = [];

    if (rbac_is_branch_scoped()) {
        $branchId = rbac_current_branch_id();
        if (!$branchId) {
            echo json_encode([]);
            exit;
        }
        $query .= " AND p.branch_id = :branch_id";
        $params[':branch_id'] = $branchId;
    }
    
    if ($category_id) {
        $query .= " AND c.id = :category_id";
        $params[':category_id'] = $category_id;
    }
    
    $query .= " ORDER BY p.product_name ASC";
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($products);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>