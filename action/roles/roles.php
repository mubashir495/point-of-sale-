<?php
/**
 * action/roles/roles.php
 * ------------------------------------------------------------------
 * Role management + permission-matrix API.
 *
 * GET    ?catalog=1        -> module/action catalogue (for building the UI matrix)
 * GET                      -> list all roles with a permission summary + user count
 * GET    ?id=5             -> single role with its full permission matrix
 * POST                     -> create a role (+ optional permissions matrix)
 * PUT    ?id=5             -> rename/describe a role (+ resync permissions matrix)
 * DELETE ?id=5             -> delete a custom role (system roles are protected)
 *
 * Protected by the 'settings' module permission (Role & Permission
 * management is treated as a system-settings capability).
 * ------------------------------------------------------------------
 */

require_once '../../config/rbac.php';

header('Content-Type: application/json');
// Never let the browser (or any intermediate proxy) cache this API's
// responses. Without this, some browsers can serve a stale cached
// copy of GET ?id=X when the "Edit" modal re-opens right after a
// save, making a successful permission update look like it didn't
// persist even though it did.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$pdo = rbac_db();
$method = $_SERVER['REQUEST_METHOD'];

// Every action here needs at least "view settings"; mutations need "edit".
rbac_require_permission('settings', $method === 'GET' ? 'view' : 'edit');
if ($method !== 'GET') {
    rbac_verify_csrf();
}

switch ($method) {

    case 'GET':
        if (isset($_GET['catalog'])) {
            respond(['success' => true, 'data' => [
                'modules' => rbac_modules(),
                'actions' => rbac_actions(),
            ]]);
        }

        $id = $_GET['id'] ?? null;

        if ($id) {
            $role = fetch_role($pdo, (int) $id);
            if (!$role) {
                respond(['success' => false, 'message' => 'Role not found.'], 404);
            }
            $role['permissions'] = rbac_load_permissions($pdo, (int) $id);
            respond(['success' => true, 'data' => $role]);
        }

        $stmt = $pdo->query("
            SELECT r.id, r.name, r.slug, r.description, r.is_system,
                   (SELECT COUNT(*) FROM branch_users bu WHERE bu.role_id = r.id) AS user_count,
                   (SELECT COUNT(*) FROM role_permissions rp WHERE rp.role_id = r.id) AS permission_count
            FROM roles r
            ORDER BY r.is_system DESC, r.name ASC
        ");
        respond(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        break;

    case 'POST':
        $d = input();

        $name = trim($d['name'] ?? '');
        if ($name === '') {
            respond(['success' => false, 'message' => 'Role name is required.'], 422);
        }

        $slug = make_slug($name);
        $description = trim($d['description'] ?? '');

        try {
            $pdo->beginTransaction();

            $check = $pdo->prepare("SELECT id FROM roles WHERE slug = ?");
            $check->execute([$slug]);
            if ($check->fetch()) {
                $pdo->rollBack();
                respond(['success' => false, 'message' => 'A role with a similar name already exists.'], 409);
            }

            $stmt = $pdo->prepare("
                INSERT INTO roles (name, slug, description, is_system, created_at, updated_at)
                VALUES (?, ?, ?, 0, NOW(), NOW())
            ");
            $stmt->execute([$name, $slug, $description ?: null]);
            $roleId = (int) $pdo->lastInsertId();

            sync_permissions($pdo, $roleId, $d['permissions'] ?? []);

            $pdo->commit();

            respond(['success' => true, 'message' => 'Role created successfully.', 'id' => $roleId], 201);

        } catch (PDOException $e) {
            $pdo->rollBack();
            respond(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
        }
        break;

    case 'PUT':
        $id = $_GET['id'] ?? null;
        if (!$id) {
            respond(['success' => false, 'message' => 'Missing role id.'], 422);
        }

        $role = fetch_role($pdo, (int) $id);
        if (!$role) {
            respond(['success' => false, 'message' => 'Role not found.'], 404);
        }

        $d = input();

        try {
            $pdo->beginTransaction();

            if (array_key_exists('name', $d) && trim($d['name']) !== '') {
                // Super Admin's name/slug is fixed so it can never be
                // accidentally renamed away from its bypass slug.
                if ($role['slug'] !== RBAC_SUPER_ROLE_SLUG) {
                    $name = trim($d['name']);
                    $stmt = $pdo->prepare("UPDATE roles SET name = ?, description = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$name, trim($d['description'] ?? ($role['description'] ?? '')) ?: null, $id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE roles SET description = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([trim($d['description'] ?? ($role['description'] ?? '')) ?: null, $id]);
                }
            }

            if (isset($d['permissions']) && $role['slug'] !== RBAC_SUPER_ROLE_SLUG) {
                sync_permissions($pdo, (int) $id, $d['permissions']);
            }

            $pdo->commit();

            respond(['success' => true, 'message' => 'Role updated successfully.']);

        } catch (PDOException $e) {
            $pdo->rollBack();
            respond(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
        }
        break;

    case 'DELETE':
        $id = $_GET['id'] ?? null;
        if (!$id) {
            respond(['success' => false, 'message' => 'Missing role id.'], 422);
        }

        $role = fetch_role($pdo, (int) $id);
        if (!$role) {
            respond(['success' => false, 'message' => 'Role not found.'], 404);
        }

        if ($role['is_system']) {
            respond(['success' => false, 'message' => 'Default system roles cannot be deleted.'], 403);
        }

        $usersStmt = $pdo->prepare("SELECT COUNT(*) FROM branch_users WHERE role_id = ?");
        $usersStmt->execute([$id]);
        if ((int) $usersStmt->fetchColumn() > 0) {
            respond(['success' => false, 'message' => 'Cannot delete a role that still has users assigned to it.'], 409);
        }

        $stmt = $pdo->prepare("DELETE FROM roles WHERE id = ?");
        $stmt->execute([$id]);

        respond(['success' => true, 'message' => 'Role deleted successfully.']);
        break;

    default:
        respond(['success' => false, 'message' => 'Method Not Allowed.'], 405);
}

// ------------------------------------------------------------------
// Helpers
// ------------------------------------------------------------------

function fetch_role(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare("SELECT id, name, slug, description, is_system FROM roles WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/**
 * $permissions is expected as: ['products' => ['view'=>true,'edit'=>false,...], ...]
 */
function sync_permissions(PDO $pdo, int $roleId, $permissions): void
{
    if (!is_array($permissions)) {
        return;
    }

    $validModules = array_keys(rbac_modules());
    $validActions = rbac_actions();

    $del = $pdo->prepare("DELETE FROM role_permissions WHERE role_id = ?");
    $del->execute([$roleId]);

    $lookup = $pdo->prepare("SELECT id FROM permissions WHERE module_key = ? AND action = ?");
    $insert = $pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");

    foreach ($permissions as $moduleKey => $actions) {
        if (!in_array($moduleKey, $validModules, true) || !is_array($actions)) {
            continue;
        }
        foreach ($actions as $action => $allowed) {
            if (!$allowed || !in_array($action, $validActions, true)) {
                continue;
            }
            $lookup->execute([$moduleKey, $action]);
            $permId = $lookup->fetchColumn();
            if ($permId) {
                $insert->execute([$roleId, $permId]);
            }
        }
    }
}

function make_slug(string $name): string
{
    $slug = strtolower(trim($name));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    return trim($slug, '-');
}

function input(): array
{
    $json = json_decode(file_get_contents('php://input'), true);
    return (json_last_error() === JSON_ERROR_NONE && is_array($json)) ? $json : $_POST;
}

function respond($data, $status = 200)
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
