<?php
require_once 'submit.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user = trim($_POST['username']);
    $contact = trim($_POST['contact']);
    $pass = trim($_POST['password']);

    if (!empty($user) && !empty($contact) && !empty($pass)) {
        // Hash password securely before storing
        $hashed_password = password_hash($pass, PASSWORD_DEFAULT);

        // Check if username already exists
        $check_sql = "SELECT id FROM users WHERE username = ?";
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param("s", $user);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            echo "Username already taken. Please choose another.";
        } else {
            // Insert user into database
            $insert_sql = "INSERT INTO users (username, contact, password) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($insert_sql);
            $stmt->bind_param("sss", $user, $contact, $hashed_password);

            if ($stmt->execute()) {
                echo "Registration successful! <a href='login.html'>Login here</a>";
            } else {
                echo "Error: " . $stmt->error;
            }
        }
        $stmt->close();
    } else {
        echo "Please fill in all fields.";
    }
}
$conn->close();
?>