<?php
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('attendance', 'edit');


header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

$hasId = isset($data['id']) && (int)$data['id'] > 0;
$hasEmployeeDate = isset($data['employee_id']) && isset($data['attendance_date']);

if (!$data || (!$hasId && !$hasEmployeeDate)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid payload. Provide "id" OR "employee_id" + "attendance_date".']);
    exit;
}

$allowedStatus = ['Present', 'Absent', 'Leave'];
$status = (isset($data['status']) && in_array($data['status'], $allowedStatus, true)) ? $data['status'] : 'Present';
$remarks = isset($data['remarks']) ? trim((string)$data['remarks']) : '';

try {
    $db = new Database();
    $conn = $db->getConnection();

    if ($hasId) {
        $sql = "UPDATE attendance
                SET status = :status, remarks = :remarks, updated_at = NOW()
                WHERE id = :id";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':status'  => $status,
            ':remarks' => $remarks,
            ':id'      => (int)$data['id'],
        ]);
    } else {
        $attendance_date = trim($data['attendance_date']);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $attendance_date)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid attendance_date format. Expected YYYY-MM-DD.']);
            exit;
        }

        // Upsert behaviour: update if exists, otherwise insert (keeps "one per employee per day" rule intact)
        $sql = "INSERT INTO attendance (employee_id, attendance_date, status, remarks, created_at, updated_at)
                VALUES (:employee_id, :attendance_date, :status, :remarks, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    status = VALUES(status),
                    remarks = VALUES(remarks),
                    updated_at = NOW()";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':employee_id'     => (int)$data['employee_id'],
            ':attendance_date' => $attendance_date,
            ':status'          => $status,
            ':remarks'         => $remarks,
        ]);
    }

    echo json_encode(['success' => true, 'message' => 'Attendance updated successfully.']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}