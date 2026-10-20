<?php
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('attendance', 'view');


header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';

$date = isset($_GET['date']) && $_GET['date'] !== '' ? trim($_GET['date']) : date('Y-m-d');
$shift = isset($_GET['shift']) ? trim($_GET['shift']) : '';

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid date format. Expected YYYY-MM-DD.']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    if ($shift !== '') {
        $sql = "SELECT a.id, a.employee_id, a.attendance_date, a.status, a.remarks
                FROM attendance a
                INNER JOIN employees e ON e.id = a.employee_id
                WHERE a.attendance_date = :date AND e.shift = :shift";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':date' => $date, ':shift' => $shift]);
    } else {
        $sql = "SELECT id, employee_id, attendance_date, status, remarks
                FROM attendance
                WHERE attendance_date = :date";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':date' => $date]);
    }

    $rows = $stmt->fetchAll();

    echo json_encode(['success' => true, 'date' => $date, 'shift' => $shift, 'data' => $rows]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}