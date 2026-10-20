<?php
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('sales', 'delete');

require_once '../../config/database.php';
header('Content-Type: application/json');

try {
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;

    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'Quotation ID is required']);
        exit;
    }

    $database = new Database();
    $db = $database->getConnection();

    $stmt = $db->prepare("DELETE FROM quotations WHERE id = :id");
    $stmt->bindParam(':id', $id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Quotation deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete quotation']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}