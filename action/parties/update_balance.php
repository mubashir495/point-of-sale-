<?php
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('suppliers', 'edit');

require_once '../../config/database.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Use POST']);
    exit;
}

$in = json_decode(file_get_contents('php://input'), true);
$in = is_array($in) ? $in : [];

if (empty($in['id']) || empty($in['balance_type']) || !in_array($in['balance_type'], ['receivable', 'payable'])) {
    http_response_code(422);
    echo json_encode(['error' => 'id and balance_type (receivable/payable) are required']);
    exit;
}

if (!isset($in['amount']) || !in_array($in['operation'] ?? '', ['add', 'subtract'])) {
    http_response_code(422);
    echo json_encode(['error' => 'amount and operation (add/subtract) are required']);
    exit;
}

$pdo = (new Database())->getConnection();
$stmt = $pdo->prepare("SELECT * FROM parties WHERE id = ?");
$stmt->execute([$in['id']]);
$party = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$party) {
    http_response_code(404);
    echo json_encode(['error' => 'Party not found']);
    exit;
}

$balanceType = $in['balance_type'];
$amount = round((float)$in['amount'], 2);
$operation = $in['operation']; // 'add' or 'subtract'

// Current balance for that type
$current = (float)$party[$balanceType . '_balance'];

// Calculate new balance
$newBalance = ($operation === 'add') ? $current + $amount : $current - $amount;
$newBalance = round($newBalance, 2);

// Update the specific column
$update = $pdo->prepare("UPDATE parties SET {$balanceType}_balance = :bal WHERE id = :id");
$update->execute([':bal' => $newBalance, ':id' => $in['id']]);

$row = $pdo->prepare("SELECT * FROM parties WHERE id = ?");
$row->execute([$in['id']]);

echo json_encode(['message' => 'Balance updated', 'party' => $row->fetch(PDO::FETCH_ASSOC)]);