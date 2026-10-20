<?php
/**
 * action/orders/add_sale_return.php
 * ------------------------------------------------------------------
 * Processes a return against a Counter Sale invoice:
 *   - validates the returned qty against what's still returnable
 *   - inserts sale_returns / sale_return_items rows
 *   - puts the stock back (updateStockAndLedger 'in') so the
 *     product's current_stock and stock_ledger stay accurate
 * Daily/overall sale totals (get_counter_sale_stats.php) are netted
 * against sale_returns.total, so no need to edit the original order.
 * ------------------------------------------------------------------
 */
require_once __DIR__ . '/../../config/rbac.php';
$user = rbac_require_permission('sales', 'edit');

require_once '../../config/database.php';
require_once '../../config/accounting_helpers.php';

$database = new Database();
$db = $database->getConnection();
header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $orderId = (int)($input['order_id'] ?? 0);
    $remarks = trim($input['remarks'] ?? '');
    $returnItems = $input['items'] ?? [];

    if ($orderId <= 0) throw new Exception('order_id is required');
    if (empty($returnItems)) throw new Exception('At least one item with a return quantity is required');

    $branchId = rbac_current_branch_id();

    $stmt = $db->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$order) throw new Exception('Original invoice not found');
    if ($order['status'] !== 'Counter') throw new Exception('Only Counter Sale invoices can be returned from this screen');

    $db->beginTransaction();

    $subtotal = 0;
    $validatedItems = [];

    foreach ($returnItems as $ri) {
        $orderItemId = (int)($ri['order_item_id'] ?? 0);
        $qty = (float)($ri['quantity'] ?? 0);
        if ($orderItemId <= 0 || $qty <= 0) continue;

        // Lock in the true order_item row (never trust client-sent price/product).
        $oiStmt = $db->prepare("SELECT * FROM order_items WHERE id = ? AND order_id = ?");
        $oiStmt->execute([$orderItemId, $orderId]);
        $orderItem = $oiStmt->fetch(PDO::FETCH_ASSOC);
        if (!$orderItem) throw new Exception("Order item #$orderItemId does not belong to this invoice");

        $alreadyStmt = $db->prepare("SELECT COALESCE(SUM(quantity), 0) FROM sale_return_items WHERE order_item_id = ?");
        $alreadyStmt->execute([$orderItemId]);
        $alreadyReturned = (float)$alreadyStmt->fetchColumn();

        $remaining = (float)$orderItem['quantity'] - $alreadyReturned;
        if ($qty > $remaining + 0.0001) {
            throw new Exception("Cannot return {$qty} of item #{$orderItemId} - only {$remaining} left to return");
        }

        $unitPrice = (float)$orderItem['unit_price'];
        $lineTotal = round($unitPrice * $qty, 2);
        $subtotal += $lineTotal;

        $validatedItems[] = [
            'order_item_id' => $orderItemId,
            'product_id'    => (int)$orderItem['product_id'],
            'quantity'      => $qty,
            'unit_price'    => $unitPrice,
            'total'         => $lineTotal,
        ];
    }

    if (empty($validatedItems)) throw new Exception('No valid return quantities were provided');

    $total = round($subtotal, 2);
    $returnNo = 'RET-' . date('ymd') . '-' . strtoupper(substr(uniqid(), -5));

    $insReturn = $db->prepare("
        INSERT INTO sale_returns (return_no, order_id, invoice_no, customer_id, return_date, subtotal, total, remarks, branch_id, created_by, created_at)
        VALUES (:return_no, :order_id, :invoice_no, :customer_id, CURDATE(), :subtotal, :total, :remarks, :branch_id, :created_by, NOW())
    ");
    $insReturn->execute([
        ':return_no'   => $returnNo,
        ':order_id'    => $orderId,
        ':invoice_no'  => $order['order_no'],
        ':customer_id' => $order['customer_id'] ?: null,
        ':subtotal'    => $subtotal,
        ':total'       => $total,
        ':remarks'     => $remarks,
        ':branch_id'   => $branchId,
        ':created_by'  => $user['id'] ?? null,
    ]);
    $returnId = $db->lastInsertId();

    $insItem = $db->prepare("
        INSERT INTO sale_return_items (return_id, order_item_id, product_id, quantity, unit_price, total)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    foreach ($validatedItems as $vi) {
        $insItem->execute([
            $returnId,
            $vi['order_item_id'],
            $vi['product_id'],
            $vi['quantity'],
            $vi['unit_price'],
            $vi['total'],
        ]);

        // Put the stock back.
        updateStockAndLedger(
            $db,
            $vi['product_id'],
            $vi['quantity'],
            'in',
            'sale_return',
            $returnId,
            'Sale Return ' . $returnNo . ' against Invoice ' . $order['order_no']
        );
    }

    $db->commit();
    echo json_encode([
        'success'   => true,
        'message'   => 'Return processed successfully',
        'return_id' => (int)$returnId,
        'return_no' => $returnNo,
        'total'     => $total,
    ]);

} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
