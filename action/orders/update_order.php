<?php
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('sales', 'edit');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Order ID is required']);
        exit;
    }

    $orderId = (int)$data['id'];

    $required = ['customer_id', 'employee_id', 'order_date', 'shift'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "Field '{$field}' is required"]);
            exit;
        }
    }

    // Validate items same way as create_order.php
    $validItems = [];
    if (!empty($data['items']) && is_array($data['items'])) {
        foreach ($data['items'] as $item) {
            if (empty($item['product_id']) || empty($item['quantity']) || $item['quantity'] <= 0) {
                continue;
            }
            $validItems[] = [
                'product_id' => (int)$item['product_id'],
                'quantity'   => (int)$item['quantity'],
                'unit_price' => (float)($item['unit_price'] ?? 0),
                'total'      => (float)($item['total'] ?? ($item['quantity'] * ($item['unit_price'] ?? 0)))
            ];
        }
    }

    if (empty($validItems)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Order must contain at least one valid item (product_id + quantity > 0)'
        ]);
        exit;
    }

    $db->beginTransaction();

    try {
        // Make sure the order actually exists before doing anything
        $checkOrder = $db->prepare("SELECT id FROM orders WHERE id = ?");
        $checkOrder->execute([$orderId]);
        if (!$checkOrder->fetch()) {
            throw new Exception("Order not found");
        }

        $subtotal = (float)($data['subtotal'] ?? 0);
        $discount = (float)($data['discount'] ?? 0);
        $total    = (float)($data['total'] ?? 0);

        // Update order
        $query = "UPDATE orders SET
                  customer_id = :customer_id,
                  employee_id = :employee_id,
                  order_date = :order_date,
                  delivery_date = :delivery_date,
                  shift = :shift,
                  subtotal = :subtotal,
                  discount = :discount,
                  total = :total,
                  remarks = :remarks,
                  updated_at = NOW()
                  WHERE id = :id";

        $stmt = $db->prepare($query);
        $stmt->execute([
            ':id'            => $orderId,
            ':customer_id'   => (int)$data['customer_id'],
            ':employee_id'   => (int)$data['employee_id'],
            ':order_date'    => $data['order_date'],
            ':delivery_date' => $data['delivery_date'] ?? null,
            ':shift'         => $data['shift'],
            ':subtotal'      => $subtotal,
            ':discount'      => $discount,
            ':total'         => $total,
            ':remarks'       => $data['remarks'] ?? ''
        ]);

        // Replace order items
        $deleteItems = $db->prepare("DELETE FROM order_items WHERE order_id = :order_id");
        $deleteItems->execute([':order_id' => $orderId]);

        $itemStmt = $db->prepare("
            INSERT INTO order_items (order_id, product_id, quantity, unit_price, total)
            VALUES (:order_id, :product_id, :quantity, :unit_price, :total)
        ");

        foreach ($validItems as $item) {
            $itemStmt->execute([
                ':order_id'   => $orderId,
                ':product_id' => $item['product_id'],
                ':quantity'   => $item['quantity'],
                ':unit_price' => $item['unit_price'],
                ':total'      => $item['total']
            ]);
        }

        // ==========================
        // FIX: keep the linked invoice in sync with the updated order
        // Only touch subtotal/discount/total/due_amount — don't reset
        // paid_amount or status, since payments may already exist.
        // ==========================
        $invStmt = $db->prepare("SELECT id, paid_amount FROM invoices WHERE order_id = :order_id");
        $invStmt->execute([':order_id' => $orderId]);
        $invoice = $invStmt->fetch(PDO::FETCH_ASSOC);

        if ($invoice) {
            $paidAmount = (float)$invoice['paid_amount'];
            $dueAmount  = $total - $paidAmount;

            $updateInv = $db->prepare("
                UPDATE invoices SET
                    subtotal = :subtotal,
                    discount = :discount,
                    total = :total,
                    due_amount = :due_amount,
                    updated_at = NOW()
                WHERE id = :id
            ");
            $updateInv->execute([
                ':subtotal'   => $subtotal,
                ':discount'   => $discount,
                ':total'      => $total,
                ':due_amount' => $dueAmount,
                ':id'         => $invoice['id']
            ]);
        }

        $db->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Order updated successfully'
        ]);

    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}