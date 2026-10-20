<?php
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('raw_materials', 'edit');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    $data = json_decode(file_get_contents('php://input'), true);

    $id = intval($data['id'] ?? 0);
    $name = trim($data['name'] ?? '');
    $unit = trim($data['unit'] ?? '');
    $reorder_level = floatval($data['reorder_level'] ?? 0);
    $status = ($data['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

    if ($id <= 0 || $name === '' || $unit === '') {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "id, name and unit are required"]);
        exit;
    }

    $stmt = $db->prepare("UPDATE raw_materials
        SET name = :name, unit = :unit, reorder_level = :reorder, status = :status
        WHERE id = :id");
    $stmt->execute([
        ':name' => $name,
        ':unit' => $unit,
        ':reorder' => $reorder_level,
        ':status' => $status,
        ':id' => $id
    ]);

    echo json_encode(["success" => true, "message" => "Raw material updated successfully"]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
