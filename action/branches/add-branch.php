<?php

require_once __DIR__ . '/../../config/rbac.php';

$raw = json_decode(file_get_contents('php://input'), true);
$d = is_array($raw) ? $raw : $_POST;

$id = !empty($d['id']) ? (int) $d['id'] : null;

rbac_require_permission('users', $id ? 'edit' : 'create');
rbac_verify_csrf();

header('Content-Type: application/json');

$branchName = trim($d['branch_name'] ?? '');
$managerId  = !empty($d['manager_id']) ? (int) $d['manager_id'] : null;
$address    = trim($d['address'] ?? '') ?: null;
$phone      = trim($d['phone'] ?? '') ?: null;

if ($branchName === '') {
    echo json_encode(['success' => false, 'message' => 'Branch name is required.']);
    exit;
}

try {
    $pdo = rbac_db();

    if (!$id) {
        // No id given - see if a branch with this name already exists
        // so we edit it in place instead of creating a duplicate.
        $stmt = $pdo->prepare("SELECT id FROM branches WHERE LOWER(branch_name) = LOWER(?) LIMIT 1");
        $stmt->execute([$branchName]);
        $existingId = $stmt->fetchColumn();
        if ($existingId) {
            $id = (int) $existingId;
        }
    }

    if ($id) {
        $stmt = $pdo->prepare("UPDATE branches SET branch_name = ?, manager_id = ?, address = ?, phone = ? WHERE id = ?");
        $stmt->execute([$branchName, $managerId, $address, $phone, $id]);

        if ($managerId) {
            $pdo->prepare("UPDATE employees SET branch_id = ? WHERE id = ?")->execute([$id, $managerId]);
            rbac_sync_employee_branch_login($managerId, $id);
        }

        echo json_encode(['success' => true, 'message' => 'Branch updated.', 'id' => $id, 'created' => false]);
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO branches (branch_name, manager_id, address, phone, status, created_at) VALUES (?, ?, ?, ?, 'active', NOW())");
    $stmt->execute([$branchName, $managerId, $address, $phone]);
    $newId = (int) $pdo->lastInsertId();

    if ($managerId) {
        $pdo->prepare("UPDATE employees SET branch_id = ? WHERE id = ?")->execute([$newId, $managerId]);
        rbac_sync_employee_branch_login($managerId, $newId);
    }

    echo json_encode(['success' => true, 'message' => 'Branch created.', 'id' => $newId, 'created' => true]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}