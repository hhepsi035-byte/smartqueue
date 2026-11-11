<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Queue Management</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f4f6f9; }
        .card { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .btn { padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; color: white; }
        .btn-verify { background: #28a745; }
        .btn-end { background: #dc3545; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
    </style>
</head>
<body>

    <!-- OTP Verification Form -->
    <div class="card">
        <h3>Verify Visitor Arrival</h3>
        <form action="verify_otp.php" method="POST">
            <input type="text" name="booking_ref" placeholder="Booking Reference" required>
            <input type="text" name="otp" placeholder="Enter 6-Digit OTP" required>
            <button type="submit" class="btn btn-verify">Verify & Start Meeting</button>
        </form>
    </div>

    <!-- Active Meetings Table -->
    <div class="card">
        <h3>Ongoing Meetings</h3>
        <table>
            <tr>
                <th>Ref ID</th>
                <th>Status</th>
                <th>Started At</th>
                <th>Action</th>
            </tr>
            <!-- Loop through SQL query: SELECT * FROM appointments WHERE meeting_status = 'in_meeting' -->
            <tr>
                <td>BK-A1B2C3D4</td>
                <td>In Meeting</td>
                <td>10:30 AM</td>
                <td>
                    <form action="complete_meeting.php" method="POST">
                        <input type="hidden" name="appointment_id" value="1">
                        <button type="submit" class="btn btn-end">End Meeting</button>
                    </form>
                </td>
            </tr>
        </table>
    </div>

</body>
</html>