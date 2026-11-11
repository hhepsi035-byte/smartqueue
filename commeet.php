<?php
header("Content-Type: application/json");
require_once 'submit.php'; // Assumes this initializes your $pdo connection

$today = date('Y-m-d');

try {
    // 1. Try to fetch today's record using PDO
    $stmt = $pdo->prepare("SELECT * FROM daily_stats WHERE stat_date = ? LIMIT 1");
    $stmt->execute([$today]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    // 2. Fallback: If no record exists for today, fetch the latest available row
    if (!$data) {
        $latestStmt = $pdo->query("SELECT * FROM daily_stats ORDER BY id DESC LIMIT 1");
        $data = $latestStmt->fetch(PDO::FETCH_ASSOC);
    }

    // Output JSON response matching your frontend expectation
    echo json_encode([
        "status" => "success",
        "current_token"        => $data['current_token'] ?? 'A-018',
        "available_appointments" => (int)($data['available_appointments'] ?? 500),
        "active_departments"   => (int)($data['active_departments'] ?? 25),
        "avg_wait_reduction"   => (int)($data['avg_wait_reduction'] ?? 40),
        "offline_capacity"     => (int)($data['offline_capacity'] ?? 150)
    ]);

} catch (Exception $e) {
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}
?>