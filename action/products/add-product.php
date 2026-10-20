<?php
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('products', 'create');

header('Content-Type: application/json');
require '../../config/db.php';

$product_name   = trim($_POST['product_name'] ?? '');
$product_code   = trim($_POST['product_code'] ?? '');
$category_id    = (int)($_POST['category'] ?? 0);
$brand_id       = (int)($_POST['brand'] ?? 0);
$unit           = trim($_POST['unit'] ?? '');
$purchase_price = (float)($_POST['purchase_price'] ?? 0);
$sale_price     = (float)($_POST['sale_price'] ?? 0);
$opening_stock  = (int)($_POST['opening_stock'] ?? 0);
$minimum_stock  = (int)($_POST['minimum_stock'] ?? 0);
$description    = trim($_POST['description'] ?? '');
$status         = ($_POST['status'] ?? 'Active') === 'Inactive' ? 'Inactive' : 'Active';

if ($product_name === '' || $category_id <= 0 || $unit === '') {
    echo json_encode(["status" => false, "message" => "Product name, category and unit are required"]);
    exit;
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

// current_stock starts equal to opening_stock on creation
$current_stock = $opening_stock;
$branch_id = rbac_current_branch_id();

if (rbac_is_branch_scoped() && !$branch_id) {
    echo json_encode(["status" => false, "message" => "Your account isn't linked to a branch yet - contact an admin."]);
    exit;
}

// ── Handle image upload ──
$product_image = null;
if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = '../../uploads/products/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    $ext = strtolower(pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
    if (in_array($ext, $allowed)) {
        $fileName = 'prod_' . time() . '_' . uniqid() . '.' . $ext;
        if (move_uploaded_file($_FILES['product_image']['tmp_name'], $uploadDir . $fileName)) {
            $product_image = $fileName;
        }
    }
}

$stmt = $conn->prepare("INSERT INTO products
    (product_name, product_code, category, brand, unit, purchase_price, sale_price, opening_stock, current_stock, minimum_stock, description, product_image, status, branch_id)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");

$stmt->bind_param(
    "sssssddiiisssi",
    $product_name,
    $product_code,
    $category_name,
    $brand,
    $unit,
    $purchase_price,
    $sale_price,
    $opening_stock,
    $current_stock,
    $minimum_stock,
    $description,
    $product_image,
    $status,
    $branch_id
);

if ($stmt->execute()) {
    echo json_encode(["status" => true, "message" => "Product added successfully", "id" => $stmt->insert_id]);
} else {
    if ($conn->errno === 1062) {
        echo json_encode(["status" => false, "message" => "Product code already exists"]);
    } else {
        echo json_encode(["status" => false, "message" => "Failed to add product"]);
    }
}

$stmt->close();
$conn->close();
?>