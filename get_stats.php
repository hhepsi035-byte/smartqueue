<?php
error_reporting(0);
@ini_set('display_errors', 0);
header('Content-Type: application/json');

if (file_exists('submit.php')) {
    include 'submit.php';
}

$fallback = [
    "success" => true,
    "appointments" => "0+",
    "departments" => "2",
    "waiting_time" => "0 min",
    "citizens_served" => "0+",
    "current_token" => "A-001",
    "people_waiting" => 0
];

if (!isset($conn) || $conn->connect_error) {
    echo json_encode($fallback);
    exit;
}

// 1. Total Appointments
$apptResult = $conn->query("SELECT COUNT(*) AS total FROM appointments");
$totalAppointments = ($apptResult && $row = $apptResult->fetch_assoc()) ? $row['total'] : 0;

// 2. Active Departments
$deptResult = $conn->query("SELECT COUNT(*) AS total FROM departments");
$totalDepartments = ($deptResult && $row = $deptResult->fetch_assoc()) ? max(2, $row['total']) : 2;

// 3. People Waiting Count
$waitingResult = $conn->query("SELECT COUNT(*) AS total FROM appointments WHERE meeting_status = 'waiting'");
$peopleWaiting = ($waitingResult && $row = $waitingResult->fetch_assoc()) ? $row['total'] : 0;

// 4. Citizens Served Count (Completed/Ended meetings)
$servedResult = $conn->query("SELECT COUNT(*) AS total FROM appointments WHERE LOWER(meeting_status) IN ('completed', 'served', 'ended')");
$totalServed = ($servedResult && $row = $servedResult->fetch_assoc()) ? $row['total'] : 0;

// 5. Estimated Wait Time Calculation (30 mins per person)
$estimatedMinutes = $peopleWaiting * 30;
$waitTimeFormatted = $estimatedMinutes . " min";

// 6. Dynamic Alternating Token Generation (Pool A & Pool B)
$latestResult = $conn->query("SELECT id FROM appointments ORDER BY id DESC LIMIT 1");
$latestRow = $latestResult ? $latestResult->fetch_assoc() : null;
$latestId = $latestRow ? $latestRow['id'] : 1;

if ($latestId % 2 == 0) {
    $poolNum = ceil($latestId / 2);
    $currentToken = "B-" . str_pad($poolNum, 3, "0", STR_PAD_LEFT);
} else {
    $poolNum = floor($latestId / 2) + 1;
    $currentToken = "A-" . str_pad($poolNum, 3, "0", STR_PAD_LEFT);
}

// JSON Output
echo json_encode([
    "success" => true,
    "appointments" => $totalAppointments . "+",
    "departments" => $totalDepartments . "+",
    "waiting_time" => $waitTimeFormatted,
    "citizens_served" => $totalServed . "+",
    "current_token" => $currentToken,
    "people_waiting" => $peopleWaiting
]);

$conn->close();
?>