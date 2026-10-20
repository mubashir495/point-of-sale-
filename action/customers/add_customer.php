<?php
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('customers', 'create');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    $data = json_decode(file_get_contents("php://input"), true);

    // ── Required Fields ──────────────────────────────────────────
    if (
        empty(trim($data['name']    ?? '')) ||
        empty(trim($data['phone']   ?? '')) ||
        empty($data['city_id']      ?? '') ||
        empty($data['area_id']      ?? '')
    ) {
        echo json_encode([
            "success" => false,
            "message" => "Customer name, phone, city and area are required."
        ]);
        exit;
    }

    // ── Name Validation ──────────────────────────────────────────
    if (strlen(trim($data['name'])) < 3) {
        echo json_encode([
            "success" => false,
            "message" => "Customer name must be at least 3 characters."
        ]);
        exit;
    }

    // ── Phone Validation ─────────────────────────────────────────
    if (!preg_match('/^03[0-9]{9}$/', trim($data['phone']))) {
        echo json_encode([
            "success" => false,
            "message" => "Enter a valid mobile number (03XXXXXXXXX)."
        ]);
        exit;
    }

    // ── Duplicate Phone Check ────────────────────────────────────
    $check = $db->prepare("SELECT id FROM customers WHERE phone = ?");
    $check->execute([trim($data['phone'])]);

    if ($check->rowCount() > 0) {
        echo json_encode([
            "success" => false,
            "message" => "This phone number is already registered."
        ]);
        exit;
    }

    // ── Generate Customer Code ───────────────────────────────────
    $codeStmt = $db->query("SELECT COUNT(*) FROM customers");
    $count     = (int) $codeStmt->fetchColumn();
    $customerCode = 'CUST-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);

    // ── Opening Balance ──────────────────────────────────────────
    $openingBalance = floatval($data['opening_balance'] ?? 0);

    $branchId = rbac_current_branch_id();
    if (rbac_is_branch_scoped() && !$branchId) {
        echo json_encode(["success" => false, "message" => "Your account isn't linked to a branch yet - contact an admin."]);
        exit;
    }

    // ── Insert Customer ──────────────────────────────────────────
    $stmt = $db->prepare("
        INSERT INTO customers
            (customer_code, name, phone, email, city_id, area_id, opening_balance, current_balance, address, branch_id)
        VALUES
            (:customer_code, :name, :phone, :email, :city_id, :area_id, :opening_balance, :current_balance, :address, :branch_id)
    ");

    $stmt->execute([
        ':customer_code'    => $customerCode,
        ':name'             => trim($data['name']),
        ':phone'            => trim($data['phone']),
        ':email'            => trim($data['email']    ?? ''),
        ':city_id'          => intval($data['city_id']),
        ':area_id'          => intval($data['area_id']),
        ':opening_balance'  => $openingBalance,
        ':current_balance'  => $openingBalance,
        ':address'          => trim($data['address']  ?? ''),
        ':branch_id'        => $branchId,
    ]);

    echo json_encode([
        "success"       => true,
        "message"       => "Customer added successfully.",
        "id"            => (int) $db->lastInsertId(),
        "customer_code" => $customerCode
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
?>