<?php
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('users', 'delete');

header('Content-Type: application/json');
include "../../config/db.php";

$id = intval($_POST['id'] ?? 0);

if ($id <= 0) {
    echo json_encode(["status" => false, "message" => "Invalid city id"]);
    exit;
}

$stmt = mysqli_prepare($conn, "DELETE FROM cities WHERE id=?");

if (!$stmt) {
    echo json_encode(["status" => false, "message" => mysqli_error($conn)]);
    exit;
}

mysqli_stmt_bind_param($stmt, "i", $id);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode(["status" => true, "message" => "City deleted successfully"]);
} else {
    echo json_encode(["status" => false, "message" => mysqli_error($conn)]);
}
