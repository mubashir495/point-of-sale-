<?php
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('ledger', 'create');
rbac_verify_csrf();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');


require_once '../../config/database.php';
try {
    $database = new Database();
    $db = $database->getConnection();
    $data = json_decode(file_get_contents("php://input"), true);
    if(
        empty($data['customer_id']) ||
        empty($data['amount'])
    ){
        http_response_code(400);

        echo json_encode([
            "success"=>false,
            "message"=>"Customer and amount required"
        ]);
        exit;
    }
    $customer_id = $data['customer_id'];
    $amount = $data['amount'];
    $payment_method = $data['payment_method'] ?? "Cash";
    $reference_no = $data['reference_no'] ?? "";
    $remarks = $data['remarks'] ?? "";
    $db->beginTransaction();
    $stmt = $db->prepare("
        SELECT *
        FROM customers
        WHERE id = ?
    ");
    $stmt->execute([
        $customer_id
    ]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    if(!$customer){
        throw new Exception("Customer not found");

    }

    $stmt = $db->prepare("
        INSERT INTO customer_payments
        (
            customer_id,
            payment_date,
            amount,
            payment_method,
            reference_no,
            remarks
        )

        VALUES
        (?,?,?,?,?,?)
    ");

    $stmt->execute([
        $customer_id,
        date('Y-m-d'),
        $amount,
        $payment_method,
        $reference_no,
        $remarks
    ]);
    $payment_id = $db->lastInsertId();
    $stmt = $db->prepare("
        UPDATE customers
        SET current_balance = current_balance - ?
        WHERE id = ?
    ");
    $stmt->execute([
        $amount,
        $customer_id
    ]);
    $stmt = $db->prepare("
        SELECT current_balance
        FROM customers
        WHERE id = ?
    ");
    $stmt->execute([
        $customer_id
    ]);
    $newBalance = $stmt->fetchColumn();
    $stmt = $db->prepare("
        INSERT INTO customer_ledger
        (
            customer_id,
            payment_id,
            entry_date,
            description,
            debit,
            credit,
            balance
        )

        VALUES
        (?,?,?,?,?,?,?)
    ");
    $stmt->execute([
        $customer_id,
        $payment_id,
        date('Y-m-d'),
        "Payment Received",
        0,
        $amount,
        $newBalance
    ]);
    $db->commit();
    echo json_encode([
        "success"=>true,
        "message"=>"Payment added successfully",
        "payment_id"=>$payment_id,
        "new_balance"=>$newBalance
    ]);
}catch (Throwable $e) {

    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage(),
        "line" => $e->getLine(),
        "file" => $e->getFile()
    ]);
}
?>