<?php
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('purchase', 'view');


header('Content-Type: application/json');
require_once '../../config/Database.php';

$party_id = isset($_GET['party_id']) ? (int)$_GET['party_id'] : 0;

if($party_id<=0){

    echo json_encode([
        'success'=>false,
        'message'=>'Invalid Party ID.'
    ]);
    exit;
}

try{

    $db=new Database();
    $conn=$db->getConnection();

    $stmt=$conn->prepare("
        SELECT

            pl.*,

            pi.invoice_no,

            pm.po_no

        FROM party_ledger pl

        LEFT JOIN purchase_invoices pi
        ON pi.id=pl.invoice_id

        LEFT JOIN purchase_master pm
        ON pm.id=pl.purchase_id

        WHERE pl.party_id=?

        ORDER BY pl.id DESC
    ");

    $stmt->execute([$party_id]);

    echo json_encode([
        'success'=>true,
        'data'=>$stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);

}catch(Throwable $e){

    http_response_code(500);

    echo json_encode([
        'success'=>false,
        'message'=>$e->getMessage()
    ]);

}