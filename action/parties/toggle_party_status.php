<?php
/**
 * action/parties/toggle_party_status.php
 * ------------------------------------------------------------------
 * Flips a party between 'active' and 'inactive'. Used instead of
 * deleting a party that already has orders/purchases/payments linked
 * to it (which the FK constraints on those tables correctly block -
 * see delete_party.php). An inactive party keeps all its historical
 * records and ledger intact, but drops out of "pick a party" lists
 * for new transactions.
 *
 * POST body (JSON or form): { "id": 5 }
 * ------------------------------------------------------------------
 */
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('suppliers', 'edit');

header('Content-Type: application/json');

require_once '../../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    $raw = json_decode(file_get_contents('php://input'), true);
    $data = is_array($raw) ? $raw : $_POST;

    $id = !empty($data['id']) ? (int) $data['id'] : 0;
    if (!$id) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'id is required']);
        exit;
    }

    $stmt = $db->prepare("SELECT status FROM parties WHERE id = ?");
    $stmt->execute([$id]);
    $current = $stmt->fetchColumn();

    if ($current === false) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Party not found']);
        exit;
    }

    $newStatus = ($current === 'inactive') ? 'active' : 'inactive';

    $update = $db->prepare("UPDATE parties SET status = ? WHERE id = ?");
    $update->execute([$newStatus, $id]);

    echo json_encode([
        'success' => true,
        'message' => $newStatus === 'inactive' ? 'Party deactivated' : 'Party activated',
        'status'  => $newStatus,
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}