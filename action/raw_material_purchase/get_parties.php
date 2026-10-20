<?php
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('purchase', 'view');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    $search = $_GET['search'] ?? null;
    $id = $_GET['id'] ?? null;

    // 1️⃣ Build base query for parties
    $query = "SELECT p.* FROM parties p WHERE 1=1";
    $params = [];

    if (!empty($id)) {
        $query .= " AND p.id = :id";
        $params[':id'] = $id;
    } else {
        if (!empty($search)) {
            $query .= " AND (p.party_name LIKE :search OR p.phone_number LIKE :search)";
            $params[':search'] = "%{$search}%";
        }
        $query .= " ORDER BY p.id DESC";
    }

    $stmt = $db->prepare($query);
    $stmt->execute($params);

    if (!empty($id)) {
        $party = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$party) {
            echo json_encode(["error" => "Party not found"]);
            exit;
        }

        // Fetch items for this party
        $items = getPartyItems($db, $party['id']);
        $party['items'] = $items;
        $party['receivable_balance'] = floatval($party['receivable_balance']);
        $party['payable_balance'] = floatval($party['payable_balance']);

        echo json_encode($party);
        exit;
    }

    // For list: fetch all parties, then attach items per party
    $parties = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($parties as &$p) {
        $p['items'] = getPartyItems($db, $p['id']);
        $p['receivable_balance'] = floatval($p['receivable_balance']);
        $p['payable_balance'] = floatval($p['payable_balance']);
    }

    echo json_encode(["success" => true, "count" => count($parties), "data" => $parties]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}

/**
 * Fetch items for a given party ID from party_materials + raw_materials
 */
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