<?php
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('categories', 'delete');

header('Content-Type: application/json');
require '../../config/db.php';

$id = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    echo json_encode(["status" => false, "message" => "Invalid category id"]);
    exit;
}

$stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    echo json_encode(["status" => true, "message" => "Category deleted successfully"]);
} else {
    echo json_encode(["status" => false, "message" => "Delete failed"]);
}

$stmt->close();
$conn->close();