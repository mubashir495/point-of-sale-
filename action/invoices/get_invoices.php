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


    $date_from = $_GET['date_from'] ?? null;
    $date_to   = $_GET['date_to'] ?? null;



    if (!$customer_id) {

        echo json_encode([
            "success" => false,
            "message" => "customer_id required"
        ]);

        exit;
    }
    $stmt = $db->prepare("
        SELECT 
            c.*,
            ci.city_name,
            a.area_name
        FROM customers c

        LEFT JOIN cities ci 
        ON ci.id = c.city_id

        LEFT JOIN areas a 
        ON a.id = c.area_id

        WHERE c.id = ?
    ");


    $stmt->execute([$customer_id]);


    $customer = $stmt->fetch(PDO::FETCH_ASSOC);



    if (!$customer) {

        echo json_encode([
            "success" => false,
            "message" => "Customer not found"
        ]);

        exit;
    }




    // =========================
    // GET INVOICES
    // =========================


    $sql = "
        SELECT *
        FROM invoices
        WHERE customer_id = ?
    ";


    $params = [$customer_id];



    if ($date_from) {

        $sql .= " AND invoice_date >= ?";

        $params[] = $date_from;
    }



    if ($date_to) {

        $sql .= " AND invoice_date <= ?";

        $params[] = $date_to;
    }



    $sql .= "
        ORDER BY invoice_date ASC, id ASC
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $invoices = [];
    $running_balance = 0;
    foreach ($rows as $row) {
        $prev_bal = $running_balance;
        $net_bill = (float)$row['total'];
        $running_balance =
            $prev_bal + $net_bill - (float)$row['paid_amount'];

        $invoices[] = [

            "invoice_no" => $row['invoice_no'],

            "date" => $row['invoice_date'],

            "bill_amt" => (float)$row['subtotal'],

            "discount" => (float)$row['discount'],

            "net_bill" => $net_bill,

            "prev_bal" => $prev_bal,

            "paid" => (float)$row['paid_amount'],

            "new_bal" => $running_balance,

            "status" => $row['status']

        ];

    }



    echo json_encode([

        "success" => true,

        "customer" => $customer,

        "invoices" => $invoices,

        "current_balance" => $running_balance

    ]);



} catch (PDOException $e) {


    http_response_code(500);


    echo json_encode([

        "success" => false,

        "message" => $e->getMessage()

    ]);

}

?>