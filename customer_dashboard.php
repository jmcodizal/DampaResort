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

// --- Check if customer is logged in ---
if (
    !isset($_SESSION['logged_in']) ||
    $_SESSION['logged_in'] !== true ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'Customer'
) {
    header('Location: login.php');
    exit;
}

// ✅ Get customer_id from session
if (!isset($_SESSION['customer_id'])) {
    echo '<div class="alert alert-danger text-center mt-4">Session expired. Please log in again.</div>';
    exit;
}

$customer_id = $_SESSION['customer_id'];

// --- Fetch customer info ---
$stmt = $conn->prepare('SELECT * FROM customer WHERE customer_id = ?');
$stmt->bind_param('i', $customer_id);
$stmt->execute();
$user_result = $stmt->get_result();
$user = $user_result->fetch_assoc();
$stmt->close();

if (!$user) {
    echo '<div class="alert alert-danger text-center mt-4">User not found.</div>';
    exit;
}


// --- Count bookings ---
$booking_stmt = $conn->prepare('SELECT COUNT(*) AS booking_count FROM bookings WHERE customer_id = ?');
$booking_stmt->bind_param('i', $customer_id);
$booking_stmt->execute();
$booking_result = $booking_stmt->get_result()->fetch_assoc();
$booking_count = $booking_result ? $booking_result['booking_count'] : 0;
$booking_stmt->close();

// --- Handle profile update ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $fname   = trim($_POST['first_name']);
    $mname   = trim($_POST['middle_name']);
    $lname   = trim($_POST['last_name']);
    $contact = trim($_POST['contact_number']);
    $email   = trim($_POST['email']);
    $gender  = trim($_POST['gender']);

    // --- Handle profile picture upload ---
    $fileName = null;
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === 0) {
        $fileTmp = $_FILES['profile_pic']['tmp_name'];
        $ext = pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION);
        $allowed = ['jpg','jpeg','png','gif'];

        if (in_array(strtolower($ext), $allowed)) {
            $fileName = uniqid('profile_') . '.' . $ext;

            if (!is_dir('uploads/customers')) {
                mkdir('uploads/customers', 0755, true);
            }

            if (!move_uploaded_file($fileTmp, 'uploads/customers/' . $fileName)) {
                die("Failed to upload profile picture.");
            }
        }
    }

    // --- Update database ---
    if ($fileName) {
        $stmt = $conn->prepare("
            UPDATE customer
            SET first_name=?, middle_name=?, last_name=?, contact_number=?, email=?, gender=?, profile_pic=?
            WHERE customer_id=?
        ");
        $stmt->bind_param('sssssssi', $fname, $mname, $lname, $contact, $email, $gender, $fileName, $customer_id);
    } else {
        $stmt = $conn->prepare("
            UPDATE customer
            SET first_name=?, middle_name=?, last_name=?, contact_number=?, email=?, gender=?
            WHERE customer_id=?
        ");
        $stmt->bind_param('ssssssi', $fname, $mname, $lname, $contact, $email, $gender, $customer_id);
    }

    if ($stmt->execute()) {
        $stmt->close();
        // Reload user info so the new picture displays immediately
        $stmt = $conn->prepare('SELECT * FROM customer WHERE customer_id = ?');
        $stmt->bind_param('i', $customer_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
    } else {
        die("Failed to update profile: " . $stmt->error);
    }
}


// --- Change password ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];

    $pwd_stmt = $conn->prepare('SELECT password FROM customer WHERE customer_id = ?');
    $pwd_stmt->bind_param('i', $customer_id);
    $pwd_stmt->execute();
    $pwd_result = $pwd_stmt->get_result();
    $pwd_data = $pwd_result->fetch_assoc();
    $pwd_stmt->close();

    if ($pwd_data && password_verify($current_password, $pwd_data['password'])) {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $update_pwd = $conn->prepare('UPDATE customer SET password = ? WHERE customer_id = ?');
        $update_pwd->bind_param('si', $hashed_password, $customer_id);
        $update_pwd->execute();
        $update_pwd->close();

        header('Location: profile.php?pwdchanged=1');
        exit;
    } else {
        header('Location: profile.php?pwderror=1');
        exit;
    }
}

// --- Fetch bookings with ratings ---
$bookings_stmt = $conn->prepare('
    SELECT b.*, r.rating, r.review_text 
    FROM bookings b 
    LEFT JOIN ratings r ON b.booking_id = r.booking_id 
    WHERE b.customer_id = ? 
    ORDER BY b.booking_date DESC
');
$bookings_stmt->bind_param('i', $customer_id);
$bookings_stmt->execute();
$bookings_result = $bookings_stmt->get_result();
$bookings = $bookings_result->fetch_all(MYSQLI_ASSOC);
$bookings_stmt->close();

// --- Helper functions ---
function getRoomNames($rooms_json) {
    $rooms = json_decode($rooms_json, true);
    if (!$rooms) return 'Room details not available';
    $room_names = [];
    foreach ($rooms as $room) {
        $room_names[] = htmlspecialchars($room['name']);
    }
    return implode(', ', $room_names);
}

function formatSimpleDate($date) {
    return date('M j, Y', strtotime($date));
}

function formatDisplayDate($date) {
    return date('M j, Y g:i A', strtotime($date));
}

function canRateBooking($booking) {
    return isset($booking['status']) && $booking['status'] === 'Completed';
}

// --- Auto-clean URL after alerts ---
if (isset($_GET['updated']) || isset($_GET['pwdchanged']) || isset($_GET['pwderror']) || isset($_GET['rated'])) {
    echo '<script>
        setTimeout(() => {
            if (window.history.replaceState) {
                const cleanUrl = window.location.pathname;
                window.history.replaceState(null, null, cleanUrl);
            }
        }, 100);
    </script>';
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Profile</title>
    <link href="https://fonts.googleapis.com/css?family=Poppins:400,500,600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
    :root {
        --brown-dark: #3d1202;
        --brown-medium: #6e3821;
        --brown-light: #e7d7c7;
        --card-background: #f8f4ef;
        --icon-color: #e68524;
        --font: "Poppins", sans-serif;
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        background-color: var(--brown-light);
        font-family: var(--font);
        min-height: 100vh;
        display: flex;
        flex-direction: column;
    }
    .hidden {
    display: none !important;
}

    /* Header Styles */
    .header {
        background-color: var(--brown-dark);
        color: white;
        padding: 1rem 1.5rem;
        display: flex;
        align-items: center;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .header-title {
        font-size: 1.3rem;
        font-weight: 500;
        display: flex;
        align-items: center;
    }

    .header-title i {
        margin-right: 12px;
        cursor: pointer;
        font-size: 1.1rem;
    }

    /* Main Content Layout */
    .main-container {
        display: flex;
        flex: 1;
        padding: 1.5rem;
        gap: 1.5rem;
        max-width: 1400px;
        margin: 0 auto;
        width: 100%;
    }

    /* Profile Section */
    .profile-section {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        padding-top: 10px;
    }

    .profile-card {
        background-color: var(--card-background);
        border-radius: 16px;
        padding: 1.5rem;
        box-shadow: 0 3px 12px rgba(0, 0, 0, 0.08);
        text-align: center;
        width: 100%;
        max-width: 500px;
    }

    .profile-pic-container {
        text-align: center;
        margin-bottom: 0.8rem;
    }

    .profile-pic {
        width: 150px;
        height: 150px;
        border-radius: 50%;
        object-fit: cover;
        border: 4px solid white;
        box-shadow: 0 0 0 2px var(--brown-medium);
    }

    .name {
        color: var(--brown-dark);
        font-size: 1.5rem;
        font-weight: 600;
        margin-bottom: 0.8rem;
    }

    .contact-info {
        color: var(--brown-dark);
        font-size: 0.9rem;
        line-height: 1.5;
        padding-bottom: 1.2rem;
        border-bottom: 1px solid #e0d5cc;
        margin-bottom: 1.2rem;
    }

    .contact-info p {
        margin-bottom: 0.4rem;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .contact-info i {
        margin-right: 8px;
        color: var(--icon-color);
        width: 16px;
        font-size: 0.9rem;
    }

    .stats-container {
        display: flex;
        justify-content: space-around;
        margin: 1.2rem 0;
    }

    .stat-item {
        text-align: center;
    }

    .stat-value {
        font-size: 1.3rem;
        font-weight: 600;
        color: var(--brown-dark);
    }

    .stat-label {
        font-size: 0.8rem;
        color: var(--brown-medium);
    }

    /* Content Section */
    .content-section {
        flex: 2;
        display: flex;
        flex-direction: column;
    }

    .section-card {
        background-color: var(--card-background);
        border-radius: 16px;
        padding: 1.5rem;
        box-shadow: 0 3px 12px rgba(0, 0, 0, 0.08);
        height: 100%;
        display: flex;
        flex-direction: column;
    }

    .section-title {
        color: var(--brown-dark);
        font-size: 1.3rem;
        font-weight: 600;
        margin-bottom: 1.2rem;
        display: flex;
        align-items: center;
    }

    .section-title i {
        margin-right: 8px;
        color: var(--icon-color);
        font-size: 1.1rem;
    }

    /* Simple Booking Summary Styles */
.bookings-container {
    max-height: 60vh;
    overflow-y: auto;
    padding: 0.5rem 0;
}

.booking-item {
    background: white;
    border: 1px solid #e0d5cc;
    border-radius: 8px;
    margin-bottom: 0.75rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

.booking-item:hover {
    border-color: var(--brown-medium);
}

.booking-item.expanded {
    border-color: var(--icon-color);
}

.booking-summary {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
}

.booking-main {
    flex: 1;
}

.booking-id {
    font-weight: 600;
    color: var(--brown-dark);
    margin-bottom: 0.25rem;
}

.room-name {
    color: var(--brown-medium);
    margin-bottom: 0.25rem;
    font-size: 0.9rem;
}

.booking-dates {
    color: var(--brown-medium);
    font-size: 0.85rem;
}

.booking-side {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.booking-status {
    padding: 0.25rem 0.75rem;
    border-radius: 4px;
    font-size: 0.8rem;
    font-weight: 500;
}

.status-pending {
    background: #fff3cd;
    color: #856404;
}

.status-confirmed {
    background: #d1ecf1;
    color: #0c5460;
}

.status-cancelled {
    background: #f8d7da;
    color: #721c24;
}

.status-completed {
    background: #d4edda;
    color: #155724;
}

.booking-total {
    font-weight: 600;
    color: var(--brown-dark);
}

.booking-toggle {
    color: var(--brown-medium);
    transition: transform 0.3s ease;
}

.booking-item.expanded .booking-toggle {
    transform: rotate(180deg);
}

.booking-details {
    display: none;
    padding: 1rem;
    border-top: 1px solid #e0d5cc;
    background: var(--card-background);
}

.booking-item.expanded .booking-details {
    display: block;
}

.detail-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.detail {
    display: flex;
    flex-direction: column;
}

.detail label {
    font-size: 0.8rem;
    color: var(--brown-medium);
    margin-bottom: 0.25rem;
}

.detail span {
    color: var(--brown-dark);
    font-weight: 500;
}

.booking-actions {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.btn-cancel, .btn-rate {
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 4px;
    font-size: 0.85rem;
    cursor: pointer;
    transition: background-color 0.3s;
}

.btn-cancel {
    background: #f8d7da;
    color: #721c24;
}

.btn-cancel:hover {
    background: #f1b0b7;
}

.btn-rate {
    background: #fff3cd;
    color: #856404;
}

.btn-rate:hover {
    background: #ffeaa7;
}

.no-bookings {
    text-align: center;
    padding: 2rem;
    color: var(--brown-medium);
}

.no-bookings i {
    font-size: 2rem;
    margin-bottom: 1rem;
    color: var(--brown-medium);
}

.no-bookings h3 {
    margin-bottom: 0.5rem;
    color: var(--brown-dark);
}

/* Scrollbar Styling */
.bookings-container::-webkit-scrollbar {
    width: 6px;
}

.bookings-container::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 3px;
}

.bookings-container::-webkit-scrollbar-thumb {
    background: var(--brown-medium);
    border-radius: 3px;
}

.bookings-container::-webkit-scrollbar-thumb:hover {
    background: var(--brown-dark);
}

    /* Enhanced Action List */
    .action-list-wrapper {
        background-color: #fff;
        border-radius: 12px;
        padding: 0;
        box-shadow: 0 1px 6px rgba(0, 0, 0, 0.05);
        flex: 1;
        display: flex;
        flex-direction: column;
    }

    .action-item {
        display: flex;
        align-items: center;
        padding: 1.2rem 1.5rem;
        text-decoration: none;
        color: var(--brown-dark);
        font-size: 1rem;
        border-bottom: 1px solid #f0ebe6;
        transition: all 0.3s ease;
        flex: 1;
    }

    .action-item:last-child {
        border-bottom: none;
    }

    .action-item:hover {
        background-color: #f8f4ef;
        transform: translateX(5px);
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
    }

    .action-item i {
        color: var(--icon-color);
        margin-right: 16px;
        font-size: 1.1rem;
        width: 24px;
        text-align: center;
    }

    /* Rating Modal Styles */
.rating-header {
    text-align: center;
    margin-bottom: 1.5rem;
}

.rating-header h2 {
    color: var(--brown-dark);
    margin-bottom: 0.5rem;
}

.rating-header p {
    color: var(--brown-medium);
}

.stars-container {
    text-align: center;
    margin: 1.5rem 0;
}

.stars {
    display: inline-flex;
    gap: 0.5rem;
    margin-bottom: 1rem;
}

.star {
    font-size: 2rem;
    color: #ddd;
    cursor: pointer;
    transition: color 0.2s;
}

.star:hover,
.star.active {
    color: var(--icon-color);
}

.rating-text {
    color: var(--brown-medium);
    font-size: 0.9rem;
}
.form-group textarea {
            width: 100%;
            padding: 1rem;
            border: 1px solid #e0d5cc;
            border-radius: 8px;
            font-family: var(--font);
            font-size: 1rem;
            resize: vertical;
            min-height: 120px;
        }
        /* Contact Us Styles */

.contact-container {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
    max-height: 70vh;
    overflow-y: auto;
    padding: 0.5rem 0;
}

/* Quick Icons */
.quick-icons {
    background: var(--brown-light);
    padding: 1.5rem;
    border-radius: 8px;
    text-align: center;
}

.quick-icons h3 {
    color: var(--brown-dark);
    margin-bottom: 1rem;
    font-size: 1.1rem;
}

.icon-grid {
    display: flex;
    justify-content: center;
    gap: 1rem;
}

.icon-link {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: white;
    color: var(--brown-dark);
    text-decoration: none;
    transition: all 0.3s ease;
    border: 2px solid #e0d5cc;
    font-size: 1.3rem;
}

.icon-link:hover {
    transform: translateY(-3px) scale(1.1);
    box-shadow: 0 6px 15px rgba(0, 0, 0, 0.15);
}

.icon-link.facebook:hover {
    background: #1877f2;
    color: white;
    border-color: #1877f2;
}

.icon-link.messenger:hover {
    background: #0084ff;
    color: white;
    border-color: #0084ff;
}

.icon-link.phone:hover {
    background: #25d366;
    color: white;
    border-color: #25d366;
}

.icon-link.email:hover {
    background: #ea4335;
    color: white;
    border-color: #ea4335;
}

/* Contact Form */
/* Enhanced Contact Modal Layout */
.contact-layout {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
    max-height: 70vh;
    overflow-y: auto;
    padding: 0.5rem 0;
}

/* FAQ Section */
.faq-section {
    background: var(--card-background);
    padding: 1.5rem;
    border-radius: 12px;
    border: 1px solid #e0d5cc;
}

.faq-section h3 {
    color: var(--brown-dark);
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 1.2rem;
}

.faq-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    margin-bottom: 2rem;
}

.faq-item {
    border: 1px solid #e0d5cc;
    border-radius: 8px;
    overflow: hidden;
    transition: all 0.3s ease;
}

.faq-item:hover {
    border-color: var(--icon-color);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.faq-question {
    padding: 1rem 1.25rem;
    background: white;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-weight: 500;
    color: var(--brown-dark);
    transition: background-color 0.3s;
}

.faq-question:hover {
    background: var(--brown-light);
}

.faq-question i {
    color: var(--brown-medium);
    transition: transform 0.3s ease;
}

.faq-item.active .faq-question i {
    transform: rotate(180deg);
}

.faq-answer {
    padding: 0;
    max-height: 0;
    overflow: hidden;
    background: white;
    transition: all 0.3s ease;
}

.faq-item.active .faq-answer {
    padding: 1.25rem;
    max-height: 500px;
}

.faq-answer p {
    margin-bottom: 0.75rem;
    color: var(--brown-dark);
    line-height: 1.5;
}

.faq-answer ul {
    margin: 0.75rem 0;
    padding-left: 1.5rem;
}

.faq-answer li {
    margin-bottom: 0.5rem;
    color: var(--brown-medium);
    line-height: 1.4;
}

/* Quick Contact in FAQ */
.quick-contact {
    text-align: center;
    padding-top: 1.5rem;
    border-top: 1px solid #e0d5cc;
}

.quick-contact h4 {
    color: var(--brown-dark);
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    font-size: 1.1rem;
}

/* Contact Section */
.contact-section {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.contact-form {
    background: var(--card-background);
    padding: 1.5rem;
    border-radius: 12px;
    border: 1px solid #e0d5cc;
}

.contact-form h3 {
    color: var(--brown-dark);
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 1.2rem;
}

.contact-info {
    background: var(--card-background);
    padding: 1.5rem;
    border-radius: 12px;
    border: 1px solid #e0d5cc;
}

.contact-info h3 {
    color: var(--brown-dark);
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 1.2rem;
}
.contact-item {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    margin-bottom: 1rem;
    padding: 0.75rem;
    background: white;
    border-radius: 6px;
}

.contact-item i {
    color: var(--icon-color);
    font-size: 1.2rem;
    margin-top: 0.25rem;
    width: 20px;
}

.contact-item strong {
    color: var(--brown-dark);
    display: block;
    margin-bottom: 0.25rem;
    font-size: 0.9rem;
}

.contact-item p {
    color: var(--brown-medium);
    margin: 0;
    line-height: 1.4;
    font-size: 0.9rem;
}

/* Form improvements */
.form-group {
    margin-bottom: 1rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    color: var(--brown-dark);
    font-weight: 500;
    font-size: 0.9rem;
}

.form-group input,
.form-group textarea {
    width: 100%;
    padding: 0.8rem;
    border: 1px solid #e0d5cc;
    border-radius: 6px;
    font-size: 0.9rem;
    font-family: var(--font);
}

.form-group textarea {
    resize: vertical;
    min-height: 100px;
}



    /* Navigation Bar */
    .nav-bar {
        background-color: white;
        padding: 0.8rem 0;
        box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
        margin-top: auto;
    }

    .nav-container {
        display: flex;
        justify-content: space-around;
        max-width: 1200px;
        margin: 0 auto;
    }

    .nav-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        text-decoration: none;
        color: var(--brown-dark);
        font-size: 0.8rem;
        font-weight: 500;
        padding: 4px 8px;
        transition: color 0.2s;
    }

    .nav-item i {
        font-size: 1.3rem;
        margin-bottom: 4px;
        color: #a09a94;
        transition: color 0.2s;
    }

    .nav-item.active i,
    .nav-item.active {
        color: var(--brown-dark);
    }

    .nav-item:hover {
        color: var(--brown-medium);
    }

    .nav-item:hover i {
        color: var(--brown-medium);
    }

    /* Modal Styles */
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        z-index: 1000;
        justify-content: center;
        align-items: center;
    }

    .modal-content {
        background-color: white;
        padding: 2rem;
        border-radius: 16px;
        width: 90%;
        max-width: 500px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
    }

    .modal-title {
        font-size: 1.5rem;
        margin-bottom: 1.5rem;
        color: var(--brown-dark);
    }

    .form-group {
        margin-bottom: 1rem;
        position: relative;
    }

    .form-group label {
        display: block;
        margin-bottom: 0.5rem;
        color: var(--brown-dark);
        font-weight: 500;
    }

    .form-group input, .form-group select {
        width: 100%;
        padding: 0.8rem;
        border: 1px solid #e0d5cc;
        border-radius: 8px;
        font-size: 1rem;
    }

    .password-toggle {
        position: absolute;
        right: 10px;
        top: 38px;
        background: none;
        border: none;
        color: var(--brown-medium);
        cursor: pointer;
        font-size: 1rem;
    }

    .password-strength {
        margin-top: 0.5rem;
        font-size: 0.8rem;
    }

    .strength-weak { color: #e74c3c; }
    .strength-medium { color: #f39c12; }
    .strength-strong { color: #27ae60; }

    .btn {
        padding: 0.8rem 1.5rem;
        border: none;
        border-radius: 8px;
        font-size: 1rem;
        cursor: pointer;
        transition: background-color 0.3s;
    }

    .btn-primary {
        background-color: var(--brown-medium);
        color: white;
    }

    .btn-primary:hover {
        background-color: var(--brown-dark);
    }

    .btn-secondary {
        background-color: #e0d5cc;
        color: var(--brown-dark);
    }

    .btn-secondary:hover {
        background-color: #d4c8bc;
    }

    .modal-buttons {
        display: flex;
        gap: 1rem;
        margin-top: 1.5rem;
    }

    .alert-container {
    position: fixed;
    top: 80px;
    right: 20px;
    z-index: 1000;
    max-width: 400px;
}

.alert {
    display: flex;
    align-items: center;
    padding: 1rem 1.5rem;
    border-radius: 8px;
    margin-bottom: 0.75rem;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    animation: slideInRight 0.3s ease;
    position: relative;
    min-width: 300px;
}

.alert-success {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-error {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.alert i {
    margin-right: 0.75rem;
    font-size: 1.2rem;
}

.alert span {
    flex: 1;
    font-weight: 500;
}

.alert-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    padding: 0;
    margin-left: 1rem;
    color: inherit;
    opacity: 0.7;
    transition: opacity 0.3s;
}

.alert-close:hover {
    opacity: 1;
}

/* Alert Animations */
@keyframes slideInRight {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes slideOutRight {
    from {
        transform: translateX(0);
        opacity: 1;
    }
    to {
        transform: translateX(100%);
        opacity: 0;
    }
}

.alert.fade-out {
    animation: slideOutRight 0.3s ease forwards;
}

    /* Responsive Design - Mobile First Approach */
    @media (max-width: 768px) {
        .header {
            padding: 0.8rem 1rem;
        }
        
        .header-title {
            font-size: 1.1rem;
        }
        
        .header-title i {
            margin-right: 10px;
            font-size: 1rem;
        }
        
        .main-container {
            padding: 1rem;
            gap: 1rem;
            flex-direction: column;
        }
        
        .profile-section {
            padding-top: 0;
            margin-bottom: 1rem;
        }
        
        .profile-card {
            padding: 1.2rem;
            border-radius: 14px;
        }
        
        .profile-pic {
            width: 100px;
            height: 100px;
            border: 3px solid white;
        }
        
        .name {
            font-size: 1.3rem;
            margin-bottom: 0.6rem;
        }
        
        .contact-info {
            font-size: 0.85rem;
            padding-bottom: 1rem;
            margin-bottom: 1rem;
        }
        
        .contact-info i {
            margin-right: 6px;
            font-size: 0.8rem;
        }
        
        .stats-container {
            margin: 1rem 0;
        }
        
        .stat-value {
            font-size: 1.1rem;
        }
        
        .stat-label {
            font-size: 0.75rem;
        }
        
        .section-card {
            padding: 1.2rem;
            border-radius: 14px;
        }
        
        .section-title {
            font-size: 1.1rem;
            margin-bottom: 1rem;
        }
        
        .section-title i {
            font-size: 1rem;
        }
        
        .action-list-wrapper {
            border-radius: 10px;
        }
        
        .action-item {
            padding: 1rem 1.2rem;
            font-size: 0.9rem;
        }
        
        .action-item i {
            margin-right: 12px;
            font-size: 1rem;
            width: 20px;
        }
        
        .nav-bar {
            padding: 0.6rem 0;
        }
        
        .nav-item {
            font-size: 0.75rem;
            padding: 3px 6px;
        }
        
        .nav-item i {
            font-size: 1.1rem;
            margin-bottom: 3px;
        }
        .alert-container {
        top: 70px;
        right: 10px;
        left: 10px;
        max-width: none;
    }
    
    .alert {
        min-width: auto;
    }
    .contact-container {
        max-height: 60vh;
        gap: 1rem;
    }
    
    .quick-icons,
    .contact-form,
    .contact-info {
        padding: 1rem;
    }
    
    .icon-grid {
        gap: 0.75rem;
    }
    
    .icon-link {
        width: 45px;
        height: 45px;
        font-size: 1.2rem;
    }

    .contact-layout {
        grid-template-columns: 1fr;
        gap: 1rem;
        max-height: 60vh;
    }
    
    .modal-content {
        margin: 1rem;
    }
    
    .faq-section,
    .contact-form,
    .contact-info {
        padding: 1rem;
    }

    }

    @media (max-width: 480px) {
        .header {
            padding: 0.7rem 0.8rem;
        }
        
        .header-title {
            font-size: 1rem;
        }
        
        .main-container {
            padding: 0.8rem;
        }
        
        .profile-card, .section-card {
            padding: 1rem;
            border-radius: 12px;
        }
        
        .profile-pic {
            width: 90px;
            height: 90px;
        }
        
        .name {
            font-size: 1.2rem;
        }
        
        .contact-info {
            font-size: 0.8rem;
        }
        
        .action-item {
            padding: 0.8rem 1rem;
            font-size: 0.85rem;
        }
        
        .action-item i {
            margin-right: 10px;
            font-size: 0.9rem;
        }
        
        .nav-container {
            flex-wrap: wrap;
        }
        
        .nav-item {
            width: 25%;
            margin-bottom: 8px;
        }
    }

    @media (max-width: 360px) {
        .profile-pic {
            width: 80px;
            height: 80px;
        }
        
        .name {
            font-size: 1.1rem;
        }
        
        .contact-info {
            font-size: 0.75rem;
        }
        
        .action-item {
            padding: 0.7rem 0.8rem;
            font-size: 0.8rem;
        }
    }

    /* Desktop Optimizations */
    @media (min-width: 1200px) {
        .main-container {
            padding: 2rem;
            gap: 2rem;
        }
        
        .profile-card, .section-card {
            padding: 2rem;
        }
    }
    @media (max-width: 768px) {
    /* Modal adjustments */
    #contactUsModal .modal-content {
        width: 95%;
        margin: 1rem;
        max-height: 90vh;
    }
    
    /* Stack layout vertically on mobile */
    .contact-layout {
        grid-template-columns: 1fr;
        gap: 1.5rem;
        max-height: none;
        overflow-y: visible;
    }
    
    /* Reduce padding on mobile */
    .faq-section,
    .contact-form,
    .contact-info {
        padding: 1.25rem;
    }
    
    /* FAQ adjustments */
    .faq-question {
        padding: 0.875rem 1rem;
        font-size: 0.9rem;
    }
    
    .faq-item.active .faq-answer {
        padding: 1rem;
    }
    
    .faq-answer p,
    .faq-answer li {
        font-size: 0.85rem;
    }
    
    /* Contact items more compact */
    .contact-item {
        padding: 0.6rem;
        gap: 0.75rem;
    }
    
    .contact-item i {
        font-size: 1.1rem;
    }
    
    /* Form adjustments */
    .form-group input,
    .form-group textarea {
        padding: 0.7rem;
        font-size: 0.85rem;
    }
    
    .form-group textarea {
        min-height: 80px;
    }
    
    /* Quick contact icons smaller */
    .icon-link {
        width: 45px;
        height: 45px;
        font-size: 1.1rem;
    }
}

@media (max-width: 480px) {
    /* Even more compact for small phones */
    .faq-section,
    .contact-form,
    .contact-info {
        padding: 1rem;
    }
    
    .faq-section h3,
    .contact-form h3,
    .contact-info h3 {
        font-size: 1.1rem;
        margin-bottom: 1.25rem;
    }
    
    .faq-question {
        padding: 0.75rem;
        font-size: 0.85rem;
    }
    
    .faq-item.active .faq-answer {
        padding: 0.875rem;
    }
    
    .faq-answer ul {
        padding-left: 1.25rem;
    }
    
    /* Contact items stacked */
    .contact-item {
        flex-direction: column;
        gap: 0.5rem;
        text-align: center;
    }
    
    .contact-item i {
        margin-top: 0;
        align-self: center;
    }
    
    /* Smaller icons */
    .icon-link {
        width: 40px;
        height: 40px;
        font-size: 1rem;
    }
    
    .icon-grid {
        gap: 0.75rem;
    }
    
    /* Modal title smaller */
    #contactUsModal .modal-content h2 {
        padding: 1.25rem 1.5rem;
        font-size: 1.2rem;
    }
}

@media (max-width: 360px) {
    /* Extra small phone adjustments */
    .faq-section,
    .contact-form,
    .contact-info {
        padding: 0.875rem;
    }
    
    .faq-question {
        font-size: 0.8rem;
        padding: 0.6rem 0.75rem;
    }
    
    .faq-answer p,
    .faq-answer li {
        font-size: 0.8rem;
    }
    
    .contact-item strong,
    .contact-item p {
        font-size: 0.8rem;
    }
    
    .form-group label {
        font-size: 0.85rem;
    }
    
    .form-group input,
    .form-group textarea {
        padding: 0.6rem;
        font-size: 0.8rem;
    }
}

@media (max-width: 768px) {
    #contactUsModal .modal-content {
        overflow-y: auto;
    }
    
    .contact-layout {
        overflow-y: visible;
    }
}
</style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-title">
            <i class="fas fa-arrow-left" onclick="history.back()"></i> 
            <span>Customer Profile</span>
        </div>
    </header>


<div id="alert-container" class="alert-container">
    <?php if (isset($_GET['updated'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <span>Profile updated successfully!</span>
            <button class="alert-close" onclick="closeAlert(this)">&times;</button>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['pwdchanged'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <span>Password changed successfully!</span>
            <button class="alert-close" onclick="closeAlert(this)">&times;</button>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['pwderror'])): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <span>Current password is incorrect!</span>
            <button class="alert-close" onclick="closeAlert(this)">&times;</button>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['rated'])): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <span>Thank you for your rating!</span>
        <button class="alert-close" onclick="closeAlert(this)">&times;</button>
    </div>
<?php endif; ?>
</div>

    

    <!-- Main Content -->
    <div class="main-container">
        <!-- Profile Section -->
        <section class="profile-section">
            <div class="profile-card">
                <div class="profile-pic-container">
                    <img src="<?php echo !empty($user['profile_pic']) ? 'uploads/customers/' . htmlspecialchars($user['profile_pic']) : 'assets/images/profile-image.jpg'; ?>" 
                    alt="Profile Picture" 
                    class="profile-pic" 
                    onerror="this.src='https://images.unsplash.com/photo-1494790108755-2616b612b786?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=80'">
                
                <h2 class="name">
                    <?php echo htmlspecialchars($user['first_name'] . ' '
                        . ($user['middle_name'] ? $user['middle_name'] . ' ' : '')
                        . $user['last_name']); ?>
                </h2>
                
                <div class="contact-info">
                    <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?></p>
                    <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($user['contact_number']); ?></p>
                    <p><i class="fas fa-user"></i> <?php echo htmlspecialchars($user['gender']); ?></p>
                </div>
                
                <div class="stats-container">
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $booking_count; ?></div>
                        <div class="stat-label">Bookings</div>
                    </div>
                </div>
            </div>
        </section>
        
        <!-- Content Section -->
        <section class="content-section">
            <div class="section-card">
                <h2 class="section-title"><i class="fas fa-cog"></i> Account Settings</h2>
                
                <div class="action-list-wrapper">
                    <a href="#" class="action-item" onclick="openModal('updateProfileModal')">
                        <i class="fas fa-pen"></i> Update Profile
                    </a>
                    <a href="#" class="action-item" onclick="openModal('changePasswordModal')">
                        <i class="fas fa-lock"></i> Change Password
                    </a>
                    <a href="#" class="action-item" onclick="openModal('bookingSummaryModal')">
        <i class="fas fa-clipboard-list"></i> Booking Summary
    </a>
    <a href="#" class="action-item" onclick="openModal('contactUsModal')">
    <i class="fas fa-envelope"></i> Contact Us
</a>
                    <a href="#" class="action-item">
                        <i class="fas fa-sign-out-alt"></i> Log Out
                    </a>
                   
                </div>
            </div>
        </section>
    </div>

<!-- Update Profile Modal -->
<div id="updateProfileModal" class="modal">
    <div class="modal-content">
        <h2 class="modal-title">Update Profile</h2>
        <form method="POST" action="" enctype="multipart/form-data">
            <!-- Profile Picture -->
            <div class="form-group">
                <label for="profile_pic">Profile Picture</label>
                <input type="file" id="profile_pic" name="profile_pic" accept="image/*">
            </div>

            <!-- First Name -->
            <div class="form-group">
                <label for="first_name">First Name</label>
                <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
            </div>

            <!-- Middle Name -->
            <div class="form-group">
                <label for="middle_name">Middle Name</label>
                <input type="text" id="middle_name" name="middle_name" value="<?php echo htmlspecialchars($user['middle_name']); ?>">
            </div>

            <!-- Last Name -->
            <div class="form-group">
                <label for="last_name">Last Name</label>
                <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
            </div>

            <!-- Contact Number -->
            <div class="form-group">
                <label for="contact_number">Contact Number</label>
                <input type="tel" id="contact_number" name="contact_number" value="<?php echo htmlspecialchars($user['contact_number']); ?>" required>
            </div>

            <!-- Email -->
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
            </div>

            <!-- Gender -->
            <div class="form-group">
                <label for="gender">Gender</label>
                <select id="gender" name="gender" required>
                    <option value="Male" <?php echo $user['gender'] === 'Male' ? 'selected' : ''; ?>>Male</option>
                    <option value="Female" <?php echo $user['gender'] === 'Female' ? 'selected' : ''; ?>>Female</option>
                </select>
            </div>

            <!-- Modal Buttons -->
            <div class="modal-buttons">
                <button type="button" class="btn btn-secondary" onclick="closeModal('updateProfileModal')">Cancel</button>
                <button type="submit" class="btn btn-primary" name="update_profile">Update Profile</button>
            </div>
        </form>
    </div>
</div>


    <!-- Change Password Modal -->
    <div id="changePasswordModal" class="modal">
        <div class="modal-content">
            <h2 class="modal-title">Change Password</h2>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="current_password">Current Password</label>
                    <input type="password" id="current_password" name="current_password" required>
                    <button type="button" class="password-toggle" onclick="togglePassword('current_password')">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" required oninput="checkPasswordStrength(this.value)">
                    <button type="button" class="password-toggle" onclick="togglePassword('new_password')">
                        <i class="fas fa-eye"></i>
                    </button>
                    <div id="password-strength" class="password-strength"></div>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                    <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                <div class="modal-buttons">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('changePasswordModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary" name="change_password">Change Password</button>
                </div>
            </form>
        </div>
    </div>

   <!-- Booking Summary Modal -->
<div id="bookingSummaryModal" class="modal">
    <div class="modal-content" style="max-width: 700px;">
        <h2 class="modal-title"><i class="fas fa-clipboard-list"></i> My Bookings</h2>
        
        <div class="bookings-container">
            <?php if (empty($bookings)): ?>
                <div class="no-bookings">
                    <i class="fas fa-calendar-times"></i>
                    <h3>No Bookings Yet</h3>
                    <p>You haven't made any bookings yet.</p>
                </div>
            <?php else: ?>
                <?php foreach ($bookings as $booking): ?>
                    <div class="booking-item" onclick="toggleBookingDetails(this)">
                        <div class="booking-summary">
                            <div class="booking-main">
                                <div class="booking-id">Booking #<?php echo $booking['booking_id']; ?></div>
                                <div class="room-name"><?php echo getRoomNames($booking['rooms']); ?></div>
                                <div class="booking-dates">
                                    <?php echo formatSimpleDate($booking['check_in']); ?> - <?php echo formatSimpleDate($booking['check_out']); ?>
                                </div>
                            </div>
                            <div class="booking-side">
                                <div class="booking-status status-<?php echo strtolower($booking['status']); ?>">
                                    <?php echo $booking['status']; ?>
                                </div>
                                <div class="booking-total">₱<?php echo number_format($booking['total_amount'], 2); ?></div>
                                <i class="fas fa-chevron-down booking-toggle"></i>
                            </div>
                        </div>
                        
                        <div class="booking-details">
                            <div class="detail-grid">
                                <div class="detail">
                                    <label>Guest Name:</label>
                                    <span><?php echo htmlspecialchars($booking['guest_name']); ?></span>
                                </div>
                                <div class="detail">
                                    <label>Number of Guests:</label>
                                    <span><?php echo $booking['pax']; ?></span>
                                </div>
                                <div class="detail">
                                    <label>Check-in:</label>
                                    <span><?php echo formatDisplayDate($booking['check_in']); ?></span>
                                </div>
                                <div class="detail">
                                    <label>Check-out:</label>
                                    <span><?php echo formatDisplayDate($booking['check_out']); ?></span>
                                </div>
                                <div class="detail">
                                    <label>Booking Date:</label>
                                    <span><?php echo formatDisplayDate($booking['booking_date']); ?></span>
                                </div>
                            </div>
                            
                            <div class="booking-actions">
    <?php if ($booking['status'] == 'Pending' || $booking['status'] == 'Confirmed'): ?>
        <button class="btn-cancel" onclick="cancelBooking(<?php echo $booking['booking_id']; ?>, event)">
            <i class="fas fa-times"></i> Cancel Booking
        </button>
    <?php endif; ?>
    
    <?php if (canRateBooking($booking)): ?>
        <button class="btn-rate" onclick="rateBooking(<?php echo $booking['booking_id']; ?>, event)">
            <i class="fas fa-star"></i> 
            <?php echo isset($booking['rating']) ? 'Update Rating' : 'Rate Stay'; ?>
        </button>
    <?php endif; ?>
</div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div class="modal-buttons">
            <button type="button" class="btn btn-secondary" onclick="closeModal('bookingSummaryModal')">Close</button>
        </div>
    </div>
</div>
<!-- Rate Stay Modal -->
<div id="rateStayModal" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="rating-header">
            <h2 class="modal-title"><i class="fas fa-star"></i> Rate Your Stay</h2>
            <p>How was your experience with us?</p>
        </div>

        <div id="rateStayContent">
           
        </div>
    </div>
</div>

<!-- Contact Us Modal -->
<div id="contactUsModal" class="modal">
    <div class="modal-content" style="max-width: 900px;">
        <h2 class="modal-title"><i class="fas fa-envelope"></i> Contact & Inquiries</h2>
        
        <div class="contact-layout">
            <!-- Left Column: FAQ Section -->
            <div class="faq-section">
                <h3><i class="fas fa-question-circle"></i> Frequently Asked Questions</h3>
                
                <div class="faq-list">
                    <div class="faq-item" onclick="toggleFAQ(this)">
                        <div class="faq-question">
                            <span>What rooms are available and how many people can they accommodate?</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p><strong>We have 5 transient houses with different room types:</strong></p>
                            <ul>
                                <li>🏠 <strong>Rooms 1, 2 & 3:</strong> AC rooms that can accommodate 15 pax each</li>
                                <li>💑 <strong>Room 4:</strong> Couple room for 2-7 pax</li>
                                <li>👥 <strong>Room 5:</strong> Big room for 20 pax</li>
                            </ul>
                            <p>Each room includes free open cottage, CR, sink & faucet, and kitchen area.</p>
                        </div>
                    </div>
                    
                    <div class="faq-item" onclick="toggleFAQ(this)">
                        <div class="faq-question">
                            <span>How much does it cost for 15 people?</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p>We offer <strong>VERY AFFORDABLE</strong> and <strong>BUDGET-FRIENDLY</strong> rates!</p>
                            <p>For exact pricing and availability, please contact us directly as rates may vary by season and room type.</p>
                        </div>
                    </div>
                    
                    <div class="faq-item" onclick="toggleFAQ(this)">
                        <div class="faq-question">
                            <span>What amenities are included?</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p><strong>All rooms include:</strong></p>
                            <ul>
                                <li>✅ Free open cottage with long table & chairs</li>
                                <li>✅ Private CR with tiles</li>
                                <li>✅ Kitchen with sink & faucet (unlimited water)</li>
                                <li>✅ Free use of single burner gas stove & stainless grill</li>
                                <li>✅ Wall fans / AC rooms</li>
                                <li>✅ Generator available during brownouts</li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="faq-item" onclick="toggleFAQ(this)">
                        <div class="faq-question">
                            <span>What are the additional fees?</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p><strong>Additional fees:</strong></p>
                            <ul>
                                <li>🅿️ <strong>Parking:</strong> Motor ₱80, Car/Van ₱150, Jeep ₱200 (overnight)</li>
                                <li>🌿 <strong>Ecological Fee:</strong> ₱20 per head (adults 8+ years)</li>
                                <li>👥 <strong>Excess Persons:</strong> ₱200 per extra person</li>
                                <li>💰 <strong>Reservation:</strong> ₱1,000 down payment (non-refundable)</li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="faq-item" onclick="toggleFAQ(this)">
                        <div class="faq-question">
                            <span>What about the beach and location?</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p><strong>Perfect beachfront location!</strong></p>
                            <ul>
                                <li>🏖️ Beachfront with 2 kubos in front</li>
                                <li>😌 Very relaxing place with fresh air</li>
                                <li>🌊 Clean sea water with soft sand (not rocky)</li>
                                <li>👥 Not crowded - private beachfront lot</li>
                                <li>🛍️ Store available for groceries</li>
                                <li>⛺ Tent pitching allowed</li>
                                <li>🔥 Bonfire allowed (firewood available)</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Contact -->
                <div class="quick-contact">
                    <h4><i class="fas fa-bolt"></i> Quick Connect</h4>
                    <div class="icon-grid">
                        <a href="https://www.facebook.com/profile.php?id=100064917196506" target="_blank" class="icon-link facebook" title="Facebook Page">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="https://m.me/100064917196506" target="_blank" class="icon-link messenger" title="Messenger">
                            <i class="fab fa-facebook-messenger"></i>
                        </a>
                        <a href="tel:09459862423" class="icon-link phone" title="Call Us">
                            <i class="fas fa-phone"></i>
                        </a>
                        <a href="mailto:emmaruth.paris78@gmail.com" class="icon-link email" title="Send Email">
                            <i class="fas fa-envelope"></i>
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Right Column: Contact Form & Info -->
            <div class="contact-section">
                <!-- Contact Form -->
                <div class="contact-form">
                    <h3><i class="fas fa-paper-plane"></i> Send Inquiry</h3>
                    <form id="contactForm" action="https://api.web3forms.com/submit" method="POST">
                        <input type="hidden" name="access_key" value="66457f6d-2502-46aa-bd78-deec49424bf3">
                        <input type="hidden" name="subject" value="New Inquiry from Dampa Resort Website">
                        <input type="hidden" name="from_name" value="Dampa Resort Contact Form">
                        
                        <div class="form-group">
                            <label for="contact_name">Your Name</label>
                            <input type="text" id="contact_name" name="name" required 
                                   value="<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="contact_email">Your Email</label>
                            <input type="email" id="contact_email" name="email" required 
                                   value="<?php echo htmlspecialchars($user['email']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="contact_message">Your Inquiry</label>
                            <textarea id="contact_message" name="message" required 
                                      placeholder="Tell us about your planned visit, number of guests, preferred dates, or any questions..." rows="5"></textarea>
                        </div>
                        
                        <input type="checkbox" name="botcheck" class="hidden">
                        
                        <div class="modal-buttons">
                            <button type="button" class="btn btn-secondary" onclick="closeModal('contactUsModal')">Cancel</button>
                            <button type="submit" class="btn btn-primary">Send Inquiry</button>
                        </div>
                    </form>
                </div>
                
                <!-- Contact Information -->
                <div class="contact-info">
                    <h3><i class="fas fa-info-circle"></i> Resort Information</h3>
                    <div class="contact-item">
                        <i class="fas fa-map-marker-alt"></i>
                        <div>
                            <strong>Location</strong>
                            <p>Tanigue St. Brgy. Bucana, Nasugbu, Batangas<br>Near BSU Nasugbu</p>
                        </div>
                    </div>
                    <div class="contact-item">
                        <i class="fas fa-phone"></i>
                        <div>
                            <strong>Contact Number</strong>
                            <p>09459862423</p>
                        </div>
                    </div>
                    <div class="contact-item">
                        <i class="fas fa-envelope"></i>
                        <div>
                            <strong>Email</strong>
                            <p>emmaruth.paris78@gmail.com</p>
                        </div>
                    </div>
                    <div class="contact-item">
                        <i class="fas fa-user"></i>
                        <div>
                            <strong>Contact Person</strong>
                            <p>Emmaruth Palo Paris</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

    <!-- Navigation Bar -->
    <nav class="nav-bar">
        <div class="nav-container">
            <a href="#" class="nav-item">
                <i class="fas fa-home"></i> Home
            </a>
            <a href="explore_rooms.php" class="nav-item">
                <i class="fas fa-tree"></i> Explore
            </a>
            <a href="book_room.php" class="nav-item">
                <i class="fas fa-book-open"></i> Book Room
            </a>
            <a href="profile.php" class="nav-item active">
                <i class="fas fa-user"></i> Profile
            </a>
        </div>
    </nav>

    <script>

document.addEventListener('DOMContentLoaded', function() {

    // --- Auto-close alerts ---
    document.querySelectorAll('.alert').forEach(alert => {
        setTimeout(() => closeAlert(alert.querySelector('.alert-close') || alert), 2000);
    });

    // --- Close alert ---
    function closeAlert(element) {
        const alert = element.closest('.alert');
        if (alert) {
            alert.classList.add('fade-out');
            setTimeout(() => alert.remove(), 300);
        }
    }

    // --- Custom alert ---
    window.showCustomAlert = function(message, type='success') {
        const alertContainer = document.getElementById('alert-container');
        if (!alertContainer) return console.error('Alert container not found');

        const alert = document.createElement('div');
        alert.className = `alert alert-${type}`;
        alert.innerHTML = `
            <i class="fas fa-${type==='success'?'check':type==='error'?'exclamation':'info'}-circle"></i>
            <span>${message}</span>
            <button class="alert-close" onclick="closeAlert(this)">&times;</button>
        `;
        alertContainer.appendChild(alert);
        setTimeout(() => closeAlert(alert), 5000);
    }

    // --- Modal functions ---
    window.openModal = id => document.getElementById(id).style.display = 'flex';
    window.closeModal = id => document.getElementById(id).style.display = 'none';
    window.onclick = e => { if (e.target.classList.contains('modal')) closeModal(e.target.id); }

    // --- Toggle password visibility ---
    window.togglePassword = inputId => {
        const input = document.getElementById(inputId);
        const icon = input.nextElementSibling.querySelector('i');
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.replace('fa-eye-slash', 'fa-eye');
        }
    }

    // --- Password strength checker ---
    window.checkPasswordStrength = password => {
        const strengthText = document.getElementById('password-strength');
        let strength = 0;
        if (password.length>=8) strength++;
        if (password.match(/[a-z]/)) strength++;
        if (password.match(/[A-Z]/)) strength++;
        if (password.match(/[0-9]/)) strength++;
        if (password.match(/[^a-zA-Z0-9]/)) strength++;
        const labels = ['', 'Weak','Medium','Medium','Strong','Very Strong'];
        const classes = ['', 'strength-weak','strength-medium','strength-medium','strength-strong','strength-strong'];
        strengthText.textContent = labels[strength];
        strengthText.className = 'password-strength ' + classes[strength];
    }

    // --- Validate password confirmation ---
    document.querySelector('form[name="change_password"]')?.addEventListener('submit', function(e) {
        const newPass = document.getElementById('new_password').value;
        const confirmPass = document.getElementById('confirm_password').value;
        if (newPass !== confirmPass) {
            e.preventDefault();
            alert('New passwords do not match!');
        }
        if (newPass.length < 8) {
            e.preventDefault();
            alert('New password must be at least 8 characters!');
        }
    });

    // --- Booking functions ---
    window.toggleBookingDetails = el => el.classList.toggle('expanded');

    window.cancelBooking = (bookingId, event) => {
        event.stopPropagation();
        if (confirm('Are you sure you want to cancel this booking?')) {
            fetch('cancel_booking.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'booking_id='+bookingId
            })
            .then(r=>r.json())
            .then(d=>{
                if(d.success){ showCustomAlert(d.message,'success'); setTimeout(()=>location.reload(),500); }
                else showCustomAlert(d.message,'error');
            })
            .catch(()=>showCustomAlert('Error cancelling booking','error'));
        }
    }

    // --- Rating system ---
    let currentBookingId = null;
    window.rateBooking = (bookingId, event) => {
        event.stopPropagation();
        currentBookingId = bookingId;
        fetch(`load_rating_form.php?booking_id=${bookingId}`)
            .then(r=>r.text())
            .then(html=>{
                document.getElementById('rateStayContent').innerHTML = html;
                openModal('rateStayModal');
                initializeStarRating();
            })
            .catch(()=>showCustomAlert('Error loading rating form','error'));
    }

    function initializeStarRating() {
        const stars = document.querySelectorAll('#rateStayModal .star');
        const ratingValue = document.getElementById('rating-value');
        const ratingText = document.getElementById('rating-text');
        const texts = {1:'Poor',2:'Fair',3:'Good',4:'Very Good',5:'Excellent'};

        if(ratingValue && ratingValue.value>0){
            updateStars(parseInt(ratingValue.value));
            if(ratingText) ratingText.textContent = texts[ratingValue.value];
        }

        stars.forEach(star=>{
            star.addEventListener('click',()=>{ 
                const r = parseInt(star.dataset.rating); 
                ratingValue.value = r; 
                updateStars(r); 
                ratingText.textContent = texts[r];
            });
            star.addEventListener('mouseover',()=>highlightStars(parseInt(star.dataset.rating)));
        });

        document.getElementById('rateStayModal')?.addEventListener('mouseleave',()=>{
            updateStars(parseInt(ratingValue.value)||0);
        });

        function updateStars(r){
            stars.forEach(s=>s.classList.toggle('active', s.dataset.rating<=r));
        }
        function highlightStars(r){
            stars.forEach(s=>s.style.color=(s.dataset.rating<=r)?'var(--icon-color)':'#ddd');
        }
    }

    window.submitRating = function() {
        const form = document.getElementById('ratingForm');
        const fd = new FormData(form);
        if(parseInt(fd.get('rating'))===0){ showCustomAlert('Select a rating','error'); return; }

        fetch('submit_rating.php', {method:'POST', body:fd})
            .then(r=>r.json())
            .then(d=>{
                if(d.success){ showCustomAlert(d.message,'success'); closeModal('rateStayModal'); setTimeout(()=>location.reload(),1500); }
                else showCustomAlert(d.message,'error');
            })
            .catch(e=>showCustomAlert('Error submitting rating','error'));
    }

});
</script>

</body>
</html>