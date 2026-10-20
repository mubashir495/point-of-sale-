<?php
/**
 * action/Branch users/branch_users.php
 * ------------------------------------------------------------------
 * Existing branch-users CRUD, extended (not rewritten) with RBAC:
 *   - role_id / status / profile_image / email / phone / full_name
 *   - permission checks per HTTP method (module: "users")
 *   - CSRF verification on all state-changing requests
 *   - optional multipart profile image upload
 *
 * All previously-supported fields/behaviour are unchanged, so existing
 * frontend calls that don't send the new fields keep working exactly
 * as before.
 * ------------------------------------------------------------------
 */

require_once '../../config/rbac.php';

$pdo = rbac_db();
$method = $_SERVER['REQUEST_METHOD'];

// Users module permission check, mapped from the HTTP verb.
rbac_require_permission('users', rbac_action_for_method($method));
if ($method !== 'GET') {
    rbac_verify_csrf();
}

switch ($method) {

    // ==========================================================
    // GET
    // ==========================================================
    case 'GET':

        $id       = $_GET['id'] ?? null;
        $branchId = $_GET['branch_id'] ?? null;
        $search   = trim($_GET['search'] ?? '');

        // ---- Single user (for Edit modal) ----
        if ($id) {

            try {

                $stmt = $pdo->prepare("
                    SELECT
                        bu.id,
                        bu.employee_id,
                        bu.branch_id,
                        bu.city_id,
                        bu.username,
                        bu.user_type,
                        bu.shift,
                        bu.customer_id,
                        bu.role_id,
                        bu.status,
                        bu.profile_image,
                        bu.full_name,
                        bu.email,
                        bu.phone,
                        r.name AS role_name,
                        e.full_name AS employee_name,
                        b.branch_name
                    FROM branch_users bu
                    LEFT JOIN employees e ON e.id = bu.employee_id
                    LEFT JOIN roles r ON r.id = bu.role_id
                    LEFT JOIN branches b ON b.id = bu.branch_id
                    WHERE bu.id = ?
                ");
                $stmt->execute([$id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$user) {
                    respond([
                        'success' => false,
                        'message' => 'User not found'
                    ], 404);
                }

                // assigned area ids
                $areaStmt = $pdo->prepare("SELECT area_id FROM user_areas WHERE user_id = ?");
                $areaStmt->execute([$id]);
                $user['area_ids'] = array_map('intval', $areaStmt->fetchAll(PDO::FETCH_COLUMN));

                // linked party (customer) ids
                $partyStmt = $pdo->prepare("SELECT party_id FROM user_parties WHERE user_id = ?");
                $partyStmt->execute([$id]);
                $user['party_ids'] = array_map('intval', $partyStmt->fetchAll(PDO::FETCH_COLUMN));

                respond([
                    'success' => true,
                    'data' => $user
                ]);

            } catch (PDOException $e) {

                respond([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 500);

            }
        }

        // ---- List of users (for table) ----
        $sql = "
            SELECT
                bu.id,
                bu.employee_id,
                bu.branch_id,
                bu.city_id,
                bu.username,
                bu.user_type,
                bu.shift,
                bu.customer_id,
                bu.role_id,
                bu.status,
                bu.profile_image,
                bu.full_name,
                bu.email,
                bu.phone,
                r.name AS role_name,
                r.slug AS role_slug,
                e.full_name AS employee_name,
                b.branch_name
            FROM branch_users bu
            LEFT JOIN employees e ON e.id = bu.employee_id
            LEFT JOIN roles r ON r.id = bu.role_id
            LEFT JOIN branches b ON b.id = bu.branch_id
            WHERE 1=1
        ";

        $params = [];

        if ($branchId) {
            $sql .= " AND bu.branch_id = ?";
            $params[] = $branchId;
        }

        if ($search != '') {
            $sql .= " AND (
                        bu.username LIKE ?
                        OR bu.full_name LIKE ?
                        OR e.full_name LIKE ?
                    )";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        $sql .= " ORDER BY bu.id DESC";

        try {

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

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

    // ==========================================================
    // POST — create user + assign areas/parties + sync customer_id
    // ==========================================================
    case 'POST':

        $d = input();

        foreach (['employee_id', 'username', 'password', 'user_type', 'shift', 'branch_id'] as $field) {
            if (empty($d[$field])) {
                respond([
                    'success' => false,
                    'message' => "Missing field: {$field}"
                ], 422);
            }
        }

        if (!empty($d['confirm_password']) && $d['confirm_password'] !== $d['password']) {
            respond(['success' => false, 'message' => 'Password and confirm password do not match.'], 422);
        }

        if (!empty($d['email']) && !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
            respond(['success' => false, 'message' => 'Invalid email address.'], 422);
        }

        $areaIds  = (isset($d['area_ids'])  && is_array($d['area_ids']))  ? array_map('intval', $d['area_ids'])  : [];
        $partyIds = (isset($d['party_ids']) && is_array($d['party_ids'])) ? array_map('intval', $d['party_ids']) : [];

        // primary customer_id = first selected party
        $primaryCustomerId = !empty($partyIds) ? $partyIds[0] : null;

        $roleId       = !empty($d['role_id']) ? (int) $d['role_id'] : null;
        $status       = in_array(($d['status'] ?? 'active'), ['active', 'inactive'], true) ? $d['status'] : 'active';
        $profileImage = handle_profile_image_upload();

        try {

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO branch_users
                    (employee_id, branch_id, city_id, username, password, user_type, shift, customer_id,
                     role_id, status, profile_image, full_name, email, phone, created_at)
                VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $d['employee_id'],
                $d['branch_id'] ?? null,
                $d['city_id'] ?? null,
                $d['username'],
                password_hash($d['password'], PASSWORD_DEFAULT),
                $d['user_type'],
                $d['shift'],
                $primaryCustomerId,
                $roleId,
                $status,
                $profileImage,
                $d['full_name'] ?? null,
                $d['email'] ?? null,
                $d['phone'] ?? null,
            ]);

            $newId = $pdo->lastInsertId();

            if ($areaIds) {
                $areaStmt = $pdo->prepare("INSERT INTO user_areas (user_id, area_id) VALUES (?, ?)");
                foreach ($areaIds as $aid) {
                    $areaStmt->execute([$newId, $aid]);
                }
            }

            if ($partyIds) {
                $partyStmt = $pdo->prepare("INSERT INTO user_parties (user_id, party_id) VALUES (?, ?)");
                foreach ($partyIds as $pid) {
                    $partyStmt->execute([$newId, $pid]);
                }
            }

            $pdo->commit();

            respond([
                'success' => true,
                'message' => 'User created successfully.',
                'id' => $newId
            ], 201);

        } catch (PDOException $e) {

            $pdo->rollBack();

            respond([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);

        }

        break;

    // ==========================================================
    // PUT — update user + resync areas/parties + sync customer_id
    // ==========================================================
    case 'PUT':

        $id = $_GET['id'] ?? null;

        if (!$id) {
            respond([
                'success' => false,
                'message' => 'Missing user id.'
            ], 422);
        }

        $d = input();

        if (!empty($d['password']) && !empty($d['confirm_password']) && $d['confirm_password'] !== $d['password']) {
            respond(['success' => false, 'message' => 'Password and confirm password do not match.'], 422);
        }

        if (!empty($d['email']) && !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
            respond(['success' => false, 'message' => 'Invalid email address.'], 422);
        }

        try {

            $pdo->beginTransaction();

            $fields = [];
            $params = [];

            $allowedFields = [
                'employee_id', 'branch_id', 'city_id', 'username', 'user_type', 'shift',
                'role_id', 'status', 'full_name', 'email', 'phone',
            ];

            foreach ($allowedFields as $field) {
                if (array_key_exists($field, $d)) {
                    $fields[] = "$field = ?";
                    $params[] = ($d[$field] === '') ? null : $d[$field];
                }
            }

            if (!empty($d['password'])) {
                $fields[] = "password = ?";
                $params[] = password_hash($d['password'], PASSWORD_DEFAULT);
            }

            $profileImage = handle_profile_image_upload();
            if ($profileImage) {
                $fields[] = "profile_image = ?";
                $params[] = $profileImage;
            }

            if ($fields) {
                $params[] = $id;
                $sql = "UPDATE branch_users SET " . implode(', ', $fields) . " WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
            }

            // resync areas
            if (isset($d['area_ids']) && is_array($d['area_ids'])) {
                $del = $pdo->prepare("DELETE FROM user_areas WHERE user_id = ?");
                $del->execute([$id]);

                $areaStmt = $pdo->prepare("INSERT INTO user_areas (user_id, area_id) VALUES (?, ?)");
                foreach (array_map('intval', $d['area_ids']) as $aid) {
                    $areaStmt->execute([$id, $aid]);
                }
            }

            // resync parties + sync customer_id with first selected party
            if (isset($d['party_ids']) && is_array($d['party_ids'])) {
                $del = $pdo->prepare("DELETE FROM user_parties WHERE user_id = ?");
                $del->execute([$id]);

                $partyIdsClean = array_map('intval', $d['party_ids']);

                $partyStmt = $pdo->prepare("INSERT INTO user_parties (user_id, party_id) VALUES (?, ?)");
                foreach ($partyIdsClean as $pid) {
                    $partyStmt->execute([$id, $pid]);
                }

                $primaryCustomerId = !empty($partyIdsClean) ? $partyIdsClean[0] : null;
                $syncStmt = $pdo->prepare("UPDATE branch_users SET customer_id = ? WHERE id = ?");
                $syncStmt->execute([$primaryCustomerId, $id]);
            }

            $pdo->commit();

            respond([
                'success' => true,
                'message' => 'User updated successfully.'
            ]);

        } catch (PDOException $e) {

            $pdo->rollBack();

            respond([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);

        }

        break;

    // ==========================================================
    // DELETE — remove user + cascade cleanup
    // ==========================================================
    case 'DELETE':

        $id = $_GET['id'] ?? null;

        if (!$id) {
            respond([
                'success' => false,
                'message' => 'Missing user id.'
            ], 422);
        }

        // Prevent an admin from deleting their own account by mistake.
        $currentUser = rbac_current_user();
        if ($currentUser && (int) $currentUser['id'] === (int) $id) {
            respond([
                'success' => false,
                'message' => 'You cannot delete your own account while logged in.'
            ], 409);
        }

        try {

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("DELETE FROM branch_users WHERE id = ?");
            $stmt->execute([$id]);

            if ($stmt->rowCount() == 0) {
                $pdo->rollBack();
                respond([
                    'success' => false,
                    'message' => 'User not found.'
                ], 404);
            }

            $pdo->prepare("DELETE FROM user_areas WHERE user_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM user_parties WHERE user_id = ?")->execute([$id]);

            $pdo->commit();

            respond([
                'success' => true,
                'message' => 'User deleted successfully.'
            ]);

        } catch (PDOException $e) {

            $pdo->rollBack();

            respond([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);

        }

        break;

    default:

        respond([
            'success' => false,
            'message' => 'Method Not Allowed.'
        ], 405);

        break;
}

function input()
{
    $json = json_decode(file_get_contents('php://input'), true);

    if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
        return $json;
    }

    return $_POST;
}

/**
 * Handles an optional multipart profile image upload ($_FILES['profile_image']).
 * Returns a relative path to store in the DB, or null if no file was sent.
 * Validates extension + mime type and writes with a random filename to
 * prevent path traversal / overwrite / executable-upload attacks.
 */
function handle_profile_image_upload(): ?string
{
    if (empty($_FILES['profile_image']) || $_FILES['profile_image']['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $file = $_FILES['profile_image'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        respond(['success' => false, 'message' => 'Profile image upload failed.'], 422);
    }

    if ($file['size'] > 2 * 1024 * 1024) { // 2MB limit
        respond(['success' => false, 'message' => 'Profile image must be smaller than 2MB.'], 422);
    }

    $allowedMimes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!isset($allowedMimes[$mime])) {
        respond(['success' => false, 'message' => 'Only JPG, PNG or WEBP images are allowed.'], 422);
    }

    $uploadDir = __DIR__ . '/../../assets/images/profiles';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $allowedMimes[$mime];
    $destination = $uploadDir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        respond(['success' => false, 'message' => 'Could not save the uploaded image.'], 500);
    }

    return 'assets/images/profiles/' . $filename;
}

function respond($data, $status = 200)
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}