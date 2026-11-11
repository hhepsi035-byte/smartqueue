<?php
session_start();
require_once 'submit.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $contact  = trim($_POST['contact']);

    if (!empty($username) && !empty($contact)) {
        // Query database to verify user details
        $sql = "SELECT id FROM users WHERE username = ? AND contact = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $username, $contact);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            // Generate temporary 6-digit OTP
            $otp = rand(100000, 999999);
            
            $_SESSION['reset_username'] = $username;
            $_SESSION['reset_otp']      = $otp;

            echo "<script>
                    alert('OTP generated successfully: $otp');
                    window.location.href = 'verify.html';
                  </script>";
        } else {
            echo "<script>
                    alert('No matching account found with provided details.');
                    window.location.href = 'forgot.html';
                  </script>";
        }
        $stmt->close();
    } else {
        echo "<script>
                alert('Please fill in all fields.');
                window.location.href = 'forgot.html';
              </script>";
    }
}
$conn->close();
?>