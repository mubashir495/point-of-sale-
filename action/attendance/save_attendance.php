<?php
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('attendance', 'create');


header('Content-Type: application/json');

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../config/database.php';

try {

    $raw = file_get_contents("php://input");

    if (empty($raw)) {
        echo json_encode([
            "success" => false,
            "message" => "No data received."
        ]);
        exit;
    }

    $data = json_decode($raw, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode([
            "success" => false,
            "message" => "Invalid JSON: " . json_last_error_msg()
        ]);
        exit;
    }

    if (
        !isset($data['attendance_date']) ||
        !isset($data['records']) ||
        !is_array($data['records'])
    ) {
        echo json_encode([
            "success" => false,
            "message" => "attendance_date or records missing."
        ]);
        exit;
    }

    $attendance_date = $data['attendance_date'];
    $records = $data['records'];

    $db = new Database();
    $conn = $db->getConnection();

    $conn->beginTransaction();

    $sql = "
        INSERT INTO attendance
        (
            employee_id,
            attendance_date,
            status,
            remarks,
            created_at,
            updated_at
        )
        VALUES
        (
            :employee_id,
            :attendance_date,
            :status,
            :remarks,
            NOW(),
            NOW()
        )
        ON DUPLICATE KEY UPDATE
            status = VALUES(status),
            remarks = VALUES(remarks),
            updated_at = NOW()
    ";

    $stmt = $conn->prepare($sql);

    $saved = 0;

    foreach ($records as $row) {

        if (!isset($row['employee_id'])) {
            continue;
        }

        $employee_id = (int)$row['employee_id'];

        $status = isset($row['status'])
            ? $row['status']
            : 'Present';

        $remarks = isset($row['remarks'])
            ? trim($row['remarks'])
            : '';

        $result = $stmt->execute([
            ':employee_id' => $employee_id,
            ':attendance_date' => $attendance_date,
            ':status' => $status,
            ':remarks' => $remarks
        ]);

        if (!$result) {

            $conn->rollBack();

            $error = $stmt->errorInfo();

            echo json_encode([
                "success" => false,
                "message" => $error[2]
            ]);

            exit;
        }

        $saved++;
    }

    $conn->commit();

    echo json_encode([
        "success" => true,
        "message" => "Attendance saved successfully.",
        "saved" => $saved
    ]);

} catch (PDOException $e) {

    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);

} catch (Exception $e) {

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}