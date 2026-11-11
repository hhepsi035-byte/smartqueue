<?php
session_start();
require 'submit.php'; // Database connection ($conn)

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employee_id = trim($_POST['employee_id'] ?? '');
    $password = trim($_POST['password'] ?? '');

    // Check if Employee ID starts with "EMP"
    if (strpos(strtoupper($employee_id), 'EMP') !== 0) {
        echo "<script>alert('Access Denied: Invalid Employee ID format. Must start with EMP.'); window.history.back();</script>";
        exit();
    }

    $stmt = $conn->prepare("SELECT id, name, password FROM employees WHERE employee_id = ?");
    $stmt->bind_param("s", $employee_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $emp = $result->fetch_assoc();
        // Plaintext check matching database demo; use password_verify() in production
        if ($password === $emp['password']) {
            $_SESSION['emp_logged_in'] = true;
            $_SESSION['emp_id'] = $employee_id;
            $_SESSION['emp_name'] = $emp['name'];
            header("Location: officerpanel.php");
            exit();
        }
    }

    echo "<script>alert('Invalid Employee ID or Password!'); window.history.back();</script>";
    $stmt->close();
}
?>