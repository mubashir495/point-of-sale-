<?php
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('sales', 'create');

require_once '../../config/database.php';
header('Content-Type: application/json');

try {
    $database = new Database();
    $db = $database->getConnection();

    // Get raw POST data
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    // Validate required fields
    if (!isset($data['customer_id']) || empty($data['customer_id'])) {
        echo json_encode(['success' => false, 'message' => 'Customer ID is required']);
        exit;
    }

    if (!isset($data['employee_id']) || empty($data['employee_id'])) {
        echo json_encode(['success' => false, 'message' => 'Employee/Supplier ID is required']);
        exit;
    }

    if (!isset($data['items']) || empty($data['items']) || !is_array($data['items'])) {
        echo json_encode(['success' => false, 'message' => 'At least one item is required']);
        exit;
    }

    // Generate quotation number
    $stmt = $db->query("SELECT MAX(id) as last_id FROM quotations");
    $last = $stmt->fetch(PDO::FETCH_ASSOC);
    $nextId = ($last['last_id'] ?? 0) + 1;
    $quotationNo = 'QT' . str_pad($nextId, 5, '0', STR_PAD_LEFT);

    // Prepare items as JSON
    $itemsJson = json_encode($data['items']);

    // Get customer name if not provided
    $customerName = $data['customer_name'] ?? null;
    if (!$customerName && isset($data['customer_id'])) {
        $stmt = $db->prepare("SELECT name FROM customers WHERE id = ?");
        $stmt->execute([$data['customer_id']]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);
        $customerName = $customer['name'] ?? null;
    }

    // Insert quotation
    $sql = "INSERT INTO quotations (
                quotation_no,
                customer_id,
                customer_name,
                employee_id,
                shift,
                order_date,
                subtotal,
                discount,
                total,
                paid,
                remarks,
                items
            ) VALUES (
                :quotation_no,
                :customer_id,
                :customer_name,
                :employee_id,
                :shift,
                :order_date,
                :subtotal,
                :discount,
                :total,
                :paid,
                :remarks,
                :items
            )";

    $stmt = $db->prepare($sql);

    $orderDate = $data['order_date'] ?? date('Y-m-d');
    $subtotal = floatval($data['subtotal'] ?? 0);
    $discount = floatval($data['discount'] ?? 0);
    $total = floatval($data['total'] ?? 0);
    $paid = floatval($data['paid'] ?? 0);
    $shift = $data['shift'] ?? 'Morning';
    $remarks = $data['remarks'] ?? '';

    $stmt->bindParam(':quotation_no', $quotationNo);
    $stmt->bindParam(':customer_id', $data['customer_id']);
    $stmt->bindParam(':customer_name', $customerName);
    $stmt->bindParam(':employee_id', $data['employee_id']);
    $stmt->bindParam(':shift', $shift);
    $stmt->bindParam(':order_date', $orderDate);
    $stmt->bindParam(':subtotal', $subtotal);
    $stmt->bindParam(':discount', $discount);
    $stmt->bindParam(':total', $total);
    $stmt->bindParam(':paid', $paid);
    $stmt->bindParam(':remarks', $remarks);
    $stmt->bindParam(':items', $itemsJson);

    if ($stmt->execute()) {
        $id = $db->lastInsertId();
        echo json_encode([
            'success' => true,
            'id' => $id,
            'quotation_no' => $quotationNo,
            'message' => 'Quotation saved successfully'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save quotation']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}