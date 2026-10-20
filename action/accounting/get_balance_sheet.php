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
    
    // Cash & Bank – no ledger, return 0
    $cash = 0;
    $bank = 0;
    
    // Accounts Receivable
    $stmt = $db->prepare("SELECT COALESCE(SUM(debit - credit), 0) FROM customer_ledger WHERE entry_date <= ?");
    $stmt->execute([$to]);
    $receivables = (float)$stmt->fetchColumn();
    
    // Inventory
    $stmt = $db->query("SELECT COALESCE(SUM(current_stock * purchase_price), 0) FROM products");
    $inventory = (float)$stmt->fetchColumn();
    
    // Accounts Payable
    $stmt = $db->prepare("SELECT COALESCE(SUM(credit - debit), 0) FROM party_ledger WHERE transaction_date <= ?");
    $stmt->execute([$to]);
    $payables = (float)$stmt->fetchColumn();
    
    $loans = 0; // no loan data
    $capital = 0;
    $ownerEquity = 0;
    
    // Current Year Profit
    $stmt = $db->prepare("SELECT COALESCE(SUM(total), 0) FROM orders WHERE order_date BETWEEN ? AND ? AND status IN ('Completed','Counter')");
    $stmt->execute([$from, $to]);
    $revenue = (float)$stmt->fetchColumn();
    
    $stmt = $db->prepare("SELECT COALESCE(SUM(oi.quantity * p.purchase_price), 0) FROM order_items oi JOIN orders o ON o.id = oi.order_id JOIN products p ON p.id = oi.product_id WHERE o.order_date BETWEEN ? AND ? AND o.status IN ('Completed','Counter')");
    $stmt->execute([$from, $to]);
    $cogs = (float)$stmt->fetchColumn();
    
    $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE expense_date BETWEEN ? AND ?");
    $stmt->execute([$from, $to]);
    $expenses = (float)$stmt->fetchColumn();
    
    $profit = $revenue - $cogs - $expenses;
    
    $assets = [
        ['account' => 'Cash in Hand', 'amount' => round($cash, 2)],
        ['account' => 'Bank Account', 'amount' => round($bank, 2)],
        ['account' => 'Accounts Receivable', 'amount' => round($receivables, 2)],
        ['account' => 'Inventory / Stock', 'amount' => round($inventory, 2)],
    ];
    $liabilities = [
        ['account' => 'Accounts Payable', 'amount' => round($payables, 2)],
        ['account' => 'Loans', 'amount' => round($loans, 2)],
    ];
    $equity = [
        ['account' => 'Capital', 'amount' => round($capital, 2)],
        ['account' => 'Owner Equity', 'amount' => round($ownerEquity, 2)],
        ['account' => 'Current Year Profit', 'amount' => round($profit, 2)],
    ];
    
    $totalAssets = array_sum(array_column($assets, 'amount'));
    $totalLiabilities = array_sum(array_column($liabilities, 'amount'));
    $totalEquity = array_sum(array_column($equity, 'amount'));
    $totalLiabilitiesEquity = $totalLiabilities + $totalEquity;
    $balanced = (abs($totalAssets - $totalLiabilitiesEquity) < 0.01);
    
    echo json_encode([
        'success' => true,
        'from' => $from,
        'to' => $to,
        'assets' => $assets,
        'liabilities' => $liabilities,
        'equity' => $equity,
        'total_assets' => round($totalAssets, 2),
        'total_liabilities' => round($totalLiabilities, 2),
        'total_equity' => round($totalEquity, 2),
        'total_liabilities_equity' => round($totalLiabilitiesEquity, 2),
        'balanced' => $balanced,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}