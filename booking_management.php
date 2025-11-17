<?php
session_start();

// --- Check if admin is logged in ---
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header("Location: login.php");
    exit;
}

// --- Database Connection ---
$servername = "localhost";
$username = "root";
$password = "";
$database = "dampa_booking";

$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// --- Optional: fetch admin info if needed ---
$admin_id = $_SESSION['admin_id'] ?? null;
$stmt = $conn->prepare("SELECT * FROM admin WHERE admin_id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$admin) {
    die("Admin not found.");
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_status'], $_POST['booking_id'])) {
        $booking_id = intval($_POST['booking_id']);
        $new_status = $_POST['update_status'];

        if (!empty($booking_id) && !empty($new_status)) {
            $update_stmt = $conn->prepare('UPDATE bookings SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE booking_id = ?');
            $update_stmt->bind_param('si', $new_status, $booking_id);

            if ($update_stmt->execute()) {
                header('Location: booking_management.php?updated=1');
                exit;
            } else {
                error_log('Failed to update booking status: ' . $conn->error);
            }

            $update_stmt->close();
        }
    }
}


$stmt = $conn->prepare('
    SELECT b.*, c.first_name, c.last_name, c.contact_number, c.email 
    FROM bookings b 
    LEFT JOIN customer c ON b.customer_id = c.customer_id 
    ORDER BY b.booking_date DESC
');
$stmt->execute();
$result = $stmt->get_result();

$bookings = [];
while ($row = $result->fetch_assoc()) {
    $bookings[] = $row;
}

$stmt->close();

// --- FUNCTIONS ---
function parseRoomDetails($rooms_json)
{
    $rooms = json_decode($rooms_json, true);
    if (!$rooms) return 'No room details';

    $room_list = [];
    foreach ($rooms as $room) {
        $name = htmlspecialchars($room['name']);
        $price = number_format($room['price'], 2);
        $qty = intval($room['qty']);
        $room_list[] = "{$name} (₱{$price} × {$qty})";
    }
    return implode(', ', $room_list);
}

function checkBookingConflicts($conn, $check_in, $check_out, $exclude_booking_id = null)
{
    $sql = '
        SELECT booking_id, guest_name, check_in, check_out 
        FROM bookings 
        WHERE status IN ("Pending", "Confirmed") 
        AND (
            (check_in BETWEEN ? AND ?) 
            OR (check_out BETWEEN ? AND ?) 
            OR (? BETWEEN check_in AND check_out) 
            OR (? BETWEEN check_in AND check_out)
        )
    ';

    if ($exclude_booking_id) {
        $sql .= ' AND booking_id != ?';
    }

    $stmt = $conn->prepare($sql);

    if ($exclude_booking_id) {
        $stmt->bind_param("ssssssi", $check_in, $check_out, $check_in, $check_out, $check_in, $check_out, $exclude_booking_id);
    } else {
        $stmt->bind_param("ssssss", $check_in, $check_out, $check_in, $check_out, $check_in, $check_out);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $conflicts = [];
    while ($row = $result->fetch_assoc()) {
        $conflicts[] = $row;
    }

    $stmt->close();
    return $conflicts;
}

// --- CLEAR URL PARAMETERS AFTER ALERTS ---
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
    <title>Admin - Booking Management</title>
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
        }

   
        .admin-header {
            background-color: var(--brown-dark);
            color: white;
            padding: 1.5rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .admin-header h1 {
            display: flex;
            align-items: center;
            gap: 1rem;
            font-size: 1.5rem;
        }

        .admin-nav a {
            color: white;
            text-decoration: none;
            margin-left: 1.5rem;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            transition: background-color 0.3s;
        }

        .admin-nav a:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        /* Main Content */
        .admin-container {
            padding: 2rem;
            max-width: 1400px;
            margin: 0 auto;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
    background: #F5E6D3;
    padding: 1.5rem;
    border-radius: 12px;
    text-align: center;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
    border: 1px solid #E8D5C4;
    position: relative;
    overflow: hidden;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 4px;
    background: linear-gradient(90deg, #E8C581, #D4A574);
}

.stat-number {
    font-size: 2rem;
    font-weight: 700;
    color: #5D4037;
    margin-bottom: 0.5rem;
}

.stat-label {
    color: #8D6E63;
    font-size: 0.9rem;
    font-weight: 500;
}

        /* Filters */
.filters {
    background: white;
    padding: 1.5rem;
    border-radius: 12px;
    margin-bottom: 2rem;
    display: flex;
    gap: 1.5rem;
    flex-wrap: wrap;
    align-items: flex-end; /* Align items to bottom */
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    flex: 1;
    min-width: 150px;
}

.filter-group label {
    font-weight: 500;
    color: var(--brown-dark);
    font-size: 0.9rem;
    white-space: nowrap;
}

.filter-group select,
.filter-group input {
    padding: 0.75rem; 
    border: 1px solid #e0d5cc;
    border-radius: 6px;
    font-family: var(--font);
    height: 42px; 
    box-sizing: border-box;
}


.filter-buttons {
    display: flex;
    gap: 0.75rem;
    align-items: flex-end;
    height: 42px; 
}

.btn {
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-family: var(--font);
    font-weight: 500;
    transition: all 0.3s;
    height: 42px; 
    display: flex;
    align-items: center;
    justify-content: center;
    white-space: nowrap;
}

.btn-primary {
    background: var(--icon-color);
    color: white;
}

.btn-primary:hover {
    background: var(--brown-medium);
    transform: translateY(-1px);
}

.btn:not(.btn-primary) {
    background: #6c757d;
    color: white;
}

.btn:not(.btn-primary):hover {
    background: #5a6268;
    transform: translateY(-1px);
}

      
        .bookings-table {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .table-header {
            background: var(--brown-dark);
            color: white;
            padding: 1rem 1.5rem;
            display: grid;
            grid-template-columns: 80px 1fr 1fr 1fr 100px 120px 120px 150px;
            gap: 1rem;
            font-weight: 500;
        }

        .booking-row {
            padding: 1rem 1.5rem;
            display: grid;
            grid-template-columns: 80px 1fr 1fr 1fr 100px 120px 120px 150px;
            gap: 1rem;
            border-bottom: 1px solid #e0d5cc;
            align-items: center;
        }

        .booking-row:hover {
            background: #E8C581;
        }

        .booking-row:last-child {
            border-bottom: none;
        }


.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-pending { 
    background: #fff3cd; 
    color: #856404; 
    border: 1px solid #ffeaa7;
}

.status-confirmed { 
    background: #d1ecf1; 
    color: #0c5460; 
    border: 1px solid #bee5eb;
}

.status-checked-in { 
    background: #d4edda; 
    color: #155724; 
    border: 1px solid #c3e6cb;
}

.status-completed { 
    background: #e2e3e5; 
    color: #383d41; 
    border: 1px solid #d6d8db;
}

.status-cancelled { 
    background: #f8d7da; 
    color: #721c24; 
    border: 1px solid #f5c6cb;
}

  
        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-success { background: #28a745; color: white; }
        .btn-warning { background: #ffc107; color: black; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-info { background: #17a2b8; color: white; }

        .btn-sm:hover {
            opacity: 0.8;
        }

        /* Conflict Alert */
        .conflict-alert {
            background: #f8d7da;
            color: #721c24;
            padding: 0.5rem;
            border-radius: 4px;
            font-size: 0.8rem;
            margin-top: 0.25rem;
            border-left: 3px solid #dc3545;
        }

        .modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1000;
    justify-content: center;
    align-items: center;
}

.modal-content {
    background: white;
    border-radius: 12px;
    width: 95%;
    max-width: 800px;
    max-height: 85vh;
    display: flex;
    flex-direction: column;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
}

.modal-content h2 {
    background: var(--brown-dark);
    color: white;
    padding: 1.5rem 2rem;
    margin: 0;
    font-size: 1.4rem;
    border-radius: 12px 12px 0 0;
}

.modal-body {
    flex: 1;
    overflow-y: auto;
    padding: 0;
}


.booking-details {
    padding: 2rem;
}

.detail-section {
    background: #f8f9fa;
    padding: 1.5rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
    border: 1px solid #e9ecef;
}

.detail-section h3 {
    color: var(--brown-dark);
    margin-bottom: 1rem;
    font-size: 1.1rem;
    border-bottom: 1px solid #dee2e6;
    padding-bottom: 0.5rem;
}

.detail-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.detail-item {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.detail-item label {
    font-weight: 600;
    color: var(--brown-medium);
    font-size: 0.85rem;
}

.detail-item span {
    color: var(--brown-dark);
    font-weight: 500;
}

.detail-item .amount {
    font-size: 1.1rem;
    color: var(--brown-dark);
    font-weight: 600;
}

/* Simple Rooms List */
.rooms-list ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.rooms-list li {
    background: white;
    padding: 1rem;
    margin-bottom: 0.5rem;
    border-radius: 6px;
}


.modal-footer {
    padding: 1rem 2rem;
    background: #f8f9fa;
    border-top: 1px solid #dee2e6;
    text-align: right;
    border-radius: 0 0 12px 12px;
}

.modal-footer .btn {
    padding: 0.6rem 1.5rem;
    background: var(--brown-medium);
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 500;
}

.modal-footer .btn:hover {
    background: var(--brown-dark);
}


.status-badge {
    padding: 0.4rem 0.8rem;
    border-radius: 4px;
    font-size: 0.8rem;
    font-weight: 500;
}

.status-pending { background: #fff3cd; color: #856404; }
.status-confirmed { background: #d1ecf1; color: #0c5460; }
.status-checked-in { background: #d4edda; color: #155724; }
.status-completed { background: #e2e3e5; color: #383d41; }
.status-cancelled { background: #f8d7da; color: #721c24; }


.loading-state, .error-message {
    text-align: center;
    padding: 3rem 2rem;
}

.loading-state i {
    font-size: 2rem;
    color: var(--icon-color);
    margin-bottom: 1rem;
}

.error-message i {
    font-size: 2rem;
    color: #dc3545;
    margin-bottom: 1rem;
}


.alert-container {
    position: fixed;
    top: 100px; 
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
    border-left: 4px solid #28a745;
}

.alert-error {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
    border-left: 4px solid #dc3545;
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
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
}

.alert-close:hover {
    opacity: 1;
    background: rgba(0, 0, 0, 0.1);
}


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

.fade-out {
    animation: slideOutRight 0.3s ease-in forwards;
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


@media (max-width: 480px) {
    .modal-content h2 {
        padding: 1.25rem 1.5rem;
        font-size: 1.3rem;
    }
    
    .details-container {
        padding: 1rem;
    }
    
    .detail-section {
        padding: 1rem;
    }
    .admin-header {
        padding: 0.75rem 1rem;
    }
    
    .admin-header h1 {
        font-size: 1rem;
        gap: 0.5rem;
    }
    
    .admin-header h1 i {
        font-size: 1.1rem;
    }
    
    .admin-nav a {
        margin-left: 0.5rem;
        padding: 0.35rem;
    }
    
    .admin-nav a i {
        font-size: 1rem;
    }

    .stats-grid {
        gap: 0.75rem;
    }
    
    .stat-card {
        padding: 1rem 0.75rem;
    }
    
    .stat-number {
        font-size: 1.3rem;
        margin-bottom: 0.25rem;
    }
    
    .stat-label {
        font-size: 0.8rem;
    }


    .filters {
        padding: 1rem;
        gap: 0.75rem;
    }
    
    .filter-group label {
        font-size: 0.8rem;
    }
    
    .filter-group select,
    .filter-group input {
        padding: 0.6rem;
        height: 40px;
        font-size: 0.9rem;
    }


    .table-header,
    .booking-row {
        grid-template-columns: 50px 110px 90px 90px 70px 80px 90px 110px;
        gap: 0.4rem;
        font-size: 0.75rem;
        padding: 0.6rem 0.8rem;
    }
    
 
    .btn-sm .btn-text {
        display: none;
    }
    
    .btn-sm i {
        margin-right: 0;
    }
    
    .action-buttons {
        flex-direction: column;
        gap: 0.2rem;
    }
    
    .btn-sm {
        width: 100%;
        justify-content: center;
        padding: 0.3rem;
    }

 
    .alert-container {
        top: 80px;
        right: 10px;
        left: 10px;
        max-width: none;
    }
    
    .alert {
        min-width: auto;
        padding: 1rem;
    }
    .action-buttons form {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }
    
    .btn-sm {
        font-size: 0.75rem;
        padding: 0.4rem 0.6rem;
    }
    .stats-grid {
        grid-template-columns: 1fr;
    }
}
@media (max-width: 360px) {
    .admin-header h1 {
        font-size: 0.9rem;
    }
    
    .admin-nav a {
        padding: 0.3rem;
        margin-left: 0.3rem;
    }
    
    .table-header,
    .booking-row {
        grid-template-columns: 40px 100px 80px 80px 60px 70px 80px 100px;
        font-size: 0.7rem;
        padding: 0.5rem 0.6rem;
    }
    
    .stat-number {
        font-size: 1.1rem;
    }
}


        @media (max-width: 1200px) {
            .table-header,
            .booking-row {
                grid-template-columns: 60px 1fr 1fr 1fr 80px 100px 100px 120px;
                gap: 0.5rem;
                font-size: 0.9rem;
            }
        }

        
        @media (max-width: 768px) {
            .modal-content {
        width: 95%;
        margin: 1rem;
        max-height: 90vh;
    }
    
    .modal-content h2 {
        padding: 1.25rem 1.5rem;
        font-size: 1.2rem;
    }
    
    .booking-details {
        padding: 1.25rem;
    }
    
    .detail-grid {
        grid-template-columns: 1fr;
        gap: 0.75rem;
    }
    
    .detail-section {
        padding: 1rem;
        margin-bottom: 1rem;
    }
      .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
     .admin-nav a span:not(.icon) {
        display: none;
    }
    
    .admin-nav a {
        padding: 0.5rem;
        margin-left: 0.5rem;
    }
    
    .detail-section h3 {
        font-size: 1rem;
        margin-bottom: 0.75rem;
    }
            .admin-container {
                padding: 1rem;
            }
            
            .filters {
                flex-direction: column;
                align-items: stretch;
            }
            
            .bookings-table {
                overflow-x: auto;
            }
            
            .table-header,
            .booking-row {
                grid-template-columns: repeat(8, 200px);
            }
            .modal-content {
        width: 98%;
        margin: 1rem;
    }
    
    .booking-details {
        padding: 1.5rem;
    }
    
    .detail-grid {
        grid-template-columns: 1fr;
    }
    
    .detail-section {
        padding: 1rem;
    }
    .admin-header {
        padding: 1rem 1.5rem;
        flex-wrap: wrap;
        gap: 1rem;
    }
    
    .admin-header h1 {
        font-size: 1.2rem;
        flex: 1;
    }
    
    .admin-nav a {
        margin-left: 1rem;
        padding: 0.4rem 0.8rem;
        font-size: 0.9rem;
    }
    
    .admin-nav a span.text {
        display: none; 
    }
    
    .admin-nav a i {
        margin-right: 0;
        font-size: 1.1rem;
    }


    .admin-container {
        padding: 1rem;
    }

  
    .stats-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
        margin-bottom: 1.5rem;
    }
    
    .stat-card {
        padding: 1.25rem 1rem;
    }
    
    .stat-number {
        font-size: 1.5rem;
    }
    
    .stat-label {
        font-size: 0.85rem;
    }

    .filters {
        flex-direction: column;
        gap: 1rem;
        padding: 1.25rem;
    }
    
    .filter-group {
        min-width: auto;
        width: 100%;
    }
    
    .filter-buttons {
        width: 100%;
        height: auto;
        justify-content: stretch;
        gap: 0.5rem;
    }
    
    .filter-buttons .btn {
        flex: 1;
        height: 44px;
    }


    .bookings-table {
        border-radius: 8px;
        overflow-x: auto;
    }
    
    .table-container {
        min-width: 800px; 
    }
    
    .table-header,
    .booking-row {
        grid-template-columns: 60px 120px 100px 100px 80px 90px 100px 120px;
        gap: 0.5rem;
        font-size: 0.8rem;
        padding: 0.75rem 1rem;
    }
    
    .action-buttons {
        flex-wrap: wrap;
        gap: 0.25rem;
    }
    
    .btn-sm {
        padding: 0.2rem 0.4rem;
        font-size: 0.7rem;
        min-width: auto;
    }
    

    .status-badge {
        padding: 0.2rem 0.5rem;
        font-size: 0.7rem;
    }
        }

      
.bookings-table {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch; 
}


.table-header {
    position: sticky;
    left: 0;
    background: var(--brown-dark);
    z-index: 2;
}


@media (max-width: 768px) {
    .table-header {
        font-size: 0.75rem;
        white-space: nowrap;
        min-width: max-content;
    }
    
  
    .table-header > div {
        padding: 0.5rem 0.25rem;
        text-align: center;
    }
    
   
    .table-header > div:nth-child(1) { min-width: 60px; } 
    .table-header > div:nth-child(2) { min-width: 120px; } 
    .table-header > div:nth-child(3) { min-width: 100px; } 
    .table-header > div:nth-child(4) { min-width: 100px; }
    .table-header > div:nth-child(5) { min-width: 80px; }  
    .table-header > div:nth-child(6) { min-width: 90px; } 
    .table-header > div:nth-child(7) { min-width: 100px; } 
    .table-header > div:nth-child(8) { min-width: 120px; } 
}


@media (max-width: 480px) {
    .table-header {
        font-size: 0.7rem;
    }
    
    .table-header > div {
        padding: 0.4rem 0.2rem;
    }
    
 
    .table-header > div:nth-child(1) { min-width: 50px; }  
    .table-header > div:nth-child(2) { min-width: 110px; }
    .table-header > div:nth-child(3) { min-width: 90px; } 
    .table-header > div:nth-child(4) { min-width: 90px; } 
    .table-header > div:nth-child(5) { min-width: 70px; }  
    .table-header > div:nth-child(6) { min-width: 80px; }  
    .table-header > div:nth-child(7) { min-width: 90px; }  
    .table-header > div:nth-child(8) { min-width: 110px; } 
}


@media (max-width: 360px) {
    .table-header {
        font-size: 0.65rem;
    }
    
    .table-header > div {
        padding: 0.3rem 0.15rem;
    }
    
    .table-header > div:nth-child(1) { min-width: 40px; }  
    .table-header > div:nth-child(2) { min-width: 100px; } 
    .table-header > div:nth-child(3) { min-width: 80px; }  
    .table-header > div:nth-child(4) { min-width: 80px; }  
    .table-header > div:nth-child(5) { min-width: 60px; }  
    .table-header > div:nth-child(6) { min-width: 70px; }  
    .table-header > div:nth-child(7) { min-width: 80px; } 
    .table-header > div:nth-child(8) { min-width: 100px; } 
}


.bookings-table {
    position: relative;
}

.bookings-table::after {
    content: "← Scroll →";
    position: absolute;
    top: 50%;
    right: 10px;
    transform: translateY(-50%);
    background: rgba(0, 0, 0, 0.7);
    color: white;
    padding: 0.5rem 0.75rem;
    border-radius: 20px;
    font-size: 0.7rem;
    opacity: 0;
    transition: opacity 0.3s;
    pointer-events: none;
    z-index: 1;
}

@media (max-width: 768px) {
    .bookings-table:hover::after {
        opacity: 1;
    }
}

.booking-row > div:nth-child(2) strong {
    font-size: 0.9rem;
    display: block;
}

.booking-row > div:nth-child(2) div {
    font-size: 0.8rem;
    color: #666;
}


@media (max-width: 768px) {
    .booking-row > div:nth-child(2) strong {
        font-size: 0.85rem;
    }
    
    .booking-row > div:nth-child(2) div {
        font-size: 0.75rem;
    }
}

@media (max-width: 480px) {
    .booking-row > div:nth-child(2) strong {
        font-size: 0.9rem;
    }
    
    .booking-row > div:nth-child(2) div {
        font-size: 0.5rem;
    }
}

@media (max-width: 360px) {
    .booking-row > div:nth-child(2) strong {
        font-size: 0.4rem;
    }
    
    .booking-row > div:nth-child(2) div {
        font-size: 0.8rem;
    }
}
    </style>
</head>
<body>
    <!-- Header -->
    <header class="admin-header">
        <h1>
            <i class="fas fa-tachometer-alt"></i>
            Booking Management - Admin Panel
        </h1>
        <nav class="admin-nav">
            <!-- <a href="profile.php"><i class="fas fa-user"></i> User View</a> -->
            <!-- <a href="#"><i class="fas fa-cog"></i> Settings</a> -->
            <a href="admin_dashboard.php"><i class="fa fa-arrow-right"></i> </a>
        </nav>
    </header>

  <!-- Alert Container -->
<div id="alert-container" class="alert-container"></div>

<?php if (isset($_GET['updated']) && $_GET['updated'] == 1): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            showCustomAlert('Booking status updated successfully!', 'success');
        });
    </script>
<?php endif; ?>

    <!-- Main Content -->
    <div class="admin-container">
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo count($bookings); ?></div>
                <div class="stat-label">Total Bookings</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count(array_filter($bookings, fn($b) => $b['status'] === 'Pending')); ?></div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count(array_filter($bookings, fn($b) => $b['status'] === 'Confirmed')); ?></div>
                <div class="stat-label">Confirmed</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count(array_filter($bookings, fn($b) => $b['status'] === 'Completed')); ?></div>
                <div class="stat-label">Completed</div>
            </div>
        </div>

        <!-- Filters -->
<div class="filters">
    <div class="filter-group">
        <label>Status</label>
        <select id="statusFilter">
            <option value="">All Status</option>
            <option value="Pending">Pending</option>
            <option value="Confirmed">Confirmed</option>
            <option value="Checked-in">Checked-in</option>
            <option value="Completed">Completed</option>
            <option value="Cancelled">Cancelled</option>
        </select>
    </div>
    <div class="filter-group">
        <label>From Date</label>
        <input type="date" id="fromDate">
    </div>
    <div class="filter-group">
        <label>To Date</label>
        <input type="date" id="toDate">
    </div>
    <div class="filter-buttons">
        <button class="btn btn-primary" onclick="applyFilters()">Apply Filters</button>
        <button class="btn" onclick="clearFilters()">Clear</button>
    </div>
</div>
        <!-- Bookings Table -->
        <div class="bookings-table">
            <div class="table-header">
                <div>ID</div>
                <div>Guest</div>
                <div>Dates</div>
                <div>Room</div>
                <div>Guests</div>
                <div>Amount</div>
                <div>Status</div>
                <div>Actions</div>
            </div>

            <?php
            foreach ($bookings as $booking):
                $conflicts = checkBookingConflicts($conn, $booking['check_in'], $booking['check_out'], $booking['booking_id']);
                ?>
            <div class="booking-row" data-status="<?php echo $booking['status']; ?>" 
     data-date="<?php echo date('Y-m-d', strtotime($booking['booking_date'])); ?>">
    <div>#<?php echo $booking['booking_id']; ?></div>
    <div class="guest-info">
        <strong><?php echo htmlspecialchars($booking['guest_name']); ?></strong>
        <div class="contact-email">
            <?php echo htmlspecialchars($booking['email']); ?>
        </div>
        <div class="contact-phone">
            <?php echo htmlspecialchars($booking['contact_number']); ?>
        </div>
    </div>
    <div>
                    <div><strong>Check-in:</strong> <?php echo date('M j, Y g:i A', strtotime($booking['check_in'])); ?></div>
                    <div><strong>Check-out:</strong> <?php echo date('M j, Y g:i A', strtotime($booking['check_out'])); ?></div>
                    <?php if (!empty($conflicts)): ?>
                        <div class="conflict-alert">
                            ⚠️ Conflict detected
                        </div>
                    <?php endif; ?>
                </div>
                <div><?php echo parseRoomDetails($booking['rooms']); ?></div>
                <div><?php echo $booking['pax']; ?> pax</div>
                <div>₱<?php echo number_format($booking['total_amount'], 2); ?></div>
                <div>
                    <span class="status-badge status-<?php echo strtolower($booking['status']); ?>">
                        <?php echo $booking['status']; ?>
                    </span>
                </div>
                <div class="action-buttons">
    <form method="POST" style="display: inline;">
        <input type="hidden" name="booking_id" value="<?php echo $booking['booking_id']; ?>">
        
        <?php if ($booking['status'] === 'Pending'): ?>
            <!-- Pending: Can Confirm or Cancel -->
            <button type="submit" name="update_status" value="Confirmed" class="btn-sm btn-success" title="Confirm Booking">
                <i class="fas fa-check"></i> Confirm
            </button>
            <button type="submit" name="update_status" value="Cancelled" class="btn-sm btn-danger" title="Cancel Booking">
                <i class="fas fa-times"></i> Cancel
            </button>
            
        <?php elseif ($booking['status'] === 'Confirmed'): ?>
            <!-- Confirmed: Can Check-in or Cancel -->
            <button type="submit" name="update_status" value="Checked-in" class="btn-sm btn-info" title="Check-in Guest">
                <i class="fas fa-sign-in-alt"></i> Check-in
            </button>
            <button type="submit" name="update_status" value="Cancelled" class="btn-sm btn-danger" title="Cancel Booking">
                <i class="fas fa-times"></i> Cancel
            </button>
            
        <?php elseif ($booking['status'] === 'Checked-in'): ?>
            <!-- Checked-in: Can Check-out (Complete) -->
            <button type="submit" name="update_status" value="Completed" class="btn-sm btn-success" title="Check-out Guest">
                <i class="fas fa-sign-out-alt"></i> Check-out
            </button>
            
        <?php elseif (in_array($booking['status'], ['Completed', 'Cancelled'])): ?>
            <!-- Completed/Cancelled: View only -->
            <span style="color: #666; font-size: 0.8rem;">Finalized</span>
            
        <?php endif; ?>
    </form>
    
    <button class="btn-sm btn-warning" onclick="viewBookingDetails(<?php echo $booking['booking_id']; ?>)" title="View Details">
        <i class="fas fa-eye"></i>
    </button>
</div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>


<div id="bookingDetailsModal" class="modal">
    <div class="modal-content">
        <h2>Booking Details</h2>
        <div class="modal-body">
            <div id="bookingDetailsContent">
              
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn" onclick="closeModal('bookingDetailsModal')">Close</button>
        </div>
    </div>
</div>

    <script>
     
function closeAlert(element) {
    const alert = element.closest('.alert');
    if (alert) {
        alert.classList.add('fade-out');
        setTimeout(() => {
            alert.remove();
        }, 300);
    }
}


function showCustomAlert(message, type = 'success') {
    const alertContainer = document.getElementById('alert-container');
    if (!alertContainer) {
        console.error('Alert container not found');
        return;
    }
    
    const alert = document.createElement('div');
    alert.className = `alert alert-${type}`;
    alert.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check' : type === 'error' ? 'exclamation' : 'info'}-circle"></i>
        <span>${message}</span>
        <button class="alert-close" onclick="closeAlert(this)">&times;</button>
    `;
    
    alertContainer.appendChild(alert);

    setTimeout(() => {
        closeAlert(alert);
    }, 5000);
}


document.addEventListener('DOMContentLoaded', function() {
    
    if (window.location.search.includes('updated=1')) {
        setTimeout(() => {
            if (window.history.replaceState) {
                const cleanUrl = window.location.pathname;
                window.history.replaceState(null, null, cleanUrl);
            }
        }, 100);
    }

    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            closeAlert(alert.querySelector('.alert-close') || alert);
        }, 5000);
    });
});

   
        function applyFilters() {
            const statusFilter = document.getElementById('statusFilter').value;
            const fromDate = document.getElementById('fromDate').value;
            const toDate = document.getElementById('toDate').value;
            
            const rows = document.querySelectorAll('.booking-row');
            
            rows.forEach(row => {
                let show = true;
                
        
                if (statusFilter && row.dataset.status !== statusFilter) {
                    show = false;
                }
          
                if (fromDate && row.dataset.date < fromDate) {
                    show = false;
                }
                if (toDate && row.dataset.date > toDate) {
                    show = false;
                }
                
                row.style.display = show ? 'grid' : 'none';
            });
        }
        
        function clearFilters() {
            document.getElementById('statusFilter').value = '';
            document.getElementById('fromDate').value = '';
            document.getElementById('toDate').value = '';
            
            const rows = document.querySelectorAll('.booking-row');
            rows.forEach(row => row.style.display = 'grid');
        }
        
   
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'flex';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

function viewBookingDetails(bookingId) {
    // Show loading state
    const content = `
        <div class="loading-state">
            <i class="fas fa-spinner fa-spin fa-2x"></i>
            <p>Loading booking details...</p>
        </div>
    `;
    document.getElementById('bookingDetailsContent').innerHTML = content;
    openModal('bookingDetailsModal');
    
 
    fetch(`get_booking_details.php?booking_id=${bookingId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
              
document.getElementById('bookingDetailsContent').innerHTML = `
    <div class="booking-details">
        <div class="detail-section">
            <h3>Guest Information</h3>
            <div class="detail-grid">
                <div class="detail-item">
                    <label>Guest Name:</label>
                    <span>${data.booking.guest_name}</span>
                </div>
                <div class="detail-item">
                    <label>Email:</label>
                    <span>${data.booking.email}</span>
                </div>
                <div class="detail-item">
                    <label>Contact Number:</label>
                    <span>${data.booking.contact_number}</span>
                </div>
                <div class="detail-item">
                    <label>Number of Guests:</label>
                    <span>${data.booking.pax} pax</span>
                </div>
            </div>
        </div>
        
        <div class="detail-section">
            <h3>Booking Dates</h3>
            <div class="detail-grid">
                <div class="detail-item">
                    <label>Check-in:</label>
                    <span>${data.formatted_check_in}</span>
                </div>
                <div class="detail-item">
                    <label>Check-out:</label>
                    <span>${data.formatted_check_out}</span>
                </div>
                <div class="detail-item">
                    <label>Duration:</label>
                    <span>${data.duration} night(s)</span>
                </div>
            </div>
        </div>
        
        <div class="detail-section">
            <h3>Room Details</h3>
            <div class="rooms-list">
                <ul>
                    ${data.room_details.split(', ').map(room => `
                        <li>${room}</li>
                    `).join('')}
                </ul>
            </div>
        </div>
        
        <div class="detail-section">
            <h3>Payment Information</h3>
            <div class="detail-grid">
                <div class="detail-item">
                    <label>Total Amount:</label>
                    <span class="amount">₱${parseFloat(data.booking.total_amount).toLocaleString()}</span>
                </div>
                <div class="detail-item">
                    <label>Status:</label>
                    <span class="status-badge status-${data.booking.status.toLowerCase()}">
                        ${data.booking.status}
                    </span>
                </div>
                <div class="detail-item">
                    <label>Booking ID:</label>
                    <span>#${data.booking.booking_id}</span>
                </div>
            </div>
        </div>
    </div>
`;
            } else {
                document.getElementById('bookingDetailsContent').innerHTML = `
                    <div class="error-message">
                        <i class="fas fa-exclamation-triangle"></i>
                        <p>Error loading booking details: ${data.message}</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('bookingDetailsContent').innerHTML = `
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i>
                    <p>Error loading booking details. Please try again.</p>
                </div>
            `;
        });
}
      
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>