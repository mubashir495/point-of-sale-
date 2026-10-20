<?php
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('products', 'edit');

header('Content-Type: application/json');
require '../../config/db.php';

$id        = (int)($_POST['id'] ?? 0);
$is_active = isset($_POST['is_active']) && $_POST['is_active'] == '1' ? 1 : 0;

if ($id <= 0) {
    echo json_encode(["status" => false, "message" => "Invalid brand id"]);
    exit;
}

$stmt = $conn->prepare("UPDATE brands SET is_active = ? WHERE id = ?");
$stmt->bind_param("ii", $is_active, $id);

if ($stmt->execute()) {
    echo json_encode(["status" => true, "message" => "Status updated"]);
} else {
    echo json_encode(["status" => false, "message" => "Failed to update status"]);
}

$stmt->close();
$conn->close();
