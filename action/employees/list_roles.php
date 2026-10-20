<?php
/**
 * action/employees/list_roles.php
 * ------------------------------------------------------------------
 * Lightweight role list for the Employees page's "Designation"
 * dropdown, so it always reflects whatever roles actually exist in
 * Roles & Permissions (including custom roles created there) instead
 * of a hardcoded option list that can drift out of sync.
 *
 * Deliberately gated by the 'employees' module (not 'settings', which
 * action/roles/roles.php requires) so anyone who can add/edit
 * employees can populate this dropdown, even without access to the
 * Roles & Permissions screen itself.
 * ------------------------------------------------------------------
 */
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('employees', 'view');

header('Content-Type: application/json');

try {
    $pdo = rbac_db();
    $stmt = $pdo->query("SELECT id, name, slug FROM roles ORDER BY name ASC");
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'data' => $roles]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
