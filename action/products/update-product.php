<?php
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('products', 'edit');

header('Content-Type: application/json');
require '../../config/db.php';

$id             = (int)($_POST['id'] ?? 0);
$product_name   = trim($_POST['product_name'] ?? '');
$product_code   = trim($_POST['product_code'] ?? '');
$category_id    = (int)($_POST['category'] ?? 0);
$brand_id       = (int)($_POST['brand'] ?? 0);
$unit           = trim($_POST['unit'] ?? '');
$purchase_price = (float)($_POST['purchase_price'] ?? 0);
$sale_price     = (float)($_POST['sale_price'] ?? 0);
$current_stock  = (int)($_POST['current_stock'] ?? 0);
$minimum_stock  = (int)($_POST['minimum_stock'] ?? 0);
$description    = trim($_POST['description'] ?? '');
$status         = ($_POST['status'] ?? 'Active') === 'Inactive' ? 'Inactive' : 'Active';

if ($id <= 0 || $product_name === '' || $category_id <= 0 || $unit === '') {
    echo json_encode(["status" => false, "message" => "Invalid data"]);
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

// Get category name from category_id
$catStmt = $conn->prepare("SELECT name FROM categories WHERE id = ?");
$catStmt->bind_param("i", $category_id);
$catStmt->execute();
$catResult = $catStmt->get_result();

if ($catResult->num_rows === 0) {
    echo json_encode(["status" => false, "message" => "Invalid category selected"]);
    $catStmt->close();
    $conn->close();
    exit;
}

$catRow = $catResult->fetch_assoc();
$category_name = $catRow['name'];
$catStmt->close();

// Get brand name from brand_id (brand is optional)
$brand = '';
if ($brand_id > 0) {
    $brandStmt = $conn->prepare("SELECT name FROM brands WHERE id = ?");
    $brandStmt->bind_param("i", $brand_id);
    $brandStmt->execute();
    $brandResult = $brandStmt->get_result();
    if ($brandResult->num_rows > 0) {
        $brand = $brandResult->fetch_assoc()['name'];
    }
    $brandStmt->close();
}

// ── Handle new image upload (optional) ──
$imageSql = "";
$fileName = null;
if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = '../../uploads/products/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    $ext = strtolower(pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
    if (in_array($ext, $allowed)) {
        $fileName = 'prod_' . time() . '_' . uniqid() . '.' . $ext;
        move_uploaded_file($_FILES['product_image']['tmp_name'], $uploadDir . $fileName);
    }
}

if ($fileName) {
    $stmt = $conn->prepare("UPDATE products SET
        product_name=?, product_code=?, category=?, brand=?, unit=?, purchase_price=?, sale_price=?,
        current_stock=?, minimum_stock=?, description=?, product_image=?, status=? WHERE id=?");
    $stmt->bind_param(
        "sssssddiisssi",
        $product_name, $product_code, $category_name, $brand, $unit,
        $purchase_price, $sale_price, $current_stock, $minimum_stock,
        $description, $fileName, $status, $id
    );
} else {
    $stmt = $conn->prepare("UPDATE products SET
        product_name=?, product_code=?, category=?, brand=?, unit=?, purchase_price=?, sale_price=?,
        current_stock=?, minimum_stock=?, description=?, status=? WHERE id=?");
    $stmt->bind_param(
        "sssssddiissi",
        $product_name, $product_code, $category_name, $brand, $unit,
        $purchase_price, $sale_price, $current_stock, $minimum_stock,
        $description, $status, $id
    );
}

if ($stmt->execute()) {
    echo json_encode(["status" => true, "message" => "Product updated successfully"]);
} else {
    if ($conn->errno === 1062) {
        echo json_encode(["status" => false, "message" => "Product code already exists"]);
    } else {
        echo json_encode(["status" => false, "message" => "Update failed"]);
    }
}

$stmt->close();
$conn->close();
?>