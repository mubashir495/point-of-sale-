<?php
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('employees', 'view');

ob_start();

require_once '../../config/database.php';

header("Content-Type: application/json");

$database = new Database();
$pdo = $database->getConnection();

try {
    $employeeId = (int)($_GET['employee_id'] ?? 0);
    $dateFrom = trim($_GET['date_from'] ?? '');
    $dateTo = trim($_GET['date_to'] ?? '');

    if ($employeeId <= 0) {
        throw new Exception("employee_id is required");
    }

    
    $empStmt = $pdo->prepare(
        "SELECT id, full_name, designation, contact, cnic, shift, address, balance
         FROM employees WHERE id = :id"
    );
    $empStmt->execute([':id' => $employeeId]);
    $employee = $empStmt->fetch();
    if (!$employee) throw new Exception("Employee not found");

    $sql = "SELECT id, txn_date, description, debit, credit, balance
            FROM employee_ledger
            WHERE employee_id = :eid";
    $params = [':eid' => $employeeId];

    if ($dateFrom !== '') {
        $sql .= " AND txn_date >= :date_from";
        $params[':date_from'] = $dateFrom;
    }
    if ($dateTo !== '') {
        $sql .= " AND txn_date <= :date_to";
        $params[':date_to'] = $dateTo;
    }

    $sql .= " ORDER BY txn_date ASC, id ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $totalDebit = 0;
    $totalCredit = 0;
    $sr = 1;
    $out = [];
    foreach ($rows as $r) {
        $totalDebit += (float)$r['debit'];
        $totalCredit += (float)$r['credit'];
        $out[] = [
            'sr' => $sr++,
            'date' => $r['txn_date'],
            'description' => $r['description'],
            'debit' => $r['debit'],
            'credit' => $r['credit'],
            'balance' => $r['balance'],
        ];
    }

    $closing = !empty($out) ? end($out)['balance'] : (float)$employee['balance'];

    ob_clean();
    echo json_encode([
        "success" => true,
        "employee" => $employee,
        "rows" => $out,
        "total_debit" => $totalDebit,
        "total_credit" => $totalCredit,
        "closing" => $closing,
    ]);
} catch (Exception $e) {
    ob_clean();
    http_response_code(400);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
