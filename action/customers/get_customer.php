    <?php
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('customers', 'view');

    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET');
    header('Access-Control-Allow-Headers: Content-Type');

    require_once '../../config/database.php';

    try {
        $database = new Database();
        $db = $database->getConnection();
        
        $customer_id = isset($_GET['id']) ? $_GET['id'] : null;
        
        if (!$customer_id) {
            http_response_code(400);
            echo json_encode(['error' => 'Customer ID is required']);
            exit;
        }

        // Branch-scoping: block direct access to a customer outside the
        // current user's assigned list (see rbac_scope_customer_ids()).
        $scopedIds = rbac_scope_customer_ids();
        if ($scopedIds !== null && !in_array((int) $customer_id, $scopedIds, true)) {
            http_response_code(403);
            echo json_encode(['error' => '403 Access Denied: this customer is not assigned to you.']);
            exit;
        }
        
        $query = "SELECT c.*,
                 a.area_name,
                 ci.city_name
          FROM customers c
          LEFT JOIN areas a ON c.area_id = a.id
          LEFT JOIN cities ci ON c.city_id = ci.id
          WHERE c.id = :customer_id";
        
        $stmt = $db->prepare($query);
        $stmt->execute([':customer_id' => $customer_id]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$customer) {
            http_response_code(404);
            echo json_encode(['error' => 'Customer not found']);
            exit;
        }
        
        echo json_encode([
            'success' => true,
            'customer' => $customer
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    ?>