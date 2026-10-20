<?php
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('products', 'view');

header('Content-Type: application/json');
require '../../config/db.php';

$sql = "SELECT * FROM products";
$params = [];
$types = '';

if (rbac_is_branch_scoped()) {
    $branchId = rbac_current_branch_id();
    if (!$branchId) {
        echo json_encode([]);
        exit;
    }
    $sql .= " WHERE branch_id = ?";
    $params[] = $branchId;
    $types .= 'i';
}

$sql .= " ORDER BY id DESC";
$stmt = $conn->prepare($sql);
if ($types) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = [
        "id"              => (int)$row['id'],
        "product_name"    => $row['product_name'],
        "product_code"    => $row['product_code'],
        "category"        => $row['category'],
        "brand"           => $row['brand'],
        "unit"            => $row['unit'],
        "purchase_price"  => (float)$row['purchase_price'],
        "sale_price"      => (float)$row['sale_price'],
        "opening_stock"   => (int)$row['opening_stock'],
        "current_stock"   => (int)$row['current_stock'],
        "minimum_stock"   => (int)$row['minimum_stock'],
        "description"     => $row['description'],
        "product_image"   => $row['product_image'],
        "status"          => $row['status'],
    ];
}

echo json_encode($data);
$conn->close();