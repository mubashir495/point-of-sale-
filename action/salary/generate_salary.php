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

    $period = trim($input['period'] ?? '');
    $employees = $input['employees'] ?? [];

    if ($period == '') {
        throw new Exception("Period is required");
    }

    if (!is_array($employees) || count($employees) == 0) {
        throw new Exception("No employees received");
    }

    $pdo->beginTransaction();

    $checkStmt = $pdo->prepare("
        SELECT id
        FROM salaries
        WHERE employee_id = :eid
        AND period = :period
    ");

    $insSalaryStmt = $pdo->prepare("
        INSERT INTO salaries
        (
            employee_id,
            period,
            basic,
            incentive,
            deduction,
            deduction_reason,
            net_payable,
            paid_amount,
            status,
            remarks,
            created_at
        )
        VALUES
        (
            :eid,
            :period,
            :basic,
            :incentive,
            :deduction,
            :ded_reason,
            :net,
            0,
            'Pending',
            :remarks,
            NOW()
        )
    ");

    $balStmt = $pdo->prepare("
        SELECT balance
        FROM employees
        WHERE id = :eid
        FOR UPDATE
    ");

    $updBalStmt = $pdo->prepare("
        UPDATE employees
        SET balance = balance + :net
        WHERE id = :eid
    ");

    $ledgerStmt = $pdo->prepare("
        INSERT INTO employee_ledger
        (
            employee_id,
            salary_id,
            txn_date,
            description,
            debit,
            credit,
            balance,
            created_at
        )
        VALUES
        (
            :eid,
            :sid,
            CURDATE(),
            :desc,
            0,
            :credit,
            :balance,
            NOW()
        )
    ");

    $processed = 0;
    $skipped = 0;

    foreach ($employees as $emp) {

        $eid = (int)($emp['employee_id'] ?? 0);

        if ($eid <= 0) {
            $skipped++;
            continue;
        }

        $checkStmt->execute([
            ':eid' => $eid,
            ':period' => $period
        ]);

        if ($checkStmt->fetch()) {
            $skipped++;
            continue;
        }

        $basic = (float)($emp['basic'] ?? 0);
        $incentive = (float)($emp['incentive'] ?? 0);
        $deduction = (float)($emp['deduction'] ?? 0);

        $dedReason = trim($emp['deduction_reason'] ?? '');
        $remarks = trim($emp['remarks'] ?? '');

        $net = $basic + $incentive - $deduction;

        $insSalaryStmt->execute([
            ':eid' => $eid,
            ':period' => $period,
            ':basic' => $basic,
            ':incentive' => $incentive,
            ':deduction' => $deduction,
            ':ded_reason' => $dedReason,
            ':net' => $net,
            ':remarks' => $remarks
        ]);

        $salaryId = $pdo->lastInsertId();

        $balStmt->execute([
            ':eid' => $eid
        ]);

        $balanceRow = $balStmt->fetch();

        $previousBalance = $balanceRow ? (float)$balanceRow['balance'] : 0;
        $newBalance = $previousBalance + $net;

        $updBalStmt->execute([
            ':net' => $net,
            ':eid' => $eid
        ]);

        $ledgerStmt->execute([
            ':eid' => $eid,
            ':sid' => $salaryId,
            ':desc' => "Salary Generated - {$period}",
            ':credit' => $net,
            ':balance' => $newBalance
        ]);

        $processed++;
    }

    $pdo->commit();

    ob_clean();

    echo json_encode([
        "success" => true,
        "message" => "Generated {$processed} salaries. Skipped {$skipped}.",
        "processed" => $processed,
        "skipped" => $skipped
    ]);

} catch (Exception $e) {

    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    ob_clean();

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}