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
if(!isset($_SESSION['customer_id'])) die("Login required");

$booking_id = intval($_POST['booking_id']);
$amount = floatval($_POST['amount']);
$customer_id = $_SESSION['customer_id'];

// Insert payment
$stmt = $conn->prepare("INSERT INTO payments (booking_id, customer_id, amount, method, status) VALUES (?,?,?,?, 'Completed')");
$method = 'GCash';
$stmt->bind_param("iids", $booking_id, $customer_id, $amount, $method);
$stmt->execute();

// Update booking status
$update = $conn->prepare("UPDATE bookings SET status='Confirmed' WHERE booking_id=?");
$update->bind_param("i",$booking_id);
$update->execute();

echo "✅ Payment confirmed! Booking is now confirmed.";
