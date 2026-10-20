<?php
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('sales', rbac_action_for_method());

require_once '../../config/database.php';
header('Content-Type: application/json');

try {
    $database = new Database();
    $db = $database->getConnection();
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'DELETE') {

        $invoice_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($invoice_id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invoice ID is required.']);
            exit;
        }

        $check = $db->prepare("SELECT id FROM invoices WHERE id = ?");
        $check->execute([$invoice_id]);
        if ($check->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Invoice not found.']);
            exit;
        }

        // NOTE: order_items ka relation `orders` table se hai, `invoices` se nahi —
        // is liye invoice delete karne par order/order_items ko chhedne ki zaroorat nahi.
        // Agar aap chahte ho ke invoice delete hone par uska poora order + order_items
        // bhi delete ho jaye, to yahan add karwa dena (order_id already invoice row mein maujood hai).

        $delete = $db->prepare("DELETE FROM invoices WHERE id = ?");
        if ($delete->execute([$invoice_id])) {
            echo json_encode(['success' => true, 'message' => 'Invoice deleted.', 'deleted_id' => $invoice_id]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to delete.']);
        }
        exit;
    }

    if ($method === 'GET') {

        $customer_name = isset($_GET['customer_name']) ? trim($_GET['customer_name']) : '';
        $invoice_no    = isset($_GET['invoice_no']) ? trim($_GET['invoice_no']) : '';
        $date_from     = isset($_GET['date_from']) ? $_GET['date_from'] : null;
        $date_to       = isset($_GET['date_to']) ? $_GET['date_to'] : null;
        $status        = isset($_GET['status']) ? $_GET['status'] : '';

        $sql = "
            SELECT 
                i.*,
                c.name AS customer_name,
                c.phone,
                c.address
            FROM invoices i
            LEFT JOIN customers c ON c.id = i.customer_id
            WHERE 1=1
        ";
        $params = [];

        if (!empty($customer_name)) {
            $sql .= " AND c.name LIKE ?";
            $params[] = '%' . $customer_name . '%';
        }

        // invoice_no OR id se search allow karta hai (aapke search box mein "53" jaisa numeric id/invoice_no aata hai)
        if (!empty($invoice_no)) {
            $sql .= " AND (i.invoice_no LIKE ? OR i.id = ?)";
            $params[] = '%' . $invoice_no . '%';
            $params[] = (int)$invoice_no;
        }

        if (!empty($date_from)) {
            $sql .= " AND i.invoice_date >= ?";
            $params[] = $date_from;
        }

        if (!empty($date_to)) {
            $sql .= " AND i.invoice_date <= ?";
            $params[] = $date_to;
        }

        if (!empty($status)) {
            $sql .= " AND i.status = ?";
            $params[] = $status;
        }

        $sql .= " ORDER BY i.invoice_date DESC, i.id DESC";

        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
        $sql .= " LIMIT ?";
        $params[] = $limit;

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // har invoice ke products bhi attach karo, taake frontend table dikha sake
        // invoices.order_id -> order_items.order_id -> order_items.product_id -> products.product_name
        if (!empty($invoices)) {
            try {
                $itemStmt = $db->prepare("
                    SELECT 
                        p.product_name,
                        oi.quantity AS qty,
                        oi.unit_price AS price,
                        oi.total
                    FROM order_items oi
                    LEFT JOIN products p ON p.id = oi.product_id
                    WHERE oi.order_id = ?
                ");
                foreach ($invoices as &$inv) {
                    $itemStmt->execute([$inv['order_id']]);
                    $inv['items'] = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
                }
                unset($inv);
            } catch (\Throwable $e) {
                // kisi wajah se items fetch fail ho to poora API crash na ho,
                // sirf empty items ke sath return karo aur log mein reason likh do
                foreach ($invoices as &$inv) {
                    $inv['items'] = [];
                }
                unset($inv);
                error_log('Invoice items fetch failed: ' . $e->getMessage());
            }
        }

        echo json_encode([
            'success' => true,
            'total' => count($invoices),
            'data' => $invoices
        ]);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>