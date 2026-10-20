<?php
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('raw_materials', 'view');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    $search = $_GET['search'] ?? null;

    $query = "SELECT * FROM raw_materials WHERE 1=1";
    $params = [];

    if (!empty($search)) {
        $query .= " AND name LIKE :search";
        $params[':search'] = "%{$search}%";
    }

    $query .= " ORDER BY id DESC";

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // mark low stock items
    foreach ($data as &$row) {
        $row['low_stock'] = floatval($row['current_stock']) <= floatval($row['reorder_level']);
    }

    echo json_encode(["success" => true, "count" => count($data), "data" => $data]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}