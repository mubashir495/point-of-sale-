<?php
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('suppliers', 'create');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    $data = json_decode(file_get_contents('php://input'), true);

    // ✅ Validation – transaction start from here
    if (empty($data['party_name'])) {
        throw new Exception("Party name is required");
    }

    $db->beginTransaction();

    // 1️⃣ Insert into parties (only these columns exist)
    $stmt = $db->prepare("
        INSERT INTO parties 
        (party_name, phone_number, receivable_balance, payable_balance)
        VALUES (:party_name, :phone_number, :receivable_balance, :payable_balance)
    ");

    $stmt->execute([
        ':party_name' => $data['party_name'],
        ':phone_number' => $data['phone_number'] ?? null,
        ':receivable_balance' => $data['receivable_balance'] ?? 0,
        ':payable_balance' => $data['payable_balance'] ?? 0,
    ]);

    $party_id = $db->lastInsertId();

    // 2️⃣ Insert items into party_materials if provided
    if (!empty($data['items']) && is_array($data['items'])) {
        $itemStmt = $db->prepare("
            INSERT INTO party_materials (party_id, material_id, rate, min_quantity)
            VALUES (:party_id, :material_id, :rate, :min_quantity)
        ");

        foreach ($data['items'] as $item) {
            if (empty($item['material_id'])) continue;
            $itemStmt->execute([
                ':party_id' => $party_id,
                ':material_id' => $item['material_id'],
                ':rate' => $item['rate'] ?? 0,
                ':min_quantity' => $item['min_quantity'] ?? 0,
            ]);
        }
    }

    $db->commit();

    // 3️⃣ Fetch created party with items
    $fetch = $db->prepare("SELECT * FROM parties WHERE id = ?");
    $fetch->execute([$party_id]);
    $party = $fetch->fetch(PDO::FETCH_ASSOC);
    $party['items'] = getPartyItems($db, $party_id);

    echo json_encode([
        "success" => true,
        "message" => "Party created successfully",
        "data" => $party
    ]);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}

// Helper function
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