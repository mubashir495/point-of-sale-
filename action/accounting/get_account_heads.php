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

    $type = $_GET['type'] ?? null; // asset/liability/equity/revenue/expense

    $query = "SELECT id, name, type, is_locked FROM account_heads WHERE 1=1";
    $params = [];

    if (!empty($type)) {
        $query .= " AND type = :type";
        $params[':type'] = $type;
    }

    $query .= " ORDER BY name ASC";

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(["success" => true, "count" => count($data), "data" => $data]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
