<?php
session_start();
include 'submit.php';

$response = [
    'logged_in' => false,
    'username' => 'Guest User',
    'contact' => 'Not Available',
    'hint' => 'Not Available'
];

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    
    $stmt = $conn->prepare("SELECT username, contact FROM users WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $response['logged_in'] = true;
            $response['username'] = $row['username'];
            $response['contact'] = $row['contact'];
            $response['hint'] = $row['username']; // Uses username as the password hint display
        }
        $stmt->close();
    }
}

header('Content-Type: application/json');
echo json_encode($response);
?>