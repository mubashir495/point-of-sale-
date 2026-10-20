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

    $search = $_GET['search'] ?? '';
    $area   = $_GET['area'] ?? '';
    $city   = $_GET['city'] ?? '';

    $scopedIds = rbac_scope_customer_ids();

    if (rbac_is_branch_scoped() && !rbac_current_branch_id()) {
        echo json_encode(["status" => true, "data" => []]);
        exit;
    }

    $query = "
    SELECT
        c.*,
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
    WHERE 1=1
";

    $params = [];

    if (rbac_is_branch_scoped()) {
        $query .= " AND c.branch_id = ?";
        $params[] = rbac_current_branch_id();
    } elseif (!empty($_GET['branch_id'])) {
        $query .= " AND c.branch_id = ?";
        $params[] = (int) $_GET['branch_id'];
    }

    if (!empty($search)) {
        $query .= "
            AND (
                c.name LIKE ?
                OR c.phone LIKE ?
                OR c.customer_code LIKE ?
            )
        ";

        $like = "%{$search}%";
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    if (!empty($area)) {
        $query .= " AND c.area_id = ?";
        $params[] = $area;
    }

    if (!empty($city)) {
        $query .= " AND c.city_id = ?";
        $params[] = $city;
    }

    if ($scopedIds !== null) {
        if (empty($scopedIds)) {
            // Assigned to zero customers -> nothing to show.
            echo json_encode(["status" => true, "data" => []]);
            exit;
        }
        $placeholders = implode(',', array_fill(0, count($scopedIds), '?'));
        $query .= " AND c.id IN ($placeholders)";
        foreach ($scopedIds as $cid) {
            $params[] = $cid;
        }
    }

    $query .= " ORDER BY c.id DESC";

    $stmt = $db->prepare($query);
    $stmt->execute($params);

    echo json_encode([
        "status" => true,
        "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        "status" => false,
        "message" => $e->getMessage()
    ]);
}