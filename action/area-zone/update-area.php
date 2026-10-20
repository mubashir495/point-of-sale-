<?php
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('users', 'edit');

header('Content-Type: application/json');
include "../../config/db.php";

$data      = json_decode(file_get_contents("php://input"), true);
$id        = intval($data['id'] ?? 0);
$city_id   = intval($data['city_id'] ?? 0);
$area_name = trim($data['area_name'] ?? '');

if ($id <= 0 || $city_id <= 0 || $area_name === '') {
    echo json_encode(["status" => false, "message" => "Id, city and area name are required"]);
    exit;
}

$stmt = mysqli_prepare($conn, "UPDATE areas SET city_id = ?, area_name = ? WHERE id = ?");
mysqli_stmt_bind_param($stmt, "isi", $city_id, $area_name, $id);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode(["status" => true, "message" => "Area updated"]);
} else {
    echo json_encode(["status" => false, "message" => mysqli_error($conn)]);
}