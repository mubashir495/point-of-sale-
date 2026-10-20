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

    $name       = trim($input['name'] ?? '');
    $headId     = (int)($input['head_id'] ?? 0);
    $isCashBank = !empty($input['is_cash_bank']) ? 1 : 0;

    if ($name === '' || $headId <= 0) {
        echo json_encode(["success" => false, "message" => "Account name and head are required"]);
        exit;
    }

    $stmt = $db->prepare("INSERT INTO chart_of_accounts (head_id, name, is_locked, is_cash_bank) VALUES (:head_id, :name, 0, :is_cash_bank)");
    $stmt->execute([':head_id' => $headId, ':name' => $name, ':is_cash_bank' => $isCashBank]);

    echo json_encode(["success" => true, "message" => "Expense account added", "id" => $db->lastInsertId()]);

} catch (PDOException $e) {
    if ($e->getCode() == 23000) {
        echo json_encode(["success" => false, "message" => "This account already exists under the selected head"]);
    } else {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => $e->getMessage()]);
    }
}
