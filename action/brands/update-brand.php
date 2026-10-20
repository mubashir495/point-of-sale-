<?php
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('products', 'edit');

header('Content-Type: application/json');
require '../../config/db.php';

$id        = (int)($_POST['id'] ?? 0);
$name      = trim($_POST['name'] ?? '');
$is_active = isset($_POST['is_active']) && $_POST['is_active'] == '1' ? 1 : 0;

if ($id <= 0 || $name === '') {
    echo json_encode(["status" => false, "message" => "Invalid data"]);
    exit;
}

$stmt = $conn->prepare("UPDATE brands SET name = ?, is_active = ? WHERE id = ?");
$stmt->bind_param("sii", $name, $is_active, $id);

if ($stmt->execute()) {
    echo json_encode(["status" => true, "message" => "Brand updated successfully"]);
} else {
    echo json_encode(["status" => false, "message" => "Update failed"]);
}

$stmt->close();
$conn->close();
