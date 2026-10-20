<?php
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('users', 'view');

header('Content-Type: application/json');
include "../../config/db.php";

$result = mysqli_query($conn, "SELECT * FROM cities ORDER BY id DESC");
if (!$result) {
    echo json_encode(["status" => false, "message" => mysqli_error($conn)]);
    exit;
}

$cities = [];
while ($row = mysqli_fetch_assoc($result)) {
    $cities[] = $row;
}

echo json_encode($cities);
