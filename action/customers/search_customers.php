<?php
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('customers', 'view');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../../config/database.php'; 

try {
    $database = new Database();
    $db = $database->getConnection();

    $q = isset($_GET['q']) ? trim($_GET['q']) : '';

    if (strlen($q) < 2) {
        echo json_encode([]);
        exit;
    }

    $scopedIds = rbac_scope_customer_ids();
    if ($scopedIds !== null && empty($scopedIds)) {
        echo json_encode([]);
        exit;
    }

$sql = "
SELECT
    c.id,
    c.customer_code,
    c.name,
    c.phone,
    c.email,
    c.address,
    c.city_id,
    c.area_id,
    c.opening_balance,
    c.current_balance,
    ci.city_name,
    a.area_name,
    CONCAT_WS(', ',
        NULLIF(c.address, ''),
        NULLIF(a.area_name, ''),
        NULLIF(ci.city_name, '')
    ) AS full_address
FROM customers c
LEFT JOIN cities ci ON c.city_id = ci.id
LEFT JOIN areas a ON c.area_id = a.id
WHERE
    c.name LIKE ?
    OR c.phone LIKE ?
    OR c.customer_code LIKE ?
";

if ($scopedIds !== null) {
    $placeholders = implode(',', array_fill(0, count($scopedIds), '?'));
    $sql .= " AND c.id IN ($placeholders)";
}

$sql .= "
ORDER BY c.name ASC
LIMIT 10
";

$stmt = $db->prepare($sql);

$search = "%{$q}%";
$bindParams = [$search, $search, $search];
if ($scopedIds !== null) {
    $bindParams = array_merge($bindParams, $scopedIds);
}
$stmt->execute($bindParams);

echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => $e->getMessage()
    ]);
}