<?php
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('customers', 'edit');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, PUT');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    $data = json_decode(file_get_contents("php://input"), true);

    // ── Customer ID ──────────────────────────────────────────────
    if (empty($data['id'])) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Customer ID is required."
        ]);
        exit;
    }

    // ── Required Fields ──────────────────────────────────────────
    if (
        empty(trim($data['name']    ?? '')) ||
        empty(trim($data['phone']   ?? '')) ||
        empty($data['city_id']      ?? '') ||
        empty($data['area_id']      ?? '')
    ) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Customer name, phone, city and area are required."
        ]);
        exit;
    }

    // ── Name Validation ──────────────────────────────────────────
    if (strlen(trim($data['name'])) < 3) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Customer name must be at least 3 characters."
        ]);
        exit;
    }

    // ── Phone Validation ─────────────────────────────────────────
    if (!preg_match('/^03[0-9]{9}$/', trim($data['phone']))) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Enter a valid mobile number (03XXXXXXXXX)."
        ]);
        exit;
    }

    // ── Duplicate Phone Check (exclude current customer) ─────────
    $check = $db->prepare("SELECT id FROM customers WHERE phone = :phone AND id != :id");
    $check->execute([
        ':phone' => trim($data['phone']),
        ':id'    => intval($data['id'])
    ]);

    if ($check->fetch()) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "This phone number is already registered to another customer."
        ]);
        exit;
    }

    // ── Update Customer ──────────────────────────────────────────
    $stmt = $db->prepare("
        UPDATE customers SET
            name            = :name,
            phone           = :phone,
            email           = :email,
            city_id         = :city_id,
            area_id         = :area_id,
            opening_balance = :opening_balance,
            address         = :address,
            updated_at      = NOW()
        WHERE id = :id
    ");

    $stmt->execute([
        ':id'               => intval($data['id']),
        ':name'             => trim($data['name']),
        ':phone'            => trim($data['phone']),
        ':email'            => trim($data['email']    ?? ''),
        ':city_id'          => intval($data['city_id']),
        ':area_id'          => intval($data['area_id']),
        ':opening_balance'  => floatval($data['opening_balance'] ?? 0),
        ':address'          => trim($data['address']  ?? ''),
    ]);

    echo json_encode([
        "success" => true,
        "message" => "Customer updated successfully."
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
?>