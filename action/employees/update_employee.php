<?php
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('employees', 'edit');

header('Content-Type: application/json');
include "../../config/db.php";

$id            = intval($_POST['id'] ?? 0);
$name          = trim($_POST['full_name'] ?? '');
$cnic          = trim($_POST['cnic'] ?? '');
$designation   = trim($_POST['designation'] ?? '');
$contact       = trim($_POST['contact'] ?? '');
$salary        = floatval($_POST['salary'] ?? 0);
$qualification = trim($_POST['qualification'] ?? '');
$shift         = trim($_POST['shift'] ?? 'Morning');
$hire_date     = !empty($_POST['hire_date']) ? $_POST['hire_date'] : NULL;
$address       = trim($_POST['address'] ?? '');
$username      = trim($_POST['username'] ?? '');
$password      = (string) ($_POST['password'] ?? '');
$roleId        = !empty($_POST['role_id']) ? (int) $_POST['role_id'] : null;

if ($id <= 0 || empty($name) || empty($designation) || empty($shift)) {
    echo json_encode([
        "status" => false,
        "message" => "Invalid data."
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| Get Existing Employee
|--------------------------------------------------------------------------
*/
$getEmployee = mysqli_prepare($conn, "SELECT photo FROM employees WHERE id = ?");
mysqli_stmt_bind_param($getEmployee, "i", $id);
mysqli_stmt_execute($getEmployee);
$result = mysqli_stmt_get_result($getEmployee);

if (!$employee = mysqli_fetch_assoc($result)) {
    echo json_encode([
        "status" => false,
        "message" => "Employee not found."
    ]);
    exit;
}

$imageName = $employee['photo'];
    
if (
    isset($_FILES['photo']) &&
    $_FILES['photo']['error'] == UPLOAD_ERR_OK &&
    !empty($_FILES['photo']['name'])
) {

    $uploadDir = "../../uploads/";

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));

    $allowed = ['jpg', 'jpeg', 'png', 'webp'];

    if (!in_array($ext, $allowed)) {
        echo json_encode([
            "status" => false,
            "message" => "Only JPG, JPEG, PNG and WEBP images are allowed."
        ]);
        exit;
    }

    $imageName = time() . "_" . uniqid() . "." . $ext;

    move_uploaded_file(
        $_FILES['photo']['tmp_name'],
        $uploadDir . $imageName
    );

    // Delete old image (optional)
    if (!empty($employee['photo'])) {
        $oldFile = $uploadDir . $employee['photo'];
        if (file_exists($oldFile)) {
            unlink($oldFile);
        }
    }
}

/*
|--------------------------------------------------------------------------
| Update Employee
|--------------------------------------------------------------------------
*/
$stmt = mysqli_prepare(
    $conn,
    "UPDATE employees SET
        full_name=?,
        cnic=?,
        designation=?,
        contact=?,
        salary=?,
        qualification=?,
        shift=?,
        hire_date=?,
        address=?,
        username=?,
        photo=?
    WHERE id=?"
);

mysqli_stmt_bind_param(
    $stmt,
    "ssssdssssssi",
    $name,
    $cnic,
    $designation,
    $contact,
    $salary,
    $qualification,
    $shift,
    $hire_date,
    $address,
    $username,
    $imageName,
    $id
);

if (mysqli_stmt_execute($stmt)) {

    // Keep the linked login (if any / if a username was given) in sync:
    // username, role-from-designation, and password (only if a new one
    // was typed - blank means "keep unchanged").
    if ($username !== '') {
        rbac_upsert_employee_login($id, $username, $password, $designation, $roleId);
    }

    echo json_encode([
        "status" => true,
        "message" => "Employee updated successfully."
    ]);

} else {

    echo json_encode([
        "status" => false,
        "message" => mysqli_error($conn)
    ]);

}

mysqli_stmt_close($stmt);
mysqli_close($conn);