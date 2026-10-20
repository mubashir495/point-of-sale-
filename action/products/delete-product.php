<?php
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('products', 'delete');

header('Content-Type: application/json');
require '../../config/db.php';

$id = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    echo json_encode(["status" => false, "message" => "Invalid product id"]);
    exit;
}

if (rbac_is_branch_scoped()) {
    $ownerCheck = $conn->prepare("SELECT branch_id FROM products WHERE id = ?");
    $ownerCheck->bind_param("i", $id);
    $ownerCheck->execute();
    $ownerRow = $ownerCheck->get_result()->fetch_assoc();
    $ownerCheck->close();
    if (!$ownerRow || (int) $ownerRow['branch_id'] !== rbac_current_branch_id()) {
        echo json_encode(["status" => false, "message" => "Product not found in your branch."]);
        exit;
    }
}

$stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    echo json_encode(["status" => true, "message" => "Product deleted successfully"]);
} else {
    echo json_encode(["status" => false, "message" => "Delete failed"]);
}

$stmt->close();
$conn->close();