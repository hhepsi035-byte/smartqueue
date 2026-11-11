<?php
date_default_timezone_set('Asia/Kolkata'); // Match your local timezone
require_once 'submit.php'; // Your database connection ($conn)

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $booking_ref = trim($_POST['booking_ref']);
    $otp = trim($_POST['otp']);
    $current_time = new DateTime(); // Current server time

    // 1. Fetch appointment details using scheduled_time (No appointment_date needed!)
    $stmt = $conn->prepare("SELECT id, scheduled_time, otp_status, is_used FROM appointments WHERE booking_ref = ? AND otp = ?");
    if (!$stmt) {
        die("Error: Database prepare error: " . $conn->error);
    }
    
    $stmt->bind_param("ss", $booking_ref, $otp);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        // 2. Single-Use Check: Check if already verified or used
        if ($row['otp_status'] === 'verified' || $row['is_used'] == 1) {
            echo "<script>alert('Error: This reference code and OTP have already been used.'); window.history.back();</script>";
            exit();
        }

        // 3. Time Window Check (±30 minutes of scheduled_time)
        $scheduled_dt = new DateTime($row['scheduled_time']);

        $window_start = clone $scheduled_dt;
        $window_start->modify('-30 minutes'); // 30 mins before appointment

        $window_end = clone $scheduled_dt;
        $window_end->modify('+30 minutes');   // 30 mins after appointment

        if ($current_time < $window_start) {
            echo "<script>alert('Error: Too early! This code is only valid starting from 30 minutes before your appointment time (" . $window_start->format('d-m-Y H:i') . ").'); window.history.back();</script>";
            exit();
        }

        if ($current_time > $window_end) {
            // Mark as expired if past the 30-minute window
            $update_expired = $conn->prepare("UPDATE appointments SET otp_status = 'expired' WHERE id = ?");
            $update_expired->bind_param("i", $row['id']);
            $update_expired->execute();

            echo "<script>alert('Error: This code has expired. It was only valid until 30 minutes after your scheduled appointment time (" . $window_end->format('d-m-Y H:i') . ").'); window.history.back();</script>";
            exit();
        }

        // 4. Mark as verified and used immediately (Single-use enforcement)
        $update_stmt = $conn->prepare("UPDATE appointments SET otp_status = 'verified', is_used = 1, verified_at = NOW() WHERE id = ?");
        $update_stmt->bind_param("i", $row['id']);
        
        if ($update_stmt->execute()) {
            echo "<script>alert('Success! Appointment verified and checked in successfully.'); window.location.href='dashboard.php';</script>";
        } else {
            echo "<script>alert('Database error updating status.'); window.history.back();</script>";
        }

    } else {
        echo "<script>alert('Invalid Booking Reference or OTP.'); window.history.back();</script>";
    }
}
?>