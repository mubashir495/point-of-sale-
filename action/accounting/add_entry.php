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

    $entry_date = trim($input['entry_date'] ?? date('Y-m-d'));
    $debit_id = (int)($input['debit_account_id'] ?? 0);
    $credit_id = (int)($input['credit_account_id'] ?? 0);
    $amount = (float)($input['amount'] ?? 0);
    $description = trim($input['description'] ?? '');

    if ($debit_id <= 0 || $credit_id <= 0 || $amount <= 0) {
        echo json_encode(["success" => false, "message" => "Invalid input"]);
        exit;
    }

    // Ensure table exists (simple journaling table)
    $createSql = "CREATE TABLE IF NOT EXISTS accounting_transactions (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        entry_date DATE NOT NULL,
        debit_account_id INT UNSIGNED NOT NULL,
        credit_account_id INT UNSIGNED NOT NULL,
        amount DECIMAL(14,2) NOT NULL,
        description VARCHAR(255) DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $db->exec($createSql);

    $stmt = $db->prepare("INSERT INTO accounting_transactions (entry_date, debit_account_id, credit_account_id, amount, description) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$entry_date, $debit_id, $credit_id, $amount, $description]);

    echo json_encode(["success" => true, "message" => "Transaction saved", "id" => $db->lastInsertId()]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
