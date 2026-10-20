<?php
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('expenses', 'view');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    $from     = $_GET['from'] ?? '1970-01-01';
    $to       = $_GET['to'] ?? date('Y-m-d');
    $headId   = $_GET['head_id'] ?? null;
    $accId    = $_GET['account_id'] ?? null;

    $query = "
        SELECT
            e.id, e.expense_date, e.amount, e.description, e.created_at,
            h.name AS head_name,
            dr.name AS dr_account_name,
            cr.name AS cr_account_name
        FROM expenses e
        JOIN account_heads h ON e.head_id = h.id
        JOIN chart_of_accounts dr ON e.dr_account_id = dr.id
        JOIN chart_of_accounts cr ON e.cr_account_id = cr.id
        WHERE e.expense_date BETWEEN :from AND :to
    ";
    $params = [':from' => $from, ':to' => $to];

    if (!empty($headId)) {
        $query .= " AND e.head_id = :head_id";
        $params[':head_id'] = $headId;
    }
    if (!empty($accId)) {
        $query .= " AND e.dr_account_id = :account_id";
        $params[':account_id'] = $accId;
    }

    $query .= " ORDER BY e.expense_date DESC, e.id DESC";

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total = 0;
    foreach ($data as $row) $total += (float)$row['amount'];

    echo json_encode([
        "success" => true,
        "count" => count($data),
        "data" => $data,
        "total_expenses" => round($total, 2)
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
