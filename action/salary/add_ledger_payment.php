<?php
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('employees', 'create');

ob_start();

require_once '../../config/database.php';

header("Content-Type: application/json");

$database = new Database();
$pdo = $database->getConnection();

try {
    $input = json_decode(file_get_contents("php://input"), true);

    if (!$input) {
        throw new Exception("Invalid JSON payload");
    }

    $employeeId = (int)($input['employee_id'] ?? 0);
    $amount = (float)($input['amount'] ?? 0);
    $method = trim($input['method'] ?? 'Cash');
    $remarks = trim($input['remarks'] ?? '');

    if ($employeeId <= 0) {
        throw new Exception("employee_id is required");
    }

    if ($amount <= 0) {
        throw new Exception("Please enter a valid amount");
    }

    $pdo->beginTransaction();

    

    $balStmt = $pdo->prepare("SELECT balance, full_name FROM employees WHERE id = :eid FOR UPDATE");
    $balStmt->execute([':eid' => $employeeId]);
    $emp = $balStmt->fetch();
    if (!$emp) throw new Exception("Employee not found");

    $prevBalance = (float)$emp['balance'];
    $newBalance = $prevBalance - $amount;

    $pdo->prepare("UPDATE employees SET balance = :bal WHERE id = :eid")
        ->execute([':bal' => $newBalance, ':eid' => $employeeId]);

    $desc = "Payment ({$method})" . ($remarks !== '' ? " - {$remarks}" : "");

    $pdo->prepare(
        "INSERT INTO employee_ledger
            (employee_id, salary_id, txn_date, description, debit, credit, balance, created_at)
         VALUES
            (:eid, NULL, CURDATE(), :desc, :debit, 0, :balance, NOW())"
    )->execute([
        ':eid' => $employeeId,
        ':desc' => $desc,
        ':debit' => $amount,
        ':balance' => $newBalance,
    ]);

    $pdo->commit();

    ob_clean();
    echo json_encode([
        "success" => true,
        "message" => "Payment of " . number_format($amount, 2) . " recorded for {$emp['full_name']}.",
        "new_balance" => $newBalance,
    ]);
} catch (Exception $e) {

    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    ob_clean();

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}