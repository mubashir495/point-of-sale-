<?php
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('sales', 'edit');

require_once '../../config/database.php';
header('Content-Type: application/json');

try {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    $quotationId = isset($data['quotation_id']) ? intval($data['quotation_id']) : 0;

    if (!$quotationId) {
        echo json_encode(['success' => false, 'message' => 'Quotation ID is required']);
        exit;
    }

    $database = new Database();
    $db = $database->getConnection();

    // Get quotation data
    $stmt = $db->prepare("SELECT * FROM quotations WHERE id = :id");
    $stmt->bindParam(':id', $quotationId);
    $stmt->execute();
    $quotation = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$quotation) {
        echo json_encode(['success' => false, 'message' => 'Quotation not found']);
        exit;
    }

    // Generate invoice number
    $stmt = $db->query("SELECT MAX(id) as last_id FROM invoices");
    $last = $stmt->fetch(PDO::FETCH_ASSOC);
    $nextId = ($last['last_id'] ?? 0) + 1;
    $invoiceNo = 'INV' . str_pad($nextId, 5, '0', STR_PAD_LEFT);

    // Decode items
    $items = json_decode($quotation['items'], true);

    // Start transaction
    $db->beginTransaction();

    try {
        // Insert invoice
        $sql = "INSERT INTO invoices (
                    invoice_no,
                    customer_id,
                    employee_id,
                    invoice_date,
                    shift,
                    subtotal,
                    discount,
                    total,
                    paid_amount,
                    due_amount,
                    status,
                    items
                ) VALUES (
                    :invoice_no,
                    :customer_id,
                    :employee_id,
                    :invoice_date,
                    :shift,
                    :subtotal,
                    :discount,
                    :total,
                    :paid,
                    :due,
                    'Pending',
                    :items
                )";

        $stmt = $db->prepare($sql);

        $due = $quotation['total'] - $quotation['paid'];

        $stmt->bindParam(':invoice_no', $invoiceNo);
        $stmt->bindParam(':customer_id', $quotation['customer_id']);
        $stmt->bindParam(':employee_id', $quotation['employee_id']);
        $stmt->bindParam(':invoice_date', $quotation['order_date']);
        $stmt->bindParam(':shift', $quotation['shift']);
        $stmt->bindParam(':subtotal', $quotation['subtotal']);
        $stmt->bindParam(':discount', $quotation['discount']);
        $stmt->bindParam(':total', $quotation['total']);
        $stmt->bindParam(':paid', $quotation['paid']);
        $stmt->bindParam(':due', $due);
        $stmt->bindParam(':items', $quotation['items']);

        $stmt->execute();

        $invoiceId = $db->lastInsertId();

        // Update quotation status
        $stmt = $db->prepare("UPDATE quotations SET status = 'Converted' WHERE id = :id");
        $stmt->bindParam(':id', $quotationId);
        $stmt->execute();

        // Commit transaction
        $db->commit();

        echo json_encode([
            'success' => true,
            'invoice_id' => $invoiceId,
            'invoice_no' => $invoiceNo,
            'message' => 'Quotation converted to invoice successfully'
        ]);
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}