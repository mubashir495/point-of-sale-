<?php
/**
 * action/orders/update_status.php
 * ------------------------------------------------------------------
 * Updates an order's status. When the new status is "Completed":
 *   - stock is deducted (existing behaviour, unchanged)
 *   - the order's invoice is found-or-created and synced
 *   - if the client confirms payment was received (payment_received),
 *     that amount is applied to the invoice + customer ledger
 *     atomically, in the same transaction as the status change
 *     (spec: "Complete Order Par Payment Confirmation")
 *
 * Accepts both "Complete" and "Completed" from the client (the
 * frontend historically sent "Complete") and always stores the
 * canonical "Completed" in the database, so status comparisons stay
 * consistent everywhere else in the app.
 * ------------------------------------------------------------------
 */
require_once __DIR__ . '/../../config/rbac.php';
$user = rbac_require_permission('sales', 'edit');
rbac_verify_csrf();

require_once '../../config/database.php';
require_once '../../config/accounting_helpers.php';

$database = new Database();
$db = $database->getConnection();
header('Content-Type: application/json');

// The client-facing status value is normalized to this canonical set
// before touching the database, so "Complete" (frontend) and
// "Completed" (some older rows/back-end checks) are always treated
// as the exact same state.
function normalize_order_status(string $status): string
{
    return ($status === 'Complete') ? 'Completed' : $status;
}

try {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $orderId = (int)($input['order_id'] ?? 0);
    $rawStatus = trim($input['status'] ?? '');
    if ($orderId <= 0 || $rawStatus === '') throw new Exception('order_id and status required');

    $newStatus = normalize_order_status($rawStatus);

    $validStatuses = ['Pending','Processing','Delivered','Completed','Counter','Cancelled'];
    if (!in_array($newStatus, $validStatuses)) throw new Exception('Invalid status');

    $stmt = $db->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$order) throw new Exception('Order not found');

    $currentStatus = normalize_order_status($order['status']);

    if ($currentStatus === $newStatus) {
        echo json_encode(['success' => true, 'message' => 'Already in this status']);
        exit;
    }

    $db->beginTransaction();

    // Delivered: create customer receivable only once
    if ($newStatus === 'Delivered' && $currentStatus !== 'Delivered') {
        if (!customerLedgerExists($db, 'delivery', $orderId) && $order['customer_id']) {
            updateCustomerBalanceAndLedger(
                $db,
                $order['customer_id'],
                $order['total'], // Debit
                0,
                'Order Delivered ' . $order['order_no'],
                'delivery',
                $orderId
            );
        }
    }

    // Completed: reduce stock, sync the invoice, and apply the
    // payment-confirmation result (spec sections 5-10)
    if ($newStatus === 'Completed' && $currentStatus !== 'Completed') {
        if (!stockLedgerExists($db, 'sale', $orderId)) {
            $itemStmt = $db->prepare("SELECT * FROM order_items WHERE order_id = ?");
            $itemStmt->execute([$orderId]);
            $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($items as $item) {
                updateStockAndLedger(
                    $db,
                    $item['product_id'],
                    $item['quantity'],
                    'out',
                    'sale',
                    $orderId,
                    'Order Completed ' . $order['order_no']
                );
            }
        }

        // payment_received: true/1 = "Yes, Payment Received" from the
        // confirmation dialog. paid_amount defaults to the full order
        // total when payment_received is true and no explicit amount
        // is sent, and to 0 (outstanding balance) when it's false.
        $paymentReceived = filter_var($input['payment_received'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $paidAmount = array_key_exists('paid_amount', $input)
            ? (float) $input['paid_amount']
            : ($paymentReceived ? (float) $order['total'] : 0.0);
        if (!$paymentReceived) {
            $paidAmount = 0.0;
        }

        applyOrderPaymentAndSyncInvoice($db, $order, $paidAmount, $user['id'] ?? null);
    }

    // Update status (canonical value)
    $stmt = $db->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$newStatus, $orderId]);

    $db->commit();
    echo json_encode(['success' => true, 'message' => 'Status updated', 'old_status' => $currentStatus, 'new_status' => $newStatus]);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
