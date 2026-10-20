<?php
/**
 * config/rbac.php
 * ------------------------------------------------------------------
 * Central Role-Based Access Control helper library.
 *
 * Include this file (require_once) at the top of:
 *   - action/auth/login.php   (to build the permission session)
 *   - action/auth/check.php   (to return role/permissions to the client)
 *   - every action/*.php endpoint that must be permission-protected
 *
 * It never echoes or exits on its own except inside the
 * rbac_require_* functions, which are opt-in.
 * ------------------------------------------------------------------
 */

if (!defined('RBAC_LOADED')) {
    define('RBAC_LOADED', true);

    require_once __DIR__ . '/database.php';

    /** The one role slug that always has every permission, hardcoded so
     *  the "keys to the kingdom" role can never be locked out even if
     *  its role_permissions rows are edited or deleted by mistake.
     *  (define() is used instead of `const` because `const` is not
     *  allowed inside a conditional block in PHP.) */
    define('RBAC_SUPER_ROLE_SLUG', 'super-admin');

    /** Canonical list of modules and actions used to build the
     *  permission matrix UI and to validate incoming requests. */
    function rbac_modules(): array
    {
        return [
            'dashboard'     => 'Dashboard',
            'products'      => 'Products',
            'categories'    => 'Categories',
            'raw_materials' => 'Raw Materials',
            'suppliers'     => 'Suppliers',
            'customers'     => 'Customers',
            'purchase'      => 'Purchase',
            'sales'         => 'Sales',
            'stock'         => 'Stock',
            'reports'       => 'Reports',
            'ledger'        => 'Ledger',
            'expenses'      => 'Expenses',
            'attendance'    => 'Attendance',
            'employees'     => 'Employees',
            'users'         => 'Users',
            'settings'      => 'Settings',
            'supplier_portal' => 'Supplier Portal (My Deliveries)',
        ];
    }

    function rbac_actions(): array
    {
        return ['view', 'create', 'edit', 'delete', 'print', 'export'];
    }

    /**
     * Get a shared PDO connection (reuses the project's Database class).
     */
    function rbac_db(): PDO
    {
        static $pdo = null;
        if ($pdo === null) {
            $database = new Database();
            $pdo = $database->getConnection();
        }
        return $pdo;
    }

    /**
     * Ensure a session is active. Safe to call multiple times.
     */
    function rbac_start_session(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    /**
     * Load a role's permissions from the database as:
     *   ['products' => ['view' => true, 'create' => true, ...], ...]
     */
    function rbac_load_permissions(PDO $pdo, int $roleId): array
    {
        $modules = rbac_modules();
        $permissions = [];
        foreach (array_keys($modules) as $moduleKey) {
            $permissions[$moduleKey] = array_fill_keys(rbac_actions(), false);
        }

        $stmt = $pdo->prepare("
            SELECT p.module_key, p.action
            FROM role_permissions rp
            INNER JOIN permissions p ON p.id = rp.permission_id
            WHERE rp.role_id = ?
        ");
        $stmt->execute([$roleId]);

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (isset($permissions[$row['module_key']])) {
                $permissions[$row['module_key']][$row['action']] = true;
            }
        }

        return $permissions;
    }

    /**
     * Build the full "all true" permission grid, used for Super Admin.
     */
    function rbac_full_permissions(): array
    {
        $permissions = [];
        foreach (array_keys(rbac_modules()) as $moduleKey) {
            $permissions[$moduleKey] = array_fill_keys(rbac_actions(), true);
        }
        return $permissions;
    }

    /**
     * Called right after a successful login. Populates $_SESSION with
     * the authenticated user's identity, role and permission grid.
     *
     * $userRow must contain: id, username, role_id, role_name, role_slug
     */
    function rbac_bootstrap_session(PDO $pdo, array $userRow): void
    {
        rbac_start_session();

        session_regenerate_id(true); // prevent session fixation on login

        $isSuper = ($userRow['role_slug'] ?? '') === RBAC_SUPER_ROLE_SLUG;

        $_SESSION['user_id']    = (int) $userRow['id'];
        $_SESSION['username']   = $userRow['username'];
        $_SESSION['role_id']    = $userRow['role_id'] ? (int) $userRow['role_id'] : null;
        $_SESSION['role_name']  = $userRow['role_name'] ?? null;
        $_SESSION['role_slug']  = $userRow['role_slug'] ?? null;
        $_SESSION['is_super']   = $isSuper;
        // Links a login account to its `employees` row (e.g. a Supplier /
        // delivery-agent account). Used to scope "my orders" style pages
        // so a non-admin user only ever sees records tied to themselves.
        $_SESSION['employee_id'] = !empty($userRow['employee_id']) ? (int) $userRow['employee_id'] : null;
        $_SESSION['permissions'] = $isSuper || !$userRow['role_id']
            ? rbac_full_permissions()
            : rbac_load_permissions($pdo, (int) $userRow['role_id']);

        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }

    /**
     * Returns the current session user as an array, or null if not
     * logged in. Does not touch the database.
     */
    function rbac_current_user(): ?array
    {
        rbac_start_session();
        if (empty($_SESSION['user_id'])) {
            return null;
        }
        return [
            'id'          => $_SESSION['user_id'],
            'username'    => $_SESSION['username'] ?? null,
            'role_id'     => $_SESSION['role_id'] ?? null,
            'role_name'   => $_SESSION['role_name'] ?? null,
            'role_slug'   => $_SESSION['role_slug'] ?? null,
            'is_super'    => $_SESSION['is_super'] ?? false,
            'employee_id' => $_SESSION['employee_id'] ?? null,
            'permissions' => $_SESSION['permissions'] ?? [],
        ];
    }

    function rbac_is_super_admin(): bool
    {
        rbac_start_session();
        return !empty($_SESSION['is_super']);
    }

    /**
     * The `employees.id` linked to the current login account (set at
     * login time from branch_users.employee_id), or null if this
     * account has no linked employee record (e.g. a pure admin login).
     */
    function rbac_current_employee_id(): ?int
    {
        rbac_start_session();
        return isset($_SESSION['employee_id']) && $_SESSION['employee_id'] !== null
            ? (int) $_SESSION['employee_id']
            : null;
    }

    /**
     * Core permission check used everywhere (server pages, APIs, JS
     * mirrors this logic via the /action/auth/check.php payload).
     */
    function rbac_has_permission(string $module, string $action): bool
    {
        rbac_start_session();

        if (empty($_SESSION['user_id'])) {
            return false;
        }
        if (!empty($_SESSION['is_super'])) {
            return true;
        }
        return !empty($_SESSION['permissions'][$module][$action]);
    }

    /**
     * Send a standard JSON error and stop execution.
     */
    /**
     * Maps an `employees.designation` free-text value to the RBAC role
     * slug that should be auto-assigned when a login is created for
     * that employee from the Employees page. Returns null for
     * designations with no sensible automatic role (an admin can still
     * assign a role by hand later from Branch Users).
     */
    function rbac_role_slug_for_designation(string $designation): ?string
    {
        $map = [
            'admin'           => 'admin',
            'manager'         => 'manager',
            'branch manager'  => 'branch-manager',
            'supervisor'      => 'manager',
            'supplier'        => 'supplier',
            'delivery rider'  => 'supplier',
            'salesman'        => 'sales-person',
            'sales officer'   => 'sales-person',
            'cashier'         => 'counter-boy',
            'store manager'   => 'store-keeper',
            'warehouse staff' => 'store-keeper',
            'accountant'      => 'accountant',
        ];
        return $map[strtolower(trim($designation))] ?? null;
    }

    /**
     * Creates or updates the `branch_users` login row linked to an
     * employee (employees.id). Called from the Employees add/edit
     * endpoints whenever a username is supplied, so an admin can grant
     * an employee system access in one step instead of a separate
     * Branch Users entry.
     *
     * - If the employee already has a branch_users row (matched by
     *   employee_id), it is updated (password only changes if a new
     *   non-empty one is supplied).
     * - The role is auto-picked from the employee's designation via
     *   rbac_role_slug_for_designation(); if that designation has no
     *   mapped role, role_id is left as-is (or null for a new row) so
     *   an admin can assign one manually from Branch Users / Roles.
     * - No-op (returns null) if $username is empty - employees without
     *   a username simply have no login, as today.
     */
    function rbac_upsert_employee_login(int $employeeId, string $username, string $password, string $designation, ?int $roleId = null): ?array
    {
        $username = trim($username);
        if ($username === '') {
            return null;
        }

        $pdo = rbac_db();

        // Prefer an explicitly-selected role (sent as role_id from the
        // Employees page's Designation dropdown, which is populated
        // from the real `roles` table - see action/employees/list_roles.php).
        // This works for any role, including custom ones created via
        // Roles & Permissions, and never silently fails the way
        // guessing a role from free-text designation can. The text
        // map is kept only as a fallback for older callers that don't
        // send role_id yet.
        if ($roleId) {
            $stmt = $pdo->prepare('SELECT id FROM roles WHERE id = ? LIMIT 1');
            $stmt->execute([$roleId]);
            $roleId = $stmt->fetchColumn() ?: null;
        }
        if (!$roleId) {
            $roleSlug = rbac_role_slug_for_designation($designation);
            if ($roleSlug) {
                $stmt = $pdo->prepare('SELECT id FROM roles WHERE slug = ? LIMIT 1');
                $stmt->execute([$roleSlug]);
                $roleId = $stmt->fetchColumn() ?: null;
            }
        }

        // Does this employee already have a login? Match by employee_id
        // first (the normal case). Some legacy rows were created before
        // employee_id was reliably linked, so also fall back to matching
        // by username - otherwise a missing link causes a NEW duplicate
        // row to be inserted here every time someone edits the employee,
        // leaving the old row (with the old password) still around and
        // still the one login.php finds first. Self-heals the link by
        // setting employee_id on that row once found this way.
        $stmt = $pdo->prepare('SELECT id, role_id FROM branch_users WHERE employee_id = ? LIMIT 1');
        $stmt->execute([$employeeId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$existing) {
            $stmt = $pdo->prepare('SELECT id, role_id FROM branch_users WHERE username = ? LIMIT 1');
            $stmt->execute([$username]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                $pdo->prepare('UPDATE branch_users SET employee_id = ? WHERE id = ?')
                    ->execute([$employeeId, $existing['id']]);
            }
        }

        // user_type mirrors the resolved role's own slug (falls back to
        // "employee" when no role could be resolved at all), regardless
        // of whether that role came from the direct role_id or the
        // legacy designation-text guess.
        $resolvedRoleSlug = 'employee';
        if ($roleId) {
            $stmt = $pdo->prepare('SELECT slug FROM roles WHERE id = ? LIMIT 1');
            $stmt->execute([$roleId]);
            $resolvedRoleSlug = $stmt->fetchColumn() ?: 'employee';
        }

        if ($existing) {
            $fields = ['username = :username'];
            $params = [':username' => $username, ':id' => $existing['id']];

            if ($password !== '') {
                $fields[] = 'password = :password';
                $params[':password'] = password_hash($password, PASSWORD_DEFAULT);
            }
            if ($roleId) {
                $fields[] = 'role_id = :role_id';
                $params[':role_id'] = $roleId;
                $fields[] = 'user_type = :user_type';
                $params[':user_type'] = $resolvedRoleSlug;
            }

            $sql = 'UPDATE branch_users SET ' . implode(', ', $fields) . ' WHERE id = :id';
            $pdo->prepare($sql)->execute($params);
            return ['id' => $existing['id'], 'created' => false];
        }

        // New login: a password is required to create one.
        if ($password === '') {
            return null;
        }

        $stmt = $pdo->prepare('
            INSERT INTO branch_users (employee_id, username, password, user_type, role_id, status, created_at)
            VALUES (:employee_id, :username, :password, :user_type, :role_id, "active", NOW())
        ');
        $stmt->execute([
            ':employee_id' => $employeeId,
            ':username'    => $username,
            ':password'    => password_hash($password, PASSWORD_DEFAULT),
            ':user_type'   => $resolvedRoleSlug,
            ':role_id'     => $roleId,
        ]);

        return ['id' => (int) $pdo->lastInsertId(), 'created' => true];
    }

    /**
     * The customer ids (from the `customers` table) that the current
     * user is restricted to, via the existing "Linked Parties" picker
     * on the Branch Users page (table `user_parties`).
     *
     * Returns:
     *   - null  => no restriction; caller should show ALL customers.
     *              (Super Admin / Admin always get this, and so does
     *              any other user who simply has no assignments yet -
     *              so nothing breaks for existing single-branch setups
     *              until an admin actually assigns specific customers
     *              to someone, e.g. a Branch Manager.)
     *   - array => only these customer ids should be visible/usable.
     *              May be an empty array (assigned to zero customers).
     */
    function rbac_scope_customer_ids(): ?array
    {
        $user = rbac_current_user();
        if (!$user) {
            return [];
        }
        if (!empty($user['is_super']) || ($user['role_slug'] ?? '') === 'admin') {
            return null;
        }

        $pdo = rbac_db();
        $stmt = $pdo->prepare('SELECT party_id FROM user_parties WHERE user_id = ?');
        $stmt->execute([$user['id']]);
        $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

        return empty($ids) ? null : $ids;
    }

    function rbac_json_exit(int $httpCode, string $message, array $extra = []): void
    {
        http_response_code($httpCode);
        header('Content-Type: application/json');
        echo json_encode(array_merge([
            'success' => false,
            'message' => $message,
        ], $extra));
        exit;
    }

    /**
     * Keeps branch_users.branch_id (used for the LOGIN SESSION) in
     * sync whenever employees.branch_id changes (used for DISPLAY /
     * assignment lists). Without this, an employee can show as
     * "assigned to Branch X" everywhere in the UI while their own
     * login still resolves to no branch at all, because login.php
     * reads branch_id from branch_users, not employees.
     *
     * Call this any time employees.branch_id is set or cleared for
     * an employee who may have a branch_users login row.
     */
    function rbac_sync_employee_branch_login(int $employeeId, ?int $branchId): void
    {
        $pdo = rbac_db();
        $pdo->prepare('UPDATE branch_users SET branch_id = ? WHERE employee_id = ?')
            ->execute([$branchId, $employeeId]);
    }

    /**
     * The branch_id of the currently logged-in user's session
     * (set at login time, see action/auth/login.php), or null if
     * this account isn't tied to a branch (e.g. a pure admin login
     * created before branches existed).
     */
    function rbac_current_branch_id(): ?int
    {
        rbac_start_session();
        return isset($_SESSION['branch_id']) && $_SESSION['branch_id'] !== null
            ? (int) $_SESSION['branch_id']
            : null;
    }

    /**
     * Whether branch data (Products, Customers, Orders/Sales,
     * Dashboard totals) should be filtered down to the current
     * user's own branch.
     *
     * Super Admin / Admin always see every branch (combined view).
     * Everyone else is scoped to their own branch_id - if they have
     * none, they see nothing branch-tagged rather than everything,
     * so a mis-configured account fails closed, not open.
     */
    function rbac_is_branch_scoped(): bool
    {
        $user = rbac_current_user();
        if (!$user) {
            return true;
        }
        if (!empty($user['is_super']) || ($user['role_slug'] ?? '') === 'admin') {
            return false;
        }
        return true;
    }


    /**
     * Require an authenticated session. Use at the top of any API
     * endpoint that needs a logged-in user but no specific permission.
     */
    function rbac_require_login(): array
    {
        rbac_start_session();
        $user = rbac_current_user();
        if (!$user) {
            rbac_json_exit(401, 'Authentication required.');
        }
        return $user;
    }

    /**
     * Require a specific module+action permission. Sends a 403 JSON
     * "Access Denied" response and halts execution if the current
     * session user lacks it.
     *
     * Usage (top of an action/*.php file):
     *   require_once __DIR__ . '/../../config/rbac.php';
     *   rbac_require_permission('products', 'edit');
     */
    function rbac_require_permission(string $module, string $action): array
    {
        $user = rbac_require_login();

        if (!rbac_has_permission($module, $action)) {
            rbac_json_exit(403, '403 Access Denied: you do not have permission to ' . $action . ' ' . $module . '.');
        }

        return $user;
    }

    /**
     * Map an HTTP method to the permission action it should check,
     * for endpoints that want one call to cover the whole file:
     *   GET -> view, POST -> create, PUT/PATCH -> edit, DELETE -> delete
     */
    function rbac_action_for_method(?string $method = null): string
    {
        $method = $method ?? ($_SERVER['REQUEST_METHOD'] ?? 'GET');
        switch (strtoupper($method)) {
            case 'POST':   return 'create';
            case 'PUT':
            case 'PATCH':  return 'edit';
            case 'DELETE': return 'delete';
            default:       return 'view';
        }
    }

    // ---------------------------------------------------------------
    // CSRF protection (double-submit cookie / session-token pattern)
    // ---------------------------------------------------------------

    function rbac_csrf_token(): string
    {
        rbac_start_session();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Verify the CSRF token sent by the client for state-changing
     * requests (POST/PUT/PATCH/DELETE). Accepts the token from the
     * X-CSRF-Token header or a csrf_token field in the JSON/POST body.
     */
    function rbac_verify_csrf(): void
    {
        rbac_start_session();

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return;
        }

        $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;

        if (!$sent) {
            $raw = file_get_contents('php://input');
            $json = json_decode($raw, true);
            if (is_array($json) && isset($json['csrf_token'])) {
                $sent = $json['csrf_token'];
            } elseif (isset($_POST['csrf_token'])) {
                $sent = $_POST['csrf_token'];
            }
        }

        $expected = $_SESSION['csrf_token'] ?? null;

        if (!$expected || !$sent || !hash_equals($expected, (string) $sent)) {
            rbac_json_exit(419, 'Invalid or missing CSRF token. Please refresh the page and try again.');
        }
    }

    /**
     * Basic XSS-safe output helper for any server-rendered HTML.
     */
    function rbac_e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}