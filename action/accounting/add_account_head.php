<?php
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('ledger', 'create');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $name = trim($input['name'] ?? '');
    $type = trim($input['type'] ?? 'expense'); // default new heads = expense type

    $allowedTypes = ['asset', 'liability', 'equity', 'revenue', 'expense'];
    if (!in_array($type, $allowedTypes)) {
        $type = 'expense';
    }

    if ($name === '') {
        echo json_encode(["success" => false, "message" => "Head name is required"]);
        exit;
    }

    $stmt = $db->prepare("INSERT INTO account_heads (name, type, is_locked) VALUES (:name, :type, 0)");
    $stmt->execute([':name' => $name, ':type' => $type]);

    $headId = $db->lastInsertId();

    // Every head gets a default self-named sub-account in chart_of_accounts
    $stmt2 = $db->prepare("INSERT INTO chart_of_accounts (head_id, name, is_locked, is_cash_bank) VALUES (:head_id, :name, 0, 0)");
    $stmt2->execute([':head_id' => $headId, ':name' => $name]);

    echo json_encode(["success" => true, "message" => "Expense head added", "id" => $headId]);

} catch (PDOException $e) {
    if ($e->getCode() == 23000) {
        echo json_encode(["success" => false, "message" => "This head already exists"]);
    } else {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => $e->getMessage()]);
    }
}
