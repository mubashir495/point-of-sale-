<?php
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('sales', 'view');

require_once '../../config/database.php';
header('Content-Type: application/json');

try {
    $database = new Database();
    $db = $database->getConnection();

    // Optional: filter by customer_id
    $customerId = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;

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
                (q.total - q.paid) AS remaining,
                q.remarks,
                q.status,
                q.created_at,
                e.full_name AS employee_name
            FROM quotations q
            LEFT JOIN employees e ON e.id = q.employee_id
            WHERE 1=1";

    if ($customerId > 0) {
        $sql .= " AND q.customer_id = :customer_id";
    }

    $sql .= " ORDER BY q.id DESC";

    $stmt = $db->prepare($sql);

    if ($customerId > 0) {
        $stmt->bindParam(':customer_id', $customerId);
    }

    $stmt->execute();
    $quotations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $quotations
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