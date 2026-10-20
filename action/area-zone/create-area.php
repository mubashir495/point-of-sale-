<?php
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('users', 'create');

header('Content-Type: application/json');
include "../../config/db.php";

$data      = json_decode(file_get_contents("php://input"), true);
$city_id   = intval($data['city_id'] ?? 0);
$area_name = trim($data['area_name'] ?? '');
$branch_id = !empty($data['branch_id']) ? (int) $data['branch_id'] : rbac_current_branch_id();

if ($city_id <= 0 || $area_name === '') {
    echo json_encode(["status" => false, "message" => "City and area name are required"]);
    exit;
}

$stmt = mysqli_prepare($conn, "INSERT INTO areas (city_id, area_name, branch_id) VALUES (?, ?, ?)");
mysqli_stmt_bind_param($stmt, "isi", $city_id, $area_name, $branch_id);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode([
        "status"  => true,
        "message" => "Area created",
        "id"      => mysqli_insert_id($conn)
    ]);
} else {
    echo json_encode(["status" => false, "message" => mysqli_error($conn)]);
}