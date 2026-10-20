<?php
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('sales', 'view');

require_once '../../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {

    $database = new Database();
    $db = $database->getConnection();

    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $shift  = isset($_GET['shift']) ? trim($_GET['shift']) : '';

    $sql = "
        SELECT
            e.id AS employee_id,
            e.full_name,
            e.designation,
            e.contact,
            e.shift AS employee_shift,

            o.id AS order_id,
            o.order_no,
            o.order_date,
            o.delivery_date,
            o.status,
            o.total,

            c.id AS customer_id,
            c.name AS customer_name

        FROM orders o

        INNER JOIN employees e
            ON e.id = o.employee_id

        INNER JOIN customers c
            ON c.id = o.customer_id

        WHERE o.employee_id IS NOT NULL
    ";

    $params = [];

    if (!empty($search)) {
        $sql .= " AND (
                    e.full_name LIKE ?
                    OR o.order_no LIKE ?
                    OR c.name LIKE ?
                )";

        $like = "%{$search}%";

        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    if (!empty($shift)) {
        $sql .= " AND e.shift = ?";
        $params[] = $shift;
    }

    $sql .= " ORDER BY e.full_name ASC, o.order_date DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Load items for every order
    foreach ($rows as &$row) {

        $itemStmt = $db->prepare("
            SELECT
                p.product_name,
                oi.quantity,
                oi.unit_price,
                oi.total
            FROM order_items oi
            INNER JOIN products p
                ON p.id = oi.product_id
            WHERE oi.order_id = ?
        ");

        $itemStmt->execute([$row['order_id']]);

        $row['items'] = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode([
        "success" => true,
        "data" => $rows
    ]);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}