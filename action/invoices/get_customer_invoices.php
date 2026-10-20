<?php
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('sales', 'view');

require_once '../../config/database.php';
header('Content-Type: application/json');
try {
    $database = new Database();
    $db = $database->getConnection();
    $customer_id = isset($_GET['customer_id'])
        ? (int)$_GET['customer_id']
        : 0;
    if(!$customer_id){
        echo json_encode([
            "success"=>false,
            "message"=>"Customer id required"
        ]);
        exit;
    }
    $stmt = $db->prepare("
        SELECT 
            c.id,
            c.name,
            c.phone,
            c.address,
            c.current_balance,
            ci.city_name,
            a.area_name
        FROM customers c
        LEFT JOIN cities ci
        ON ci.id = c.city_id
        LEFT JOIN areas a
        ON a.id = c.area_id
        WHERE c.id=?
    ");
    $stmt->execute([$customer_id]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt = $db->prepare("
        SELECT *
        FROM invoices
        WHERE customer_id=?
        ORDER BY id ASC
    ");
    $stmt->execute([$customer_id]);
    $invoiceData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $balance = 0;
    $invoices=[];
    foreach($invoiceData as $invoice){
        $previous = $balance;
        $balance =
        $balance 
        + $invoice['total']
        - $invoice['paid_amount'];
        $invoices[]=[
            "invoice_id"=>$invoice['id'],
            "invoice_no"=>$invoice['invoice_no'],
            "date"=>$invoice['invoice_date'],
            "total"=>(float)$invoice['total'],
            "paid"=>(float)$invoice['paid_amount'],
            "due"=>(float)$invoice['due_amount'],
            "previous_balance"=>(float)$previous,
            "current_balance"=>(float)$balance,
            "status"=>$invoice['status']
        ];
    }
    $stmt=$db->query("
        SELECT MAX(id) as last_id
        FROM invoices
    ");
    $last=$stmt->fetch(PDO::FETCH_ASSOC);
    $nextInvoice = 
    "INV".str_pad(
        ($last['last_id']??0)+1,
        5,
        "0",
        STR_PAD_LEFT
    );
    echo json_encode([
        "success"=>true,
        "customer"=>$customer,
        "next_invoice"=>$nextInvoice,
        "total_balance"=>$balance,
        "invoices"=>$invoices
    ]);
}catch(PDOException $e){
    echo json_encode([
        "success"=>false,
        "message"=>$e->getMessage()
    ]);
}
?>