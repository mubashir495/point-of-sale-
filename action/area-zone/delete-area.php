<?php
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('users', 'delete');

header('Content-Type: application/json');
include "../../config/db.php";

$data = json_decode(file_get_contents("php://input"), true);
$id   = intval($data['id'] ?? 0);

if ($id <= 0) {
    echo json_encode(["status" => false, "message" => "Id is required"]);
    exit;
}

$stmt = mysqli_prepare($conn, "DELETE FROM areas WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $id);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode(["status" => true, "message" => "Area deleted"]);
} else {
    echo json_encode(["status" => false, "message" => mysqli_error($conn)]);
}