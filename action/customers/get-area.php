<?php
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('customers', 'view');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    $cityId = $_GET['city_id'] ?? null;

    if ($cityId) {
        $query = "SELECT id, area_name FROM areas WHERE city_id = :city_id ORDER BY area_name ASC";
        $stmt = $db->prepare($query);
        $stmt->execute([':city_id' => $cityId]);
    } else {
        $query = "SELECT id, area_name FROM areas ORDER BY area_name ASC";
        $stmt = $db->prepare($query);
        $stmt->execute();
    }

    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}