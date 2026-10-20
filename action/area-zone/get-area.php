<?php
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('users', 'view');

header('Content-Type: application/json');
include "../../config/db.php";

$sql = "SELECT areas.id, areas.area_name, areas.city_id, areas.created_at, cities.city_name
        FROM areas
        LEFT JOIN cities ON areas.city_id = cities.id
        ORDER BY areas.id DESC";

$result = mysqli_query($conn, $sql);
if (!$result) {
    echo json_encode(["status" => false, "message" => mysqli_error($conn)]);
    exit;
}

$areas = [];
while ($row = mysqli_fetch_assoc($result)) {
    $areas[] = $row;
}

echo json_encode(["status" => true, "data" => $areas]);