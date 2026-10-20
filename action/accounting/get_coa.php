<?php
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('ledger', 'view');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    $headId    = $_GET['head_id'] ?? null;
    $type      = $_GET['type'] ?? null;       // filter by head type e.g. expense
    $cashBank  = $_GET['cash_bank'] ?? null;  // '1' to get only Cash/Bank usable accounts

    $query = "
        SELECT
            c.id, c.name, c.is_locked, c.is_cash_bank,
            h.id AS head_id, h.name AS head_name, h.type AS head_type
        FROM chart_of_accounts c
        JOIN account_heads h ON c.head_id = h.id
        WHERE 1=1
    ";
    $params = [];

    if (!empty($headId)) {
        $query .= " AND c.head_id = :head_id";
        $params[':head_id'] = $headId;
    }

    if (!empty($type)) {
        $query .= " AND h.type = :type";
        $params[':type'] = $type;
    }

    if ($cashBank === '1') {
        $query .= " AND c.is_cash_bank = 1";
    }

    $query .= " ORDER BY h.name ASC, c.name ASC";

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(["success" => true, "count" => count($data), "data" => $data]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
