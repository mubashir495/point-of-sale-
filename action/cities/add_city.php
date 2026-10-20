<?php
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('users', 'create');

header('Content-Type: application/json');
include "../../config/db.php";

$city_name = trim($_POST['city_name'] ?? '');

if ($city_name === '') {
    echo json_encode(["status" => false, "message" => "City name is required"]);
    exit;
}

$stmt = mysqli_prepare($conn,
    "INSERT INTO cities (city_name) VALUES (?)"
);

if (!$stmt) {
    echo json_encode(["status" => false, "message" => mysqli_error($conn)]);
    exit;
}

mysqli_stmt_bind_param($stmt, "s", $city_name);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode(["status" => true, "message" => "City added successfully", "id" => mysqli_insert_id($conn)]);
} else {
    echo json_encode(["status" => false, "message" => mysqli_error($conn)]);
}
