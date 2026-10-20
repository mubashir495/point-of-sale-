<?php
/**
 * action/orders/get_sale_for_return.php
 * ------------------------------------------------------------------
 * Looks up a Counter Sale invoice by invoice/order number so the
 * Sale (POS) page's "Return" modal can show its items and let the
 * user pick what is being returned. Each item's "returnable" qty is
 * its original quantity minus whatever has already been returned
 * against it, so the same item can't be over-returned.
 * ------------------------------------------------------------------
 */
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('sales', 'view');

require_once '../../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    $invoiceNo = trim($_GET['invoice_no'] ?? '');
    if ($invoiceNo === '') {
        throw new Exception('invoice_no is required');
    }

    $database = new Database();
    $db = $database->getConnection();

    $stmt = $db->prepare("SELECT o.*, c.name AS customer_name
                           FROM orders o
                           LEFT JOIN customers c ON o.customer_id = c.id
                           WHERE o.order_no = :order_no
                           LIMIT 1");
    $stmt->execute([':order_no' => $invoiceNo]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Invoice not found']);
        exit;
    }

    if ($order['status'] !== 'Counter') {
        echo json_encode(['success' => false, 'message' => 'Only Counter Sale invoices can be returned from this screen']);
        exit;
    }

    $itemsStmt = $db->prepare("
        SELECT
            oi.id AS order_item_id,
            oi.product_id,
            oi.quantity,
            oi.unit_price,
            oi.total,
            p.product_name,
            COALESCE((
                SELECT SUM(sri.quantity)
                FROM sale_return_items sri
                WHERE sri.order_item_id = oi.id
            ), 0) AS already_returned
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = :order_id
        ORDER BY oi.id ASC
    ");
    $itemsStmt->execute([':order_id' => $order['id']]);
    $rows = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

    $items = [];
    foreach ($rows as $row) {
        $ordered = (float)$row['quantity'];
        $returned = (float)$row['already_returned'];
        $remaining = max(0, $ordered - $returned);
        $items[] = [
            'order_item_id'   => (int)$row['order_item_id'],
            'product_id'      => (int)$row['product_id'],
            'product_name'    => $row['product_name'] ?? 'Unknown Product',
            'quantity'        => $ordered,
            'unit_price'      => (float)$row['unit_price'],
            'already_returned'=> $returned,
            'returnable_qty'  => $remaining,
        ];
    }

    echo json_encode([
        'success' => true,
        'order' => [
            'order_id'      => (int)$order['id'],
            'invoice_no'    => $order['order_no'],
            'order_date'    => $order['order_date'],
            'customer_name' => $order['customer_name'] ?? 'Walk-in',
            'total'         => (float)$order['total'],
        ],
        'items' => $items,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
