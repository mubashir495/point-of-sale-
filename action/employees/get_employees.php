<?php
require_once __DIR__ . '/../../config/rbac.php';
rbac_require_permission('employees', 'view');

    header('Content-Type: application/json');
    include "../../config/db.php";

    $branchId = isset($_GET['branch_id']) && $_GET['branch_id'] !== '' ? (int)$_GET['branch_id'] : null;

    if ($branchId) {
        $stmt = $conn->prepare("SELECT * FROM employees WHERE branch_id = ? ORDER BY id DESC");
        $stmt->bind_param("i", $branchId);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = mysqli_query($conn, "SELECT * FROM employees ORDER BY id DESC");
    }
    if (!$result) {
        echo json_encode(["status" => false, "message" => mysqli_error($conn)]);
        exit;
    }

    $employees = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $employees[] = $row;
    }

    echo json_encode($employees);