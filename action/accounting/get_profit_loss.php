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
    
    $stmt = $db->prepare("SELECT COALESCE(SUM(total), 0) FROM orders WHERE order_date BETWEEN ? AND ? AND status IN ('Completed','Counter')");
    $stmt->execute([$from, $to]);
    $revenue = (float)$stmt->fetchColumn();
    
    $stmt = $db->prepare("SELECT COALESCE(SUM(oi.quantity * p.purchase_price), 0) FROM order_items oi JOIN orders o ON o.id = oi.order_id JOIN products p ON p.id = oi.product_id WHERE o.order_date BETWEEN ? AND ? AND o.status IN ('Completed','Counter')");
    $stmt->execute([$from, $to]);
    $cogs = (float)$stmt->fetchColumn();
    
    $grossProfit = $revenue - $cogs;
    
    $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE expense_date BETWEEN ? AND ?");
    $stmt->execute([$from, $to]);
    $expenses = (float)$stmt->fetchColumn();
    
    $netProfit = $grossProfit - $expenses;
    
    echo json_encode([
        'success' => true,
        'from' => $from,
        'to' => $to,
        'revenue' => round($revenue, 2),
        'cogs' => round($cogs, 2),
        'gross_profit' => round($grossProfit, 2),
        'operating_expenses' => round($expenses, 2),
        'net_profit' => round($netProfit, 2),
        'profit_margin' => $revenue > 0 ? round(($netProfit / $revenue) * 100, 2) : 0
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}