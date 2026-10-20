<?php
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('employees', 'view');

/**
 * GET /action/employees/list.php
 *
 * Lightweight support endpoint used by the attendance dashboard to
 * populate the "Mark Attendance" employee dropdown and the
 * "Mark All Present" bulk action. Reads only from the existing
 * `employees` table — no modification to that table's structure.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_response(false, 'Only GET method is allowed.', [], 405);
}

$database = new Database();
$conn = $database->getConnection();

try {
    $stmt = $conn->prepare("
        SELECT id, full_name, designation, contact, shift, photo
        FROM employees
        ORDER BY full_name ASC
    ");
    $stmt->execute();
    $employees = $stmt->fetchAll();

    send_response(true, 'Employees fetched successfully.', ['records' => $employees]);
} catch (Exception $e) {
    send_response(false, 'Failed to fetch employees: ' . $e->getMessage(), [], 500);
}
