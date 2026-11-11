<?php
// Database configuration
$host = 'localhost';
$dbname = 'smartqueue'; // Your database name from phpMyAdmin
$username = 'root';     // Default XAMPP username
$password = '';         // Default XAMPP password

// Set header to return JSON response
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the posted data matching the HTML input names
    $feedbackText = isset($_POST['feedback']) ? trim($_POST['feedback']) : '';
    $reaction = isset($_POST['reaction']) ? trim($_POST['reaction']) : '';

    if (empty($feedbackText)) {
        echo json_encode(['status' => 'error', 'message' => 'Feedback text cannot be empty.']);
        exit;
    }

    try {
        // Connect to database using PDO
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Insert feedback using your exact table column names: feedback_text and reaction
        $stmt = $pdo->prepare("INSERT INTO feedback (feedback_text, reaction) VALUES (:feedback_text, :reaction)");
        $stmt->execute([
            ':feedback_text' => $feedbackText,
            ':reaction' => $reaction
        ]);

        echo json_encode(['status' => 'success', 'message' => 'Feedback submitted successfully!']);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
}
?>