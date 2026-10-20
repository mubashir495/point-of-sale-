<?php
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('employees', 'view');

ob_start();

require_once '../../config/database.php';

header("Content-Type: application/json");

$database = new Database();
$pdo = $database->getConnection();

try {
    $period = isset($_GET['period']) ? trim($_GET['period']) : date('Y-m');
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';

    if (!preg_match('/^\d{4}-\d{2}$/', $period)) {
        throw new Exception("Invalid period format, expected YYYY-MM");
    }

    $sql = "SELECT s.id, s.employee_id, s.period, s.basic, s.incentive, s.deduction,
                   s.deduction_reason, s.net_payable, s.paid_amount, s.status,
                   s.payment_method, s.remarks, s.paid_date,
                   e.full_name, e.designation, e.contact, e.shift
            FROM salaries s
            JOIN employees e ON e.id = s.employee_id
            WHERE s.period = :period";
    $params = [':period' => $period];

    if ($search !== '') {
        $sql .= " AND (e.full_name LIKE :search OR e.designation LIKE :search OR e.contact LIKE :search)";
        $params[':search'] = "%{$search}%";
    }

    $sql .= " ORDER BY e.full_name ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $totals = [
        'basic' => 0, 'deduction' => 0, 'incentive' => 0, 'gross' => 0, 'paid' => 0,
    ];
    foreach ($rows as $r) {
        $totals['basic'] += (float)$r['basic'];
        $totals['deduction'] += (float)$r['deduction'];
        $totals['incentive'] += (float)$r['incentive'];
        $totals['gross'] += (float)$r['net_payable'];
        $totals['paid'] += (float)$r['paid_amount'];
    }

    ob_clean();
    echo json_encode(["success" => true, "period" => $period, "data" => $rows, "totals" => $totals]);
} catch (Exception $e) {
    ob_clean();
    http_response_code(400);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
