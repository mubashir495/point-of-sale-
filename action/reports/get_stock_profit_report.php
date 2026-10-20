<?php
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('reports', 'view');

header('Content-Type: application/json');
require_once '../../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    $sql = "
        SELECT
            p.id,
            p.product_name,
            p.product_code,
            p.purchase_price,
            p.sale_price,
            p.current_stock,
            p.minimum_stock,
            COALESCE(sold.total_qty, 0)      AS total_sold,
            COALESCE(sold.total_revenue, 0)  AS total_revenue
        FROM products p
        LEFT JOIN (
            SELECT
                oi.product_id,
                SUM(oi.quantity)                AS total_qty,
                SUM(oi.quantity * oi.unit_price) AS total_revenue
            FROM order_items oi
            INNER JOIN orders o ON o.id = oi.order_id
            WHERE o.status = 'Complete'
            GROUP BY oi.product_id
        ) sold ON sold.product_id = p.id
        WHERE p.status = 'active'
        ORDER BY p.product_name ASC
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $report = [];
    $grandRevenue = 0;
    $grandCost = 0;
    $grandProfit = 0;
    $lowStockCount = 0;

    foreach ($rows as $r) {
        $qtySold      = (int)$r['total_sold'];
        $purchasePrice = (float)$r['purchase_price'];
        $revenue      = (float)$r['total_revenue'];
        $cost         = $qtySold * $purchasePrice;
        $profit       = $revenue - $cost;
        $currentStock = (int)$r['current_stock'];
        $minStock     = (int)$r['minimum_stock'];
        $isLowStock   = $currentStock <= $minStock;

        if ($isLowStock) {
            $lowStockCount++;
        }

        $grandRevenue += $revenue;
        $grandCost    += $cost;
        $grandProfit  += $profit;

        $report[] = [
            'id'             => $r['id'],
            'product_name'   => $r['product_name'],
            'product_code'   => $r['product_code'],
            'purchase_price' => $purchasePrice,
            'sale_price'     => (float)$r['sale_price'],
            'current_stock'  => $currentStock,
            'minimum_stock'  => $minStock,
            'low_stock'      => $isLowStock,
            'total_sold'     => $qtySold,
            'total_revenue'  => $revenue,
            'total_cost'     => $cost,
            'total_profit'   => $profit,
        ];
    }

    echo json_encode([
        'success' => true,
        'products' => $report,
        'summary' => [
            'total_revenue'   => $grandRevenue,
            'total_cost'      => $grandCost,
            'total_profit'    => $grandProfit,
            'low_stock_count' => $lowStockCount,
        ],
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}