<?php
/**
 * Shared helper functions used by every attendance API endpoint.
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

/**
 * Send a standard JSON response and stop execution.
 */
function send_response($success, $message, $data = [], $httpCode = 200)
{
    http_response_code($httpCode);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data'    => $data
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Read JSON body of the request as an associative array.
 */
function get_json_input()
{
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

/**
 * Calculate working hours (decimal) between two HH:MM(:SS) times.
 * Handles overnight shifts (check_out earlier than check_in).
 */
function calculate_working_hours($checkIn, $checkOut)
{
    if (empty($checkIn) || empty($checkOut)) {
        return 0.0;
    }

    try {
        $in  = new DateTime($checkIn);
        $out = new DateTime($checkOut);

        // Overnight shift: check-out is on the next day.
        if ($out <= $in) {
            $out->modify('+1 day');
        }

        $diffSeconds = $out->getTimestamp() - $in->getTimestamp();
        $hours = $diffSeconds / 3600;
        return round(max(0, $hours), 2);
    } catch (Exception $e) {
        return 0.0;
    }
}

/**
 * Calculate overtime hours given worked hours and a standard shift length.
 * Default standard shift = 8 hours.
 */
function calculate_overtime($workingHours, $standardHours = 8.0)
{
    $overtime = $workingHours - $standardHours;
    return $overtime > 0 ? round($overtime, 2) : 0.0;
}

/**
 * Validate a date string in Y-m-d format.
 */
function is_valid_date($date)
{
    if (empty($date)) return false;
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

/**
 * Validate a time string in H:i or H:i:s format.
 */
function is_valid_time($time)
{
    if (empty($time)) return true; // time is optional in some flows
    $d = DateTime::createFromFormat('H:i:s', $time) ?: DateTime::createFromFormat('H:i', $time);
    return $d !== false;
}

/**
 * Write an entry into attendance_logs for auditing.
 */
function write_attendance_log($conn, $attendanceId, $employeeId, $action, $oldValues, $newValues, $performedBy = 'system')
{
    $stmt = $conn->prepare("
        INSERT INTO attendance_logs (attendance_id, employee_id, action, old_values, new_values, performed_by)
        VALUES (:attendance_id, :employee_id, :action, :old_values, :new_values, :performed_by)
    ");
    $stmt->execute([
        ':attendance_id' => $attendanceId,
        ':employee_id'   => $employeeId,
        ':action'        => $action,
        ':old_values'    => $oldValues ? json_encode($oldValues) : null,
        ':new_values'    => $newValues ? json_encode($newValues) : null,
        ':performed_by'  => $performedBy,
    ]);
}
