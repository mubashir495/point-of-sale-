<?php
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('ledger', 'view');


header('Content-Type: application/json');
require_once '../../config/database.php';

try {

    $database = new Database();
    $db = $database->getConnection();

    if (empty($_GET['customer_id'])) {
        echo json_encode([
            "success" => false,
            "message" => "Customer ID is required"
        ]);
        exit;
    }

    $customerId = (int)$_GET['customer_id'];
    $dateFrom = $_GET['date_from'] ?? date('Y-m-01');
    $dateTo   = $_GET['date_to'] ?? date('Y-m-d');

    // Customer Detail
    $stmt = $db->prepare("
        SELECT
            id,
            name,
            phone,
            address,
            current_balance
        FROM customers
        WHERE id = ?
    ");

    $stmt->execute([$customerId]);

    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$customer) {
        echo json_encode([
            "success" => false,
            "message" => "Customer not found"
        ]);
        exit;
    }

    // Ledger
    $stmt = $db->prepare("
        SELECT
            entry_date,
            description,
            debit,
            credit,
            balance
        FROM customer_ledger
        WHERE customer_id = ?
        AND entry_date BETWEEN ? AND ?
        ORDER BY entry_date ASC,id ASC
    ");

    $stmt->execute([
        $customerId,
        $dateFrom,
        $dateTo
    ]);

    $ledger = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $rows = [];
    $opening = 0;
    $totalDebit = 0;
    $totalCredit = 0;

    foreach ($ledger as $index => $row) {

        $rows[] = [
            "sr" => $index + 1,
            "date" => $row['entry_date'],
            "description" => $row['description'],
            "opening" => $opening,
            "debit" => (float)$row['debit'],
            "credit" => (float)$row['credit'],
            "balance" => (float)$row['balance']
        ];

        $opening = $row['balance'];

        $totalDebit += $row['debit'];
        $totalCredit += $row['credit'];
    }

    echo json_encode([
        "success" => true,
        "customer" => $customer,
        "rows" => $rows,
        "total_debit" => $totalDebit,
        "total_credit" => $totalCredit,
        "closing" => $opening
    ]);

} catch (Exception $e) {

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);

}