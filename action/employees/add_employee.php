<?php
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('employees', 'create');

header('Content-Type: application/json');
include "../../config/db.php";

$name          = trim($_POST['full_name'] ?? '');
$cnic          = trim($_POST['cnic'] ?? '');
$designation   = trim($_POST['designation'] ?? '');
$contact       = trim($_POST['contact'] ?? '');
$salary        = $_POST['salary'] ?? 0;
$qualification = trim($_POST['qualification'] ?? '');
$shift         = trim($_POST['shift'] ?? '');
$hire_date     = $_POST['hire_date'] ?: null;
$address       = trim($_POST['address'] ?? '');
$username      = trim($_POST['username'] ?? '');
$password      = (string) ($_POST['password'] ?? '');
$roleId        = !empty($_POST['role_id']) ? (int) $_POST['role_id'] : null;
$branchId      = !empty($_POST['branch_id']) ? (int) $_POST['branch_id'] : null;

if ($name === '' || $designation === '' || $shift === '') {
    echo json_encode(["status" => false, "message" => "Full name, designation and shift are required"]);
    exit;
}

$shiftColumn = mysqli_query($conn, "SHOW COLUMNS FROM employees LIKE 'shift'");
if ($shiftColumn && mysqli_num_rows($shiftColumn) === 0) {
    mysqli_query($conn, "ALTER TABLE employees ADD COLUMN shift VARCHAR(20) DEFAULT 'Morning'");
}

$imageName = "";
if (isset($_FILES['photo']) && $_FILES['photo']['error'] === 0) {
    $uploadDir = "../../uploads/";
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
    $imageName = time() . "_" . uniqid() . "." . $ext;
    move_uploaded_file($_FILES['photo']['tmp_name'], $uploadDir . $imageName);
}

$stmt = mysqli_prepare($conn,
    "INSERT INTO employees (full_name,cnic,designation,contact,salary,qualification,shift,hire_date,address,username,photo,branch_id)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?)"
);
if (!$stmt) {
    echo json_encode(["status" => false, "message" => mysqli_error($conn)]);
    exit;
}

mysqli_stmt_bind_param($stmt, "ssssdssssssi",
    $name, $cnic, $designation, $contact, $salary, $qualification, $shift, $hire_date, $address, $username, $imageName, $branchId
);

if (mysqli_stmt_execute($stmt)) {
    $newEmployeeId = mysqli_insert_id($conn);
    $loginInfo = null;
    if ($username !== '') {
        $loginInfo = rbac_upsert_employee_login($newEmployeeId, $username, $password, $designation, $roleId);
    }

    echo json_encode([
        "status" => true,
        "message" => "Employee added successfully",
        "id" => $newEmployeeId,
        "login_created" => (bool) $loginInfo,
    ]);
} else {
    echo json_encode(["status" => false, "message" => mysqli_error($conn)]);
}