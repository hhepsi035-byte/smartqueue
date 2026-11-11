<?php
session_start();
require 'submit.php'; // Uses your database connection ($conn)

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date = $_POST['scheduled_date'] ?? '';
    $time = $_POST['scheduled_time'] ?? '';

    if (empty($date) || empty($time)) {
        echo "<script>alert('Please select both date and time.'); window.history.back();</script>";
        exit();
    }

    $scheduled_time = $date . ' ' . $time . ':00';

    // Get logged-in user ID, or fallback to first registered user
    if (!empty($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
    } else {
        $user_res = $conn->query("SELECT id FROM users LIMIT 1");
        if ($user_res && $user_res->num_rows > 0) {
            $row = $user_res->fetch_assoc();
            $user_id = $row['id'];
        } else {
            die("Error: No registered users found in 'users' table.");
        }
    }

    // Generate 6-digit OTP and unique Reference Code
    $otp = sprintf("%06d", mt_rand(1, 999999));
    $booking_ref = 'BK-' . strtoupper(substr(md5(uniqid()), 0, 8));

    // Calculate otp_expires_at (30 minutes after scheduled appointment time)
    $scheduled_dt = new DateTime($scheduled_time);
    $scheduled_dt->modify('+30 minutes');
    $otp_expires_at = $scheduled_dt->format('Y-m-d H:i:s');

    // Insert into appointments table using scheduled_time (no appointment_date column needed)
    $sql = "INSERT INTO appointments (user_id, booking_ref, otp, otp_status, meeting_status, scheduled_time, otp_expires_at, is_used) 
            VALUES (?, ?, ?, 'pending', 'waiting', ?, ?, 0)";
    
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        die("Database prepare error: " . $conn->error);
    }

    // Bind parameters: i = integer, s = string (5 parameters total: user_id, booking_ref, otp, scheduled_time, otp_expires_at)
    $stmt->bind_param("issss", $user_id, $booking_ref, $otp, $scheduled_time, $otp_expires_at);

    if ($stmt->execute()) {
        // Show confirmation popup with Token and OTP
        echo "
        <!DOCTYPE html>
        <html>
        <head>
            <title>Appointment Confirmed</title>
            <style>
                body { font-family: sans-serif; background: #f3f4f6; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
                .card { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); width: 350px; text-align: center; }
                .icon { width: 50px; height: 50px; background: #d1fae5; color: #059669; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 24px; margin: 0 auto 15px; }
                .details { background: #f9fafb; padding: 15px; border-radius: 8px; margin: 20px 0; text-align: left; }
                .code { font-size: 20px; font-weight: bold; color: #2563eb; letter-spacing: 1px; }
                .otp { font-size: 24px; font-weight: bold; color: #059669; letter-spacing: 3px; }
                .btn { display: block; background: #2563eb; color: white; text-decoration: none; padding: 10px; border-radius: 6px; margin-top: 15px; font-weight: bold; }
            </style>
        </head>
        <body>
            <div class='card'>
                <div class='icon'>✓</div>
                <h2>Appointment Booked!</h2>
                <p style='color: #6b7280; font-size: 14px;'>Save these details for your appointment check-in.</p>
                
                <div class='details'>
                    <div style='margin-bottom: 12px;'>
                        <small style='color: #6b7280;'>Booking Reference</small><br>
                        <span class='code'>$booking_ref</span>
                    </div>
                    <div>
                        <small style='color: #6b7280;'>Your OTP</small><br>
                        <span class='otp'>$otp</span>
                    </div>
                </div>

                <a href='appointment.html' class='btn'>Book Another</a>
            </div>
        </body>
        </html>
        ";
    } else {
        echo "Error saving appointment: " . $stmt->error;
    }
    
    $stmt->close();
}
?>