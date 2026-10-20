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

    $from = $_GET['from'] ?? null;
    $to = $_GET['to'] ?? null;
    $party_id = $_GET['party_id'] ?? null;

    // ✅ phone column hata diya – sirf zaroori fields
    $query = "SELECT p.*, pt.party_name AS supplier_name,
                (p.total_amount - p.paid_amount) AS balance_due
              FROM raw_material_purchases p
              LEFT JOIN parties pt ON p.party_id = pt.id
              WHERE 1=1";
    $params = [];

    if (!empty($from)) { $query .= " AND p.purchase_date >= :from"; $params[':from'] = $from; }
    if (!empty($to)) { $query .= " AND p.purchase_date <= :to"; $params[':to'] = $to; }
    if (!empty($party_id)) { $query .= " AND p.party_id = :party_id"; $params[':party_id'] = $party_id; }

    $query .= " ORDER BY p.id DESC";

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // attach items for each purchase
    $itemStmt = $db->prepare("SELECT i.*, m.name AS material_name, m.unit
                               FROM raw_material_purchase_items i
                               LEFT JOIN raw_materials m ON i.material_id = m.id
                               WHERE i.purchase_id = :pid");

    foreach ($purchases as &$purchase) {
        $itemStmt->execute([':pid' => $purchase['id']]);
        $purchase['items'] = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode(["success" => true, "count" => count($purchases), "data" => $purchases]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}