<?php
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('products', 'view');

header('Content-Type: application/json');
require '../../config/db.php';

$result = $conn->query("SELECT id, name, is_active, created_at FROM brands ORDER BY id DESC");

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = [
        "id"        => (int)$row['id'],
        "name"      => $row['name'],
        "is_active" => (int)$row['is_active'],
        "created_at"=> $row['created_at'],
    ];
}

echo json_encode($data);
$conn->close();
