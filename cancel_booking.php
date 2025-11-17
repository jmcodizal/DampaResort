<?php
session_start();
// --- Database Connection ---
$servername = "localhost";
$username = "root";
$password = "";
$database = "dampa_booking";

$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['booking_id'])) {
    $booking_id = intval($_POST['booking_id']);
    $customer_id = 1; 

    $stmt = $conn->prepare('UPDATE bookings SET status = "Cancelled" WHERE booking_id = ? AND customer_id = ?');
    $stmt->bind_param('ii', $booking_id, $customer_id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Booking cancelled successfully']);
    } else {
        error_log("Database error: " . $stmt->error);
        echo json_encode(['success' => false, 'message' => 'Failed to cancel booking']);
    }

    $stmt->close();
    $conn->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>
