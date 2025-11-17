<?php
session_start();
header('Content-Type: application/json');

// --- Database Connection ---
$servername = "localhost";
$username = "root";
$password = "";
$database = "dampa_booking";

$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// --- Ensure customer is logged in ---
if (
    !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true ||
    !isset($_SESSION['role']) || $_SESSION['role'] !== 'Customer' ||
    !isset($_SESSION['customer_id'])
) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in']);
    exit;
}

$customer_id = $_SESSION['customer_id'];

// --- Ensure POST request ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// --- Validate input ---
$booking_id  = intval($_POST['booking_id'] ?? 0);
$rating      = intval($_POST['rating'] ?? 0);
$review_text = trim($_POST['review_text'] ?? '');

if ($booking_id <= 0 || $rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'message' => 'Invalid rating data']);
    exit;
}

// --- Check if booking exists and belongs to customer ---
$stmt = $conn->prepare("SELECT status FROM bookings WHERE booking_id = ? AND customer_id = ?");
$stmt->bind_param("ii", $booking_id, $customer_id);
$stmt->execute();
$res = $stmt->get_result();
$booking = $res->fetch_assoc();
$stmt->close();

if (!$booking || $booking['status'] !== 'Completed') {
    echo json_encode(['success' => false, 'message' => 'Cannot rate this booking']);
    exit;
}

// --- Check if rating already exists ---
$stmt = $conn->prepare("SELECT rating_id FROM ratings WHERE booking_id = ?");
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$res = $stmt->get_result();
$existing = $res->fetch_assoc();
$stmt->close();

if ($existing) {
    // --- Update rating ---
    $stmt = $conn->prepare("UPDATE ratings SET rating = ?, review_text = ?, updated_at = CURRENT_TIMESTAMP WHERE booking_id = ?");
    $stmt->bind_param("isi", $rating, $review_text, $booking_id);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => true, 'message' => 'Rating updated successfully']);
} else {
    // --- Insert new rating ---
    $stmt = $conn->prepare("INSERT INTO ratings (booking_id, customer_id, rating, review_text) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiis", $booking_id, $customer_id, $rating, $review_text);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => true, 'message' => 'Thank you for your rating!']);
}

$conn->close();
?>
