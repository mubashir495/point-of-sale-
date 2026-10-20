<?php
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('attendance', 'view');

/**
 * Attendance Report API
 * Method: GET
 *
 * Mode 1 - Summary (default): mode=summary
 *   Params: start_date, end_date (required), shift (optional), employee_id (optional)
 *   Returns per-employee aggregated Present/Absent/Leave counts within the date range.
 *   NOTE: This endpoint does NOT return employee name/photo/designation.
 *   The frontend merges this with data from action/employees/get_employees.php by employee_id.
 *
 * Mode 2 - History: mode=history&employee_id=14
 *   Params: employee_id, start_date, end_date (required)
 *   Returns full attendance history + summary counts + attendance percentage for one employee.
 *   Used by the Employee Details modal.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';

$mode = isset($_GET['mode']) ? trim($_GET['mode']) : 'summary';
$start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';
$shift = isset($_GET['shift']) ? trim($_GET['shift']) : '';
$employee_id = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : 0;

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'start_date and end_date are required (YYYY-MM-DD).']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    if ($mode === 'history') {
        if ($employee_id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'employee_id is required for history mode.']);
            exit;
        }

        $sql = "SELECT id, attendance_date, status, remarks
                FROM attendance
                WHERE employee_id = :employee_id
                  AND attendance_date BETWEEN :start_date AND :end_date
                ORDER BY attendance_date DESC";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':employee_id' => $employee_id,
            ':start_date'  => $start_date,
            ':end_date'    => $end_date,
        ]);
        $history = $stmt->fetchAll();

        $present = 0;
        $absent = 0;
        $leave = 0;
        foreach ($history as $h) {
            if ($h['status'] === 'Present') $present++;
            elseif ($h['status'] === 'Absent') $absent++;
            elseif ($h['status'] === 'Leave') $leave++;
        }
        $total = count($history);
        $percentage = $total > 0 ? round(($present / $total) * 100, 2) : 0.0;

        echo json_encode([
            'success' => true,
            'employee_id' => $employee_id,
            'history' => $history,
            'summary' => [
                'present_count' => $present,
                'absent_count'  => $absent,
                'leave_count'   => $leave,
                'total_days'    => $total,
                'percentage'    => $percentage,
            ],
        ]);
        exit;
    }

    // ---- Summary mode ----
    $params = [':start_date' => $start_date, ':end_date' => $end_date];

    $shiftFilter = '';
    if ($shift !== '') {
        $shiftFilter = ' AND e.shift = :shift';
        $params[':shift'] = $shift;
    }

    $empFilter = '';
    if ($employee_id > 0) {
        $empFilter = ' AND a.employee_id = :employee_id';
        $params[':employee_id'] = $employee_id;
    }

    $sql = "SELECT
                a.employee_id,
                SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) AS present_count,
                SUM(CASE WHEN a.status = 'Absent'  THEN 1 ELSE 0 END) AS absent_count,
                SUM(CASE WHEN a.status = 'Leave'   THEN 1 ELSE 0 END) AS leave_count,
                COUNT(*) AS total_days,
                GROUP_CONCAT(DISTINCT NULLIF(a.remarks, '') SEPARATOR ' | ') AS remarks
            FROM attendance a
            INNER JOIN employees e ON e.id = a.employee_id
            WHERE a.attendance_date BETWEEN :start_date AND :end_date
            {$shiftFilter}
            {$empFilter}
            GROUP BY a.employee_id
            ORDER BY a.employee_id ASC";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    echo json_encode(['success' => true, 'data' => $rows]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}