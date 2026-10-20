<?php
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('sales', 'create');

require_once '../../config/database.php';
$database = new Database();
$db = $database->getConnection();
header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (empty($input['items']) || !is_array($input['items'])) {
        throw new Exception('items[] is required');
    }
    $validItems = [];
    foreach ($input['items'] as $item) {
        if (empty($item['product_id']) || empty($item['quantity']) || $item['quantity'] <= 0) continue;
        $validItems[] = [
            'product_id' => (int)$item['product_id'],
            'quantity' => (int)$item['quantity'],
            'unit_price' => (float)($item['unit_price'] ?? 0),
            'total' => (float)($item['total'] ?? ($item['quantity'] * ($item['unit_price'] ?? 0)))
        ];
    }
    if (empty($validItems)) throw new Exception('Order must contain at least one valid item');
    
    $customerId = isset($input['customer_id']) ? (int)$input['customer_id'] : null;
    $employeeId = isset($input['employee_id']) ? (int)$input['employee_id'] : null;
    $shift = $input['shift'] ?? null;
    $orderDate = $input['order_date'] ?? date('Y-m-d');
    $deliveryDate = $input['delivery_date'] ?? null;
    $subtotal = (float)($input['subtotal'] ?? 0);
    $discount = (float)($input['discount'] ?? 0);
    $total = (float)($input['total'] ?? 0);
    $remarks = $input['remarks'] ?? '';
    $status = $input['status'] ?? 'Pending';
    $branchId = rbac_current_branch_id();

    if (rbac_is_branch_scoped() && !$branchId) {
        throw new Exception("Your account isn't linked to a branch yet - contact an admin.");
    }

    $db->beginTransaction();
    
    // Generate order no
    $stmt = $db->query("SELECT MAX(id) as last_id FROM orders");
    $last = $stmt->fetch(PDO::FETCH_ASSOC);
    $nextId = ($last['last_id'] ?? 0) + 1;
    $orderNo = 'ORD' . str_pad($nextId, 5, '0', STR_PAD_LEFT);
    
    $sql = "INSERT INTO orders (order_no, customer_id, employee_id, order_date, delivery_date, shift, subtotal, discount, total, status, remarks, branch_id)
            VALUES (:order_no, :customer_id, :employee_id, :order_date, :delivery_date, :shift, :subtotal, :discount, :total, :status, :remarks, :branch_id)";
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':order_no' => $orderNo,
        ':customer_id' => $customerId,
        ':employee_id' => $employeeId,
        ':order_date' => $orderDate,
        ':delivery_date' => $deliveryDate,
        ':shift' => $shift,
        ':subtotal' => $subtotal,
        ':discount' => $discount,
        ':total' => $total,
        ':status' => $status,
        ':remarks' => $remarks,
        ':branch_id' => $branchId,
    ]);
    $orderId = $db->lastInsertId();
    
    $itemStmt = $db->prepare("INSERT INTO order_items (order_id, product_id, quantity, unit_price, total) VALUES (?, ?, ?, ?, ?)");
    foreach ($validItems as $item) {
        $itemStmt->execute([$orderId, $item['product_id'], $item['quantity'], $item['unit_price'], $item['total']]);
    }
    
    $db->commit();
    echo json_encode(['success' => true, 'message' => 'Order created successfully', 'order_id' => $orderId, 'order_no' => $orderNo]);
    
} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}