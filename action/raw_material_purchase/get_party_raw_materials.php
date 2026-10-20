<?php
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('purchase', 'view');

header('Content-Type: application/json');
require_once '../../config/Database.php';

try {
    $db   = new Database();
    $conn = $db->getConnection();

    $party_id = isset($_GET['party_id']) ? (int)$_GET['party_id'] : 0;

    if ($party_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Party ID is required', 'data' => []]);
        exit;
    }

    $sql = "
        SELECT 
            pm.material_id AS raw_material_id,
            rm.name AS raw_material_name,
            rm.unit,
            pm.rate,
            pm.min_quantity
        FROM party_materials pm
        LEFT JOIN raw_materials rm ON pm.material_id = rm.id
        WHERE pm.party_id = :party_id
        ORDER BY rm.name ASC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([':party_id' => $party_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format with correct keys
    $data = [];
    foreach ($rows as $row) {
        $data[] = [
            'raw_material_id'   => (int) $row['raw_material_id'],
            'raw_material_name' => $row['raw_material_name'] ?? 'Unknown',
            'unit'              => $row['unit'] ?? '',
            'rate'              => (float) $row['rate'],
            'min_quantity'      => (float) $row['min_quantity'],
        ];
    }

    echo json_encode(['success' => true, 'data' => $data]);

} catch (Throwable $e) {
    error_log('get_party_raw_materials.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage(), 'data' => []]);
}