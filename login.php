<?php
session_start();
require_once 'submit.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user = trim($_POST['username']);
    $pass = trim($_POST['password']);
   
    if (!empty($user) && !empty($pass)) {
        $sql = "SELECT id, username, password FROM users WHERE username = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $user);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            // Verify stored hashed password with user input
            if (password_verify($pass, $row['password'])) {
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['username'] = $row['username'];
                echo "Login successful! Welcome, " . htmlspecialchars($row['username']);
                // Redirect to dashboard: header("Location: dashboard.php");
            } else {
                echo "Invalid username or password.";
            }
        } else {
            echo "Invalid username or password.";
        }
        $stmt->close();
    } else {
        echo "Please enter both username and password.";
    }
}
$conn->close();
?>