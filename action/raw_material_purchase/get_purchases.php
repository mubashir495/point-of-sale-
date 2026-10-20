<?php
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('purchase', 'view');

header('Content-Type: application/json');
require_once '../../config/Database.php';

try {
    $db   = new Database();
    $conn = $db->getConnection();

    $sql = "
        SELECT
            pm.id,
            pm.po_no,
            pm.party_id,
            p.party_name,
            pm.purchase_date,
            pm.total_amount,
            pm.paid_amount,
            pm.balance_amount,
            pm.status,
            pm.remarks,
            pm.created_at
        FROM purchase_master pm
        LEFT JOIN parties p ON p.id = pm.party_id
        ORDER BY pm.id DESC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $data = [];
    foreach ($rows as $row) {
        $data[] = [
            'id'             => (int) $row['id'],
            'po_no'          => $row['po_no'],
            'party_id'       => (int) $row['party_id'],
            'party_name'     => $row['party_name'] ?? '-',
            'purchase_date'  => $row['purchase_date'],
            'total_amount'   => (float) $row['total_amount'],
            'paid_amount'    => (float) $row['paid_amount'],
            'balance_amount' => (float) $row['balance_amount'],
            'status'         => $row['status'],
            'remarks'        => $row['remarks'],
            'created_at'     => $row['created_at'],
        ];
    }

    echo json_encode(['success' => true, 'data' => $data]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}