<?php
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('suppliers', 'edit');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT, POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['id'])) {
        throw new Exception("Party ID is required");
    }

    $check = $db->prepare("SELECT id FROM parties WHERE id = ?");
    $check->execute([$data['id']]);
    if (!$check->fetch()) {
        throw new Exception("Party not found", 404);
    }

    $db->beginTransaction();

    // Update parties table
    $fields = [];
    $params = [':id' => $data['id']];
    $allowed = ['party_name', 'phone_number', 'receivable_balance', 'payable_balance'];

    foreach ($allowed as $field) {
        if (array_key_exists($field, $data)) {
            $fields[] = "$field = :$field";
            $params[":$field"] = $data[$field];
        }
    }

    if (!empty($fields)) {
        $sql = "UPDATE parties SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
    }

    // Update items: delete old, insert new
    if (isset($data['items'])) {
        $delete = $db->prepare("DELETE FROM party_materials WHERE party_id = ?");
        $delete->execute([$data['id']]);

        if (is_array($data['items']) && count($data['items']) > 0) {
            $itemStmt = $db->prepare("
                INSERT INTO party_materials (party_id, material_id, rate, min_quantity)
                VALUES (:party_id, :material_id, :rate, :min_quantity)
            ");

            foreach ($data['items'] as $item) {
                if (empty($item['material_id'])) continue;
                $itemStmt->execute([
                    ':party_id' => $data['id'],
                    ':material_id' => $item['material_id'],
                    ':rate' => $item['rate'] ?? 0,
                    ':min_quantity' => $item['min_quantity'] ?? 0,
                ]);
            }
        }
    }

    $db->commit();

    // Fetch updated party
    $fetch = $db->prepare("SELECT * FROM parties WHERE id = ?");
    $fetch->execute([$data['id']]);
    $party = $fetch->fetch(PDO::FETCH_ASSOC);
    $party['items'] = getPartyItems($db, $data['id']);

    echo json_encode([
        "success" => true,
        "message" => "Party updated successfully",
        "data" => $party
    ]);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code($e->getCode() >= 400 ? $e->getCode() : 500);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}

// Same helper
function getPartyItems($db, $party_id) {
    $stmt = $db->prepare("
        SELECT 
            pm.material_id,
            rm.name AS material_name,
            rm.unit,
            pm.rate,
            pm.min_quantity
        FROM party_materials pm
        LEFT JOIN raw_materials rm ON pm.material_id = rm.id
        WHERE pm.party_id = ?
    ");
    $stmt->execute([$party_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}