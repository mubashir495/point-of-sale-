<?php
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('employees', 'edit');

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
    $salaryId = (int)($input['salary_id'] ?? 0);
    $amount = (float)($input['amount'] ?? 0);
    $method = trim($input['method'] ?? 'Cash');

    if ($salaryId <= 0) throw new Exception("salary_id is required");
    if ($amount <= 0) throw new Exception("Please enter a valid amount");

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT * FROM salaries WHERE id = :id FOR UPDATE");
    $stmt->execute([':id' => $salaryId]);
    $salary = $stmt->fetch();
    if (!$salary) throw new Exception("Salary record not found");

    $employeeId = (int)$salary['employee_id'];
    $remainingDue = (float)$salary['net_payable'] - (float)$salary['paid_amount'];

    if ($amount > $remainingDue + 0.01) {
        throw new Exception("Amount exceeds due amount (" . number_format($remainingDue, 2) . ")");
    }

    $newPaid = (float)$salary['paid_amount'] + $amount;
    $newStatus = ($newPaid >= (float)$salary['net_payable'] - 0.01) ? 'Paid' : 'Partial';

    $upd = $pdo->prepare(
        "UPDATE salaries SET paid_amount = :paid, status = :status, payment_method = :method, paid_date = CURDATE()
         WHERE id = :id"
    );
    $upd->execute([
        ':paid' => $newPaid,
        ':status' => $newStatus,
        ':method' => $method,
        ':id' => $salaryId,
    ]);

    $balStmt = $pdo->prepare("SELECT balance, full_name FROM employees WHERE id = :eid FOR UPDATE");
    $balStmt->execute([':eid' => $employeeId]);
    $emp = $balStmt->fetch();
    $prevBalance = $emp ? (float)$emp['balance'] : 0;
    $newBalance = $prevBalance - $amount;

    $pdo->prepare("UPDATE employees SET balance = :bal WHERE id = :eid")
        ->execute([':bal' => $newBalance, ':eid' => $employeeId]);

    $pdo->prepare(
        "INSERT INTO employee_ledger
            (employee_id, salary_id, txn_date, description, debit, credit, balance, created_at)
         VALUES
            (:eid, :sid, CURDATE(), :desc, :debit, 0, :balance, NOW())"
    )->execute([
        ':eid' => $employeeId,
        ':sid' => $salaryId,
        ':desc' => "Salary Payment - " . $salary['period'] . " ({$method})",
        ':debit' => $amount,
        ':balance' => $newBalance,
    ]);

    $pdo->commit();

    ob_clean();
    echo json_encode([
        "success" => true,
        "message" => "Payment of " . number_format($amount, 2) . " recorded.",
        "status" => $newStatus,
        "paid_amount" => $newPaid,
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    ob_clean();
    http_response_code(400);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
