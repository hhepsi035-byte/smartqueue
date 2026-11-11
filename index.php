<?php
// Turn on error reporting to see what is failing
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Include your connection file safely
if (file_exists('submit.php')) {
    include 'submit.php';
} else {
    die("Error: submit.php not found in this directory.");
}

$user_name = "Guest User";
$user_email = "Not Available";
$user_hint = "Not Available";
$booked_slots = [];

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    
    // Check if $conn exists from submit.php
    if (isset($conn)) {
        $stmt = $conn->prepare("SELECT username, contact FROM users WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                $user_name = $row['username'];
                $user_email = $row['contact'];
                $user_hint = $row['username'];  
            }
            $stmt->close();
        }
    } else {
        echo "<p style='color:red;'>Database connection object (\$conn) is not defined in submit.php!</p>";
    }
}
?>