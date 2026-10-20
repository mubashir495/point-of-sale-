<?php
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('users', 'edit');

header('Content-Type: application/json');
include "../../config/db.php";

$id = intval($_POST['id'] ?? 0);
$city_name = trim($_POST['city_name'] ?? '');

if ($id <= 0 || $city_name === '') {
    echo json_encode(["status" => false, "message" => "Invalid data"]);
    exit;
}

$stmt = mysqli_prepare($conn, "UPDATE cities SET city_name=? WHERE id=?");

if (!$stmt) {
    echo json_encode(["status" => false, "message" => mysqli_error($conn)]);
    exit;
}

mysqli_stmt_bind_param($stmt, "si", $city_name, $id);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode(["status" => true, "message" => "City updated successfully"]);
} else {
    echo json_encode(["status" => false, "message" => mysqli_error($conn)]);
}
