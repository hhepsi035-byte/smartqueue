<?php
session_start();
date_default_timezone_set('Asia/Kolkata'); // Syncs PHP time with your local system time
require_once 'submit.php'; // Database connection ($conn)

// Handle AJAX actions securely
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');
    
    $action = $_POST['ajax_action'] ?? '';
    $employee_id = $_SESSION['emp_id'] ?? 'EMP001';

    if ($action === 'start_meeting') {
        $booking_code = trim($_POST['booking_code'] ?? '');
        $otp = trim($_POST['otp'] ?? '');

        if (empty($booking_code) || empty($otp)) {
            echo json_encode(['status' => 'error', 'message' => 'Booking code and OTP are required.']);
            exit();
        }

        // 1. Verify appointment exists in the database
        $checkAppt = $conn->prepare("SELECT id, scheduled_time, meeting_status FROM appointments WHERE booking_ref = ? AND otp = ?");
        if (!$checkAppt) {
            echo json_encode(['status' => 'error', 'message' => 'Database prepare error: ' . $conn->error]);
            exit();
        }
        
        $checkAppt->bind_param("ss", $booking_code, $otp);
        $checkAppt->execute();
        $result = $checkAppt->get_result();

        if ($row = $result->fetch_assoc()) {
            $checkAppt->close();

            // RULE 1: Single-Use & Status Check
            if (strtolower($row['meeting_status']) !== 'waiting') {
                echo json_encode(['status' => 'error', 'message' => 'Error: This code has already been used, completed, or expired. Current status: ' . $row['meeting_status']]);
                exit();
            }

            // RULE 2: Time Window Validation (10 mins before to 30 mins after scheduled time)
            $currentTimestamp = time();
            $scheduledTimestamp = strtotime($row['scheduled_time']);

            $windowStart = $scheduledTimestamp - (10 * 60); // Exactly 10 minutes before
            $windowEnd = $scheduledTimestamp + (30 * 60);   // Exactly 30 minutes after

            $formatted_scheduled = date('d-m-Y H:i', $scheduledTimestamp);
            $formatted_start = date('d-m-Y H:i', $windowStart);
            $formatted_end = date('d-m-Y H:i', $windowEnd);

            if ($currentTimestamp < $windowStart) {
                echo json_encode([
                    'status' => 'error', 
                    'message' => "Too early! Scheduled time is $formatted_scheduled. This slot is only accessible between $formatted_start and $formatted_end."
                ]);
                exit();
            }

            if ($currentTimestamp > $windowEnd) {
                // Automatically expire the appointment in the database
                $expireStmt = $conn->prepare("UPDATE appointments SET meeting_status = 'expired' WHERE id = ?");
                $expireStmt->bind_param("i", $row['id']);
                $expireStmt->execute();
                $expireStmt->close();

                echo json_encode([
                    'status' => 'error', 
                    'message' => "Expired! The validity window for this appointment ended at $formatted_end (Scheduled: $formatted_scheduled). Reference code and OTP are now expired."
                ]);
                exit();
            }

            // 3. Insert active visit record into customer_visits
            $stmt = $conn->prepare("INSERT INTO customer_visits (employee_id, booking_reference, otp) VALUES (?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("sss", $employee_id, $booking_code, $otp);
                if ($stmt->execute()) {
                    $visit_id = $stmt->insert_id;
                    
                    // Mark appointment as 'in_progress' so it can never be reused
                    $updateStatus = $conn->prepare("UPDATE appointments SET meeting_status = 'in_progress' WHERE booking_ref = ?");
                    $updateStatus->bind_param("s", $booking_code);
                    $updateStatus->execute();
                    $updateStatus->close();

                    echo json_encode(['status' => 'success', 'visit_id' => $visit_id]);
                } else {
                    echo json_encode(['status' => 'error', 'message' => $stmt->error]);
                }
                $stmt->close();
            }
        } else {
            $checkAppt->close();
            echo json_encode(['status' => 'error', 'message' => 'Invalid Booking Reference Code or OTP. Please check the credentials.']);
        }
        exit();
    }

    if ($action === 'end_meeting') {
        $visit_id = intval($_POST['visit_id'] ?? 0);
        $meeting_status = $_POST['meeting_status'] ?? 'Completed';

        // 1. Update status in customer_visits table
        $stmt = $conn->prepare("UPDATE customer_visits SET status = ?, ended_at = CURRENT_TIMESTAMP WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("si", $meeting_status, $visit_id);
            $stmt->execute();
            $stmt->close();
        }

        // 2. Automatically mark appointment as completed
        $updateAppt = $conn->prepare("UPDATE appointments SET meeting_status = 'completed' WHERE booking_ref = (SELECT booking_reference FROM customer_visits WHERE id = ?)");
        if ($updateAppt) {
            $updateAppt->bind_param("i", $visit_id);
            $updateAppt->execute();
            $updateAppt->close();
        }

        echo json_encode(['status' => 'success']);
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Counter Management - SmartQueue</title>
    <link rel="stylesheet" href="officerpanel.css">
</head>
<body class="admin-body">
    <div class="counter-container">
        <h2>Counter Management</h2>
        <p>Staff: Officer John</p>

        <form id="counterForm">
            <div class="form-group">
                <label>Booking Reference Code</label>
                <input type="text" id="booking_code" required placeholder="BK-XXXXXX">
            </div>
            <div class="form-group">
                <label>6-Digit User OTP</label>
                <input type="text" id="otp" required placeholder="123456" maxlength="6">
            </div>
            <button type="button" id="btnStart" class="btn-primary">Start Meeting (Verify OTP)</button>
        </form>
        <br>
        <a href="logout.php" style="color: #2563eb; text-decoration: none; font-weight: bold;">Logout Portal</a>
    </div>

    <!-- Active Meeting Control Modal -->
    <div id="meetingModal" class="modal" style="display:none;">
        <div class="modal-content">
            <h3>Active Session</h3>
            <p>Booking: <span id="modalBooking"></span> | OTP: <span id="modalOtp"></span></p>
            <div id="timeCounter" style="font-size: 28px; font-weight: bold; margin: 15px 0;">00:00:00</div>
            <button id="btnEnd" class="btn-success">Finish Meeting</button>
            <button id="btnNoShow" class="btn-danger">Mark No-Show</button>
        </div>
    </div>

    <script>
        // Automatically fill input fields if URL has booking_code and otp parameters
        window.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            const bookingCodeParam = urlParams.get('booking_code');
            const otpParam = urlParams.get('otp');

            if (bookingCodeParam) {
                document.getElementById('booking_code').value = bookingCodeParam;
            }
            if (otpParam) {
                document.getElementById('otp').value = otpParam;
            }
        });

        let timerInterval = null;
        let secondsElapsed = 0;
        let currentVisitId = null;

        const btnStart = document.getElementById('btnStart');
        const btnEnd = document.getElementById('btnEnd');
        const btnNoShow = document.getElementById('btnNoShow');
        const modal = document.getElementById('meetingModal');
        const timeCounter = document.getElementById('timeCounter');

        // Start Meeting Action
        btnStart.addEventListener('click', function () {
            const bookingCode = document.getElementById('booking_code').value.trim();
            const otp = document.getElementById('otp').value.trim();

            if (!bookingCode || !otp) {
                alert('Please enter both Booking Reference Code and OTP.');
                return;
            }

            const formData = new FormData();
            formData.append('ajax_action', 'start_meeting');
            formData.append('booking_code', bookingCode);
            formData.append('otp', otp);

            fetch('officerpanel.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    currentVisitId = data.visit_id;
                    document.getElementById('modalBooking').innerText = bookingCode;
                    document.getElementById('modalOtp').innerText = otp;
                    
                    modal.style.display = 'flex';
                    startTimer();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(err => {
                console.error(err);
                alert('Network or script error. Check F12 Console.');
            });
        });

        function startTimer() {
            secondsElapsed = 0;
            timeCounter.innerText = "00:00:00";
            timerInterval = setInterval(() => {
                secondsElapsed++;
                let hrs = Math.floor(secondsElapsed / 3600);
                let mins = Math.floor((secondsElapsed % 3600) / 60);
                let secs = secondsElapsed % 60;
                timeCounter.innerText = String(hrs).padStart(2, '0') + ':' + 
                                        String(mins).padStart(2, '0') + ':' + 
                                        String(secs).padStart(2, '0');
            }, 1000);
        }

        function stopTimer() {
            clearInterval(timerInterval);
        }

        btnEnd.addEventListener('click', function () { finishMeeting('Completed'); });
        btnNoShow.addEventListener('click', function () { finishMeeting('No-Show'); });

        function finishMeeting(status) {
            if (!currentVisitId) {
                alert("Error: currentVisitId is missing!");
                return;
            }

            const formData = new FormData();
            formData.append('ajax_action', 'end_meeting');
            formData.append('visit_id', currentVisitId);
            formData.append('meeting_status', status);

            fetch('officerpanel.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    stopTimer();
                    modal.style.display = 'none';
                    if (document.getElementById('counterForm')) {
                        document.getElementById('counterForm').reset();
                    }
                    alert('Meeting logged successfully with status: ' + status);
                    location.reload();
                } else {
                    alert('Error updating meeting status: ' + data.message);
                }
            })
            .catch(err => {
                console.error("Fetch error:", err);
                alert('Network or script error. Check F12 Console.');
            });
        }
    </script>
</body>
</html>