<?php
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('ledger', 'view');

require_once '../../config/database.php';
$database = new Database();
$db = $database->getConnection();
header('Content-Type: application/json');

try {
    $from = $_GET['from'] ?? date('Y-m-d', strtotime('-1 year'));
    $to = $_GET['to'] ?? date('Y-m-d');
    
    $data = [];
    $totalDebit = 0;
    $totalCredit = 0;
    
    // Customer Receivable (Asset) – Debit balance
    $stmt = $db->prepare("SELECT COALESCE(SUM(debit), 0) as debit, COALESCE(SUM(credit), 0) as credit FROM customer_ledger WHERE entry_date BETWEEN ? AND ?");
    $stmt->execute([$from, $to]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $debit = (float)$row['debit'];
    $credit = (float)$row['credit'];
    if ($debit || $credit) {
        $data[] = ['account_name' => 'Accounts Receivable', 'head_type' => 'Asset', 'debit' => round($debit, 2), 'credit' => round($credit, 2)];
        $totalDebit += $debit;
        $totalCredit += $credit;
    }
    
    // Supplier Payable (Liability) – Credit balance
    $stmt = $db->prepare("SELECT COALESCE(SUM(debit), 0) as debit, COALESCE(SUM(credit), 0) as credit FROM party_ledger WHERE transaction_date BETWEEN ? AND ?");
    $stmt->execute([$from, $to]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $debit = (float)$row['debit'];
    $credit = (float)$row['credit'];
    if ($debit || $credit) {
        $data[] = ['account_name' => 'Accounts Payable', 'head_type' => 'Liability', 'debit' => round($debit, 2), 'credit' => round($credit, 2)];
        $totalDebit += $debit;
        $totalCredit += $credit;
    }
    
    // Sales Revenue (Revenue) – Credit
    $stmt = $db->prepare("SELECT 0 as debit, COALESCE(SUM(total), 0) as credit FROM orders WHERE order_date BETWEEN ? AND ? AND status IN ('Completed','Counter')");
    $stmt->execute([$from, $to]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $credit = (float)$row['credit'];
    if ($credit) {
        $data[] = ['account_name' => 'Sales Revenue', 'head_type' => 'Revenue', 'debit' => 0, 'credit' => round($credit, 2)];
        $totalCredit += $credit;
    }
    
    // COGS (Expense) – Debit
    $stmt = $db->prepare("SELECT COALESCE(SUM(oi.quantity * p.purchase_price), 0) as debit FROM order_items oi JOIN orders o ON o.id = oi.order_id JOIN products p ON p.id = oi.product_id WHERE o.order_date BETWEEN ? AND ? AND o.status IN ('Completed','Counter')");
    $stmt->execute([$from, $to]);
    $debit = (float)$stmt->fetchColumn();
    if ($debit) {
        $data[] = ['account_name' => 'Cost of Goods Sold', 'head_type' => 'Expense', 'debit' => round($debit, 2), 'credit' => 0];
        $totalDebit += $debit;
    }
    
    // Operating Expenses (Expense) – Debit
    $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE expense_date BETWEEN ? AND ?");
    $stmt->execute([$from, $to]);
    $debit = (float)$stmt->fetchColumn();
    if ($debit) {
        $data[] = ['account_name' => 'Operating Expenses', 'head_type' => 'Expense', 'debit' => round($debit, 2), 'credit' => 0];
        $totalDebit += $debit;
    }
    
    // Inventory (Asset) – Debit
    $stmt = $db->query("SELECT COALESCE(SUM(current_stock * purchase_price), 0) FROM products");
    $debit = (float)$stmt->fetchColumn();
    if ($debit) {
        $data[] = ['account_name' => 'Inventory', 'head_type' => 'Asset', 'debit' => round($debit, 2), 'credit' => 0];
        $totalDebit += $debit;
    }
    
    $balanced = (abs($totalDebit - $totalCredit) < 0.01);
    echo json_encode([
        'success' => true,
        'from' => $from,
        'to' => $to,
        'data' => $data,
        'total_debit' => round($totalDebit, 2),
        'total_credit' => round($totalCredit, 2),
        'balanced' => $balanced,
        'difference' => round($totalDebit - $totalCredit, 2),
        'message' => $balanced ? 'Trial Balance is balanced' : 'Trial Balance is NOT balanced'
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}