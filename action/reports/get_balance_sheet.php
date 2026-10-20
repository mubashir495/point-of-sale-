<?php
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('reports', 'view');

/**
 * Balance Sheet API
 *
 * Endpoint: action/reports/get_balance_sheet.php?from=YYYY-MM-DD&to=YYYY-MM-DD
 * Method: GET
 *
 * Returns JSON with assets, liabilities, equity, and totals.
 */

// Include your database class
require_once '../../config/database.php';

// Create instance and get connection
$database = new Database();
$db = $database->getConnection();

// Helper: validate date
function is_valid_date($date) {
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

header('Content-Type: application/json');

try {
    // Get date range from query parameters
    $from = isset($_GET['from']) && is_valid_date($_GET['from']) ? $_GET['from'] : date('Y-m-d', strtotime('-1 year'));
    $to = isset($_GET['to']) && is_valid_date($_GET['to']) ? $_GET['to'] : date('Y-m-d');

    // ---------- ASSETS ----------

    // 1. Inventory (Current Stock Value)
    $inventory = getInventoryValue($db);

    // 2. Accounts Receivable (Customer Outstanding)
    $receivables = getCustomerOutstanding($db, $from, $to);

    // 3. Cash in Hand & Bank Account (from chart_of_accounts where is_cash_bank = 1)
    // Note: Since the provided schema does not have a ledger for cash/bank accounts,
    // we return 0 as per the requirement.
    $cash = 0;
    $bank = 0;

    // ---------- LIABILITIES ----------

    // 4. Accounts Payable (Supplier Outstanding)
    $payables = getSupplierOutstanding($db, $from, $to);

    // 5. Loans (from chart_of_accounts where name = 'Loan' or similar)
    // Not available in the provided schema, return 0.
    $loans = 0;

    // ---------- EQUITY ----------

    // 6. Current Year Profit
    $profit = getCurrentYearProfit($db, $from, $to);

    // 7. Capital (from chart_of_accounts where name = 'Capital')
    // Not available in the provided schema, return 0.
    $capital = 0;

    // 8. Owner Equity (from chart_of_accounts where name = 'Owner Equity')
    // Not available in the provided schema, return 0.
    $ownerEquity = 0;

    // Build response arrays
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

    // Calculate totals
    $totalAssets = array_sum(array_column($assets, 'amount'));
    $totalLiabilities = array_sum(array_column($liabilities, 'amount'));
    $totalEquity = array_sum(array_column($equity, 'amount'));
    $totalLiabilitiesEquity = $totalLiabilities + $totalEquity;

    // Verify balance sheet rule
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

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

// ---------- HELPER FUNCTIONS ----------

/**
 * Get total inventory value (current_stock * purchase_price)
 */
function getInventoryValue($db) {
    $sql = "SELECT COALESCE(SUM(current_stock * purchase_price), 0) AS total_value FROM products";
    $stmt = $db->query($sql);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return (float) $result['total_value'];
}

/**
 * Get customer outstanding (SUM(debit - credit))
 */
function getCustomerOutstanding($db, $from, $to) {
    $sql = "SELECT COALESCE(SUM(debit - credit), 0) AS outstanding
            FROM customer_ledger
            WHERE entry_date <= :to";
    $stmt = $db->prepare($sql);
    $stmt->execute([':to' => $to]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return (float) $result['outstanding'];
}

/**
 * Get supplier outstanding (SUM(credit - debit))
 */
function getSupplierOutstanding($db, $from, $to) {
    $sql = "SELECT COALESCE(SUM(credit - debit), 0) AS outstanding
            FROM party_ledger
            WHERE transaction_date <= :to";
    $stmt = $db->prepare($sql);
    $stmt->execute([':to' => $to]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return (float) $result['outstanding'];
}

/**
 * Calculate Current Year Profit = Sales - COGS - Expenses
 */
function getCurrentYearProfit($db, $from, $to) {
    $sales = getSalesRevenue($db, $from, $to);
    $cogs = getCOGS($db, $from, $to);
    $expenses = getTotalExpenses($db, $from, $to);
    return $sales - $cogs - $expenses;
}

/**
 * Get sales revenue from orders (status: Completed, Counter)
 */
function getSalesRevenue($db, $from, $to) {
    $sql = "SELECT COALESCE(SUM(total), 0) AS revenue
            FROM orders
            WHERE order_date BETWEEN :from AND :to
              AND status IN ('Completed', 'Counter')";
    $stmt = $db->prepare($sql);
    $stmt->execute([':from' => $from, ':to' => $to]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return (float) $result['revenue'];
}

/**
 * Get COGS (Cost of Goods Sold) = SUM(order_items.quantity * products.purchase_price)
 */
function getCOGS($db, $from, $to) {
    $sql = "SELECT COALESCE(SUM(oi.quantity * p.purchase_price), 0) AS cogs
            FROM order_items oi
            JOIN orders o ON o.id = oi.order_id
            JOIN products p ON p.id = oi.product_id
            WHERE o.order_date BETWEEN :from AND :to
              AND o.status IN ('Completed', 'Counter')";
    $stmt = $db->prepare($sql);
    $stmt->execute([':from' => $from, ':to' => $to]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return (float) $result['cogs'];
}

/**
 * Get total expenses
 */
function getTotalExpenses($db, $from, $to) {
    $sql = "SELECT COALESCE(SUM(amount), 0) AS total_expenses
            FROM expenses
            WHERE expense_date BETWEEN :from AND :to";
    $stmt = $db->prepare($sql);
    $stmt->execute([':from' => $from, ':to' => $to]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return (float) $result['total_expenses'];
}