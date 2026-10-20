<?php
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('products', 'edit');

header('Content-Type: application/json');
require '../../config/db.php';

$id     = (int)($_POST['id'] ?? 0);
$status = ($_POST['status'] ?? 'Active') === 'Inactive' ? 'Inactive' : 'Active';

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

$stmt = $conn->prepare("UPDATE products SET status = ? WHERE id = ?");
$stmt->bind_param("si", $status, $id);

if ($stmt->execute()) {
    echo json_encode(["status" => true, "message" => "Status updated"]);
} else {
    echo json_encode(["status" => false, "message" => "Failed to update status"]);
}

$stmt->close();
$conn->close();