<?php

require_once '../../config/rbac.php';

$pdo = rbac_db();

$method = $_SERVER['REQUEST_METHOD'];

rbac_require_permission('users', rbac_action_for_method($method));
if ($method !== 'GET') {
    rbac_verify_csrf();
}

$type   = $_GET['type'] ?? $_GET['action'] ?? null;
$userId = $_GET['user_id'] ?? null;

if (!$type || !in_array($type, ['areas', 'parties'])) {
    respond([
        'success' => false,
        'message' => 'Missing or invalid type. Use type=areas or type=parties.'
    ], 422);
}

if (!$userId) {
    respond([
        'success' => false,
        'message' => 'Missing user_id.'
    ], 422);
}

// -----------------------------------------------------------
// Auto-detect actual column name from a whitelist of candidates
// -----------------------------------------------------------
function pickColumn(PDO $pdo, string $table, array $candidates)
{
    $stmt = $pdo->prepare("
        SELECT COLUMN_NAME
        FROM information_schema.columns
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
    ");
    $stmt->execute([$table]);
    $existing = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($candidates as $c) {
        if (in_array($c, $existing, true)) return $c;
    }
    return null;
}

switch ($method) {

    case 'GET':

        try {

            if ($type === 'areas') {

                $stmt = $pdo->prepare("
                    SELECT a.id, a.area_name
                    FROM user_areas ua
                    INNER JOIN areas a ON a.id = ua.area_id
                    WHERE ua.user_id = ?
                    ORDER BY a.area_name ASC
                ");
                $stmt->execute([$userId]);

            } else { // parties

                $partyCol = pickColumn($pdo, 'user_parties', ['party_id', 'customer_id']);
                $nameCol  = pickColumn($pdo, 'customers', ['name', 'customer_name', 'full_name']);

                if (!$partyCol) {
                    respond([
                        'success' => false,
                        'message' => 'Could not detect customer-reference column in user_parties table (expected party_id or customer_id).'
                    ], 500);
                }
                if (!$nameCol) {
                    respond([
                        'success' => false,
                        'message' => 'Could not detect name column in customers table (expected name, customer_name, or full_name).'
                    ], 500);
                }

                $sql = "
                    SELECT c.id, c.`$nameCol` AS name
                    FROM user_parties up
                    INNER JOIN customers c ON c.id = up.`$partyCol`
                    WHERE up.user_id = ?
                    ORDER BY c.`$nameCol` ASC
                ";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$userId]);

            }

            respond([
                'success' => true,
                'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ]);

        } catch (PDOException $e) {

            respond([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);

        }

        break;

    case 'DELETE':

        try {

            if ($type === 'areas') {

                $areaId = $_GET['area_id'] ?? null;

                if (!$areaId) {
                    respond(['success' => false, 'message' => 'Missing area_id.'], 422);
                }

                $stmt = $pdo->prepare("DELETE FROM user_areas WHERE user_id = ? AND area_id = ?");
                $stmt->execute([$userId, $areaId]);

            } else { // parties

                $partyId = $_GET['party_id'] ?? null;

                if (!$partyId) {
                    respond(['success' => false, 'message' => 'Missing party_id.'], 422);
                }

                $partyCol = pickColumn($pdo, 'user_parties', ['party_id', 'customer_id']);

                if (!$partyCol) {
                    respond([
                        'success' => false,
                        'message' => 'Could not detect customer-reference column in user_parties table.'
                    ], 500);
                }

                $stmt = $pdo->prepare("DELETE FROM user_parties WHERE user_id = ? AND `$partyCol` = ?");
                $stmt->execute([$userId, $partyId]);

            }

            if ($stmt->rowCount() == 0) {
                respond(['success' => false, 'message' => 'Record not found.'], 404);
            }

            respond([
                'success' => true,
                'message' => 'Removed successfully'
            ]);

        } catch (PDOException $e) {

            respond(['success' => false, 'message' => $e->getMessage()], 500);

        }

        break;

    default:

        respond(['success' => false, 'message' => 'Method Not Allowed.'], 405);

        break;
}

function respond($data, $status = 200)
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}