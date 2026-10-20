<?php
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('purchase', 'view');

header('Content-Type: application/json');
require_once '../../config/Database.php';

$purchase_id = isset($_GET['purchase_id']) ? (int)$_GET['purchase_id'] : 0;

if ($purchase_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid Purchase ID.'
    ]);
    exit;
}

try {

    $db = new Database();
    $conn = $db->getConnection();

    $stmt = $conn->prepare("
    SELECT
        pi.*,
        pm.po_no,
        p.party_name,
        p.phone_number
    FROM purchase_invoices pi
    INNER JOIN purchase_master pm
        ON pm.id = pi.purchase_id
    INNER JOIN parties p
        ON p.id = pi.party_id
    WHERE pi.purchase_id = ?
    LIMIT 1
");
    $stmt->execute([$purchase_id]);

    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$invoice) {
        echo json_encode([
            'success'=>false,
            'message'=>'Invoice not found.'
        ]);
        exit;
    }

    $itemStmt = $conn->prepare("
        SELECT

            pii.id,

            rm.name AS raw_material_name,

            pii.quantity,

            pii.rate,

            pii.line_total

        FROM purchase_invoice_items pii

        INNER JOIN raw_materials rm
        ON rm.id = pii.raw_material_id

        WHERE pii.invoice_id=?

        ORDER BY pii.id ASC
    ");

    $itemStmt->execute([$invoice['id']]);

    $invoice['items'] = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success'=>true,
        'data'=>$invoice
    ]);

} catch(Throwable $e){

    http_response_code(500);

    echo json_encode([
        'success'=>false,
        'message'=>$e->getMessage()
    ]);

}