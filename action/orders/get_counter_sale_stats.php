<?php
/**
 * action/orders/get_counter_sale_stats.php
 * ------------------------------------------------------------------
 * Powers the "Sales Summary" button on the Sale (POS) page.
 * Returns today's and overall totals for Counter Sale invoices,
 * netted against any Sale Returns recorded against them, so the
 * numbers shown always reflect actual cash-in.
 * ------------------------------------------------------------------
 */
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('sales', 'view');

require_once '../../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    $database = new Database();
    $db = $database->getConnection();

    $branchId = rbac_current_branch_id();
    $branchScoped = rbac_is_branch_scoped() && $branchId;

    $branchSaleFilter = $branchScoped ? " AND o.branch_id = :branch_id" : "";
    $branchReturnFilter = $branchScoped ? " AND sr.branch_id = :branch_id" : "";

    // ---------------- Overall (all-time) ----------------
    $sql = "SELECT COUNT(*) AS total_orders, COALESCE(SUM(o.total), 0) AS total_sale
            FROM orders o
            WHERE o.status = 'Counter'" . $branchSaleFilter;
    $stmt = $db->prepare($sql);
    if ($branchScoped) $stmt->bindValue(':branch_id', $branchId, PDO::PARAM_INT);
    $stmt->execute();
    $overallSale = $stmt->fetch(PDO::FETCH_ASSOC);

    $sql = "SELECT COUNT(*) AS total_returns, COALESCE(SUM(sr.total), 0) AS total_return_amount
            FROM sale_returns sr
            WHERE 1=1" . $branchReturnFilter;
    $stmt = $db->prepare($sql);
    if ($branchScoped) $stmt->bindValue(':branch_id', $branchId, PDO::PARAM_INT);
    $stmt->execute();
    $overallReturns = $stmt->fetch(PDO::FETCH_ASSOC);

    // ---------------- Today ----------------
    $sql = "SELECT COUNT(*) AS total_orders, COALESCE(SUM(o.total), 0) AS total_sale
            FROM orders o
            WHERE o.status = 'Counter' AND DATE(o.order_date) = CURDATE()" . $branchSaleFilter;
    $stmt = $db->prepare($sql);
    if ($branchScoped) $stmt->bindValue(':branch_id', $branchId, PDO::PARAM_INT);
    $stmt->execute();
    $todaySale = $stmt->fetch(PDO::FETCH_ASSOC);

    $sql = "SELECT COUNT(*) AS total_returns, COALESCE(SUM(sr.total), 0) AS total_return_amount
            FROM sale_returns sr
            WHERE sr.return_date = CURDATE()" . $branchReturnFilter;
    $stmt = $db->prepare($sql);
    if ($branchScoped) $stmt->bindValue(':branch_id', $branchId, PDO::PARAM_INT);
    $stmt->execute();
    $todayReturns = $stmt->fetch(PDO::FETCH_ASSOC);

    $todayGross = (float)$todaySale['total_sale'];
    $todayReturnAmt = (float)$todayReturns['total_return_amount'];
    $overallGross = (float)$overallSale['total_sale'];
    $overallReturnAmt = (float)$overallReturns['total_return_amount'];

    echo json_encode([
        'success' => true,
        'today' => [
            'orders'        => (int)$todaySale['total_orders'],
            'gross_sale'    => $todayGross,
            'returns'       => (int)$todayReturns['total_returns'],
            'return_amount' => $todayReturnAmt,
            'net_sale'      => round($todayGross - $todayReturnAmt, 2),
        ],
        'overall' => [
            'orders'        => (int)$overallSale['total_orders'],
            'gross_sale'    => $overallGross,
            'returns'       => (int)$overallReturns['total_returns'],
            'return_amount' => $overallReturnAmt,
            'net_sale'      => round($overallGross - $overallReturnAmt, 2),
        ],
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
