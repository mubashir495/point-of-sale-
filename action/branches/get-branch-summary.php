<?php
/**
 * action/branches/get-branch-summary.php
 * ------------------------------------------------------------------
 * Overall Branch Summary shown at the bottom of the Branch
 * Management table:
 *   - Total Workers: every employee across all branches
 *   - Currently Working: employees marked 'Present' in today's
 *     attendance (reuses the existing `attendance` table/logic - no
 *     new status tracking is introduced).
 * ------------------------------------------------------------------
 */
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('users', 'view');

header('Content-Type: application/json');

try {
    $pdo = rbac_db();

    $totalWorkers = (int) $pdo->query("SELECT COUNT(*) FROM employees")->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT employee_id)
        FROM attendance
        WHERE attendance_date = CURDATE() AND status = 'Present'
    ");
    $stmt->execute();
    $currentlyWorking = (int) $stmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'data' => [
            'total_workers' => $totalWorkers,
            'currently_working' => $currentlyWorking,
        ],
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}