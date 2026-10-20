<?php
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('purchase', 'delete');

header('Content-Type: application/json');
require_once '../../config/Database.php';

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if ($id <= 0) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid Purchase ID."
    ]);
    exit;
}

try {

    $db = new Database();
    $conn = $db->getConnection();

    $conn->beginTransaction();

    // delete invoice items
    $stmt = $conn->prepare("
        DELETE pii
        FROM purchase_invoice_items pii
        INNER JOIN purchase_invoices pi
            ON pi.id = pii.invoice_id
        WHERE pi.purchase_id = ?
    ");
    $stmt->execute([$id]);

    // delete invoice
    $stmt = $conn->prepare("
        DELETE FROM purchase_invoices
        WHERE purchase_id=?
    ");
    $stmt->execute([$id]);

    // delete purchase details
    $stmt = $conn->prepare("
        DELETE FROM purchase_details
        WHERE purchase_id=?
    ");
    $stmt->execute([$id]);

    // delete purchase master
    $stmt = $conn->prepare("
        DELETE FROM purchase_master
        WHERE id=?
    ");
    $stmt->execute([$id]);

    $conn->commit();

    echo json_encode([
        "success"=>true,
        "message"=>"Purchase deleted successfully."
    ]);

} catch(Throwable $e){

    $conn->rollBack();

    echo json_encode([
        "success"=>false,
        "message"=>$e->getMessage()
    ]);
}