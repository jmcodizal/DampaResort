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

$booking_id  = intval($_GET['booking_id'] ?? 0);
$amount      = 1000; // always reservation fee
$customer_id = $_SESSION['customer_id'] ?? 0;

// Validate request
if (!$booking_id || !$customer_id) {
    die("Invalid request");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Pay Reservation Fee</title>
<style>
  body {
    font-family: Arial, sans-serif;
    background-color: #e6f0ff; /* light blue bg */
    color: #004080; /* dark blue text */
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 50px;
  }
  h2 { color: #003366; }
  .qr-box {
    margin: 20px 0;
    padding: 20px;
    background-color: #cce0ff;
    border-radius: 12px;
    text-align: center;
  }
  img.qr {
    max-width: 250px;
    border: 2px solid #004080;
    border-radius: 8px;
  }
  button {
    background-color: #004080;
    color: #fff;
    padding: 12px 20px;
    border: none;
    border-radius: 8px;
    font-size: 16px;
    cursor: pointer;
  }
  button:hover { background-color: #003366; }
</style>
</head>
<body>

<h2>Pay Reservation Fee: ₱<?= number_format($amount, 2) ?></h2>

<div class="qr-box">
  <p>Scan the QR below using GCash:</p>
  <img src="uploads/gcash.jpg" alt="GCash QR" class="qr">
</div>

<p>After payment, click confirm:</p>
<form method="POST" action="confirm_payment.php">
  <input type="hidden" name="booking_id" value="<?= $booking_id ?>">
  <input type="hidden" name="amount" value="<?= $amount ?>">
  <button type="submit">I Have Paid</button>
</form>

</body>
</html>
