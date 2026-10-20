<?php
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('sales', 'view');

require_once '../../config/database.php';
header('Content-Type: application/json');

try {
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;

    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'Quotation ID is required']);
        exit;
    }

    $database = new Database();
    $db = $database->getConnection();

    // Get quotation details
    $sql = "SELECT 
                q.id,
                q.quotation_no,
                q.customer_id,
                q.customer_name,
                q.employee_id,
                q.shift,
                q.order_date,
                q.subtotal,
                q.discount,
                q.total,
                q.paid,
                q.remarks,
                q.status,
                q.items,
                q.created_at,
                e.full_name AS employee_name
            FROM quotations q
            LEFT JOIN employees e ON e.id = q.employee_id
            WHERE q.id = :id";

    $stmt = $db->prepare($sql);
    $stmt->bindParam(':id', $id);
    $stmt->execute();

    $quotation = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$quotation) {
        echo json_encode(['success' => false, 'message' => 'Quotation not found']);
        exit;
    }

    // Decode items JSON
    $quotation['items'] = json_decode($quotation['items'], true);

    echo json_encode([
        'success' => true,
        'data' => $quotation
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}