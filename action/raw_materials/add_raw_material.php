<?php
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('raw_materials', 'create');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    $data = json_decode(file_get_contents('php://input'), true);

    $name = trim($data['name'] ?? '');
    $unit = trim($data['unit'] ?? '');
    $reorder_level = floatval($data['reorder_level'] ?? 0);
    $opening_stock = floatval($data['opening_stock'] ?? 0);

    if ($name === '' || $unit === '') {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Name and unit are required"]);
        exit;
    }

    $db->beginTransaction();

    $stmt = $db->prepare("INSERT INTO raw_materials (name, unit, current_stock, reorder_level)
        VALUES (:name, :unit, :stock, :reorder)");
    $stmt->execute([
        ':name' => $name,
        ':unit' => $unit,
        ':stock' => $opening_stock,
        ':reorder' => $reorder_level
    ]);

    $materialId = $db->lastInsertId();

    // if opening stock given, log it in stock_ledger
    if ($opening_stock > 0) {
        $ledgerStmt = $db->prepare("INSERT INTO stock_ledger (material_id, type, qty, reference_type, note)
            VALUES (:material_id, 'in', :qty, 'adjustment', 'Opening Stock')");
        $ledgerStmt->execute([':material_id' => $materialId, ':qty' => $opening_stock]);
    }

    $db->commit();

    echo json_encode(["success" => true, "message" => "Raw material added successfully", "id" => $materialId]);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    http_response_code(500);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
