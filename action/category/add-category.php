<?php
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('categories', 'create');

header('Content-Type: application/json');
require '../../config/db.php';

$name      = trim($_POST['name'] ?? '');
$is_active = isset($_POST['is_active']) && $_POST['is_active'] == '1' ? 1 : 0;

if ($name === '') {
    echo json_encode(["status" => false, "message" => "Category name is required"]);
    exit;
}

$stmt = $conn->prepare("INSERT INTO categories (name, is_active) VALUES (?, ?)");
$stmt->bind_param("si", $name, $is_active);

if ($stmt->execute()) {
    echo json_encode(["status" => true, "message" => "Category added successfully", "id" => $stmt->insert_id]);
} else {
    echo json_encode(["status" => false, "message" => "Failed to add category"]);
}

$stmt->close();
$conn->close();