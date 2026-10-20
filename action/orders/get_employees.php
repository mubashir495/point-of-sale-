<?php
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('sales', 'view');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    $shift = $_GET['shift'] ?? '';

    $query = "SELECT id, full_name, shift
              FROM employees
              WHERE designation = 'Supplier'";

    $params = [];

    if (!empty($shift)) {
        $query .= " AND shift = :shift";
        $params[':shift'] = $shift;
    }

    $query .= " ORDER BY full_name ASC";

    $stmt = $db->prepare($query);
    $stmt->execute($params);

    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}