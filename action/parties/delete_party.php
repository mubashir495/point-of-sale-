<?php
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('suppliers', 'delete');

// DELETE /delete.php?id=5
require_once '../../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    http_response_code(405);
    echo json_encode(['error' => 'Use DELETE', 'message' => 'Use DELETE']);
    exit;
}

if (empty($_GET['id'])) {
    http_response_code(422);
    echo json_encode(['error' => 'id is required', 'message' => 'id is required']);
    exit;
}

$pdo = (new Database())->getConnection();

try {
    $check = $pdo->prepare("SELECT id FROM parties WHERE id = ?");
    $check->execute([$_GET['id']]);
    if (!$check->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Party not found', 'message' => 'Party not found']);
        exit;
    }

    $stmt = $pdo->prepare("DELETE FROM parties WHERE id = ?");
    $stmt->execute([$_GET['id']]);

    echo json_encode(['success' => true, 'message' => 'Party deleted']);
} catch (PDOException $e) {
    // MySQL foreign key violation (party still referenced by orders,
    // purchases, payments, etc.) - error code 23000. Give a clear
    // message instead of letting the raw DB error break the JSON
    // response (which is what was showing as "Unexpected token '<'").
    if ((int) $e->getCode() === 23000 || str_contains($e->getMessage(), 'foreign key constraint')) {
        http_response_code(409);
        echo json_encode(['error' => 'This party has existing orders, purchases, or payments linked to it, so it cannot be deleted. Remove or reassign those records first.', 'message' => 'This party has existing orders, purchases, or payments linked to it, so it cannot be deleted. Remove or reassign those records first.']);
        exit;
    }

    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage(), 'message' => 'Database error: ' . $e->getMessage()]);
}