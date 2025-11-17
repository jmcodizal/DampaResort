<?php
// ==============================
// SESSION & AUTHENTICATION
// ==============================
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header("Location: landing_page.php");
    exit;
}

// ==============================
// DATABASE CONNECTION
// ==============================
$conn = new mysqli("localhost", "root", "", "dampa_booking");
if ($conn->connect_error) die("Database connection failed: " . $conn->connect_error);

$admin_id = $_SESSION['admin_id'] ?? null;
if (!$admin_id) header("Location: landing_page.php");

// ==============================
// FETCH ADMIN INFO
// ==============================
$stmt = $conn->prepare("SELECT * FROM admin WHERE admin_id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$admin) die("Admin not found.");

// ==============================
// HANDLE PROFILE UPDATE
// ==============================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $fname   = trim($_POST['first_name'] ?? $admin['first_name']);
    $mname   = trim($_POST['middle_name'] ?? $admin['middle_name']);
    $lname   = trim($_POST['last_name'] ?? $admin['last_name']);
    $contact = trim($_POST['contact_number'] ?? $admin['contact_number']);
    $email   = trim($_POST['email'] ?? $admin['email']);
    $age     = isset($_POST['age']) ? (int)$_POST['age'] : (int)$admin['age'];
    $gender  = trim($_POST['gender'] ?? $admin['gender']);
    $dob     = trim($_POST['dob'] ?? $admin['dob']);

    // Profile photo upload
    $fileName = $admin['profile_photo_path'] ?? null;
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === 0) {
        $fileTmp = $_FILES['profile_photo']['tmp_name'];
        $ext = pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION);
        $allowed = ['jpg','jpeg','png','gif'];
        if (in_array(strtolower($ext), $allowed)) {
            $fileName = 'uploads/admins/' . uniqid('admin_') . '.' . $ext;
            if (!is_dir('uploads/admins')) mkdir('uploads/admins', 0755, true);
            move_uploaded_file($fileTmp, $fileName);
        }
    }

    $stmt = $conn->prepare(
        "UPDATE admin SET first_name=?, middle_name=?, last_name=?, contact_number=?, email=?, age=?, gender=?, dob=?, profile_photo_path=? WHERE admin_id=?"
    );
    $stmt->bind_param('sssisssssi', $fname, $mname, $lname, $contact, $email, $age, $gender, $dob, $fileName, $admin_id);
    $stmt->execute();
    $stmt->close();

    // Refresh admin data
    $stmt = $conn->prepare("SELECT * FROM admin WHERE admin_id=?");
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $admin = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// ==============================
// HANDLE PASSWORD CHANGE
// ==============================
$pwd_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password     = $_POST['new_password'] ?? '';

    $pwd_stmt = $conn->prepare("SELECT password FROM admin WHERE admin_id=?");
    $pwd_stmt->bind_param("i", $admin_id);
    $pwd_stmt->execute();
    $pwd_result = $pwd_stmt->get_result()->fetch_assoc();
    $pwd_stmt->close();

    if ($pwd_result && password_verify($current_password, $pwd_result['password'])) {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $update_pwd = $conn->prepare("UPDATE admin SET password=? WHERE admin_id=?");
        $update_pwd->bind_param("si", $hashed_password, $admin_id);
        $update_pwd->execute();
        $update_pwd->close();
        $pwd_message = "Password updated successfully!";
    } else $pwd_message = "Current password is incorrect.";
}

// ==============================
// DASHBOARD STATS
// ==============================
$total_customers = $conn->query("SELECT COUNT(*) AS total_customers FROM customer")->fetch_assoc()['total_customers'] ?? 0;
$total_bookings  = $conn->query("SELECT COUNT(*) AS total_bookings FROM bookings")->fetch_assoc()['total_bookings'] ?? 0;

// ==============================
// PAYMENTS & REPORTS
// ==============================
$start_date = $_GET['start_date'] ?? null;
$end_date   = $_GET['end_date'] ?? null;

$date_filter = "";
$params = [];
$types = "";
if ($start_date && $end_date) {
    $date_filter = " AND booking_date BETWEEN ? AND ? ";
    $params = [$start_date, $end_date];
    $types = "ss";
}

// Payments query
$payments_sql = "SELECT b.booking_id, b.total_amount, b.guest_name, b.check_in, b.check_out, b.status,
                        c.first_name, c.last_name
                 FROM bookings b
                 JOIN customer c ON b.customer_id = c.customer_id
                 WHERE b.status='Completed'
                 ORDER BY b.booking_date DESC";

$payments = $conn->query($payments_sql);

// ==============================
// CSV DOWNLOAD
// ==============================
if (isset($_GET['download_csv']) && $_GET['download_csv'] == 1) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="payments_report.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Booking ID','Customer','Guest','Amount','Status','Check-In','Check-Out']);
    while ($row = $payments->fetch_assoc()) {
        fputcsv($output, [
            $row['booking_id'],
            $row['first_name'].' '.$row['last_name'],
            $row['guest_name'],
            $row['total_amount'],
            $row['status'],
            $row['check_in'],
            $row['check_out']
        ]);
    }
    fclose($output);
    exit;
}

// ==============================
// REVENUE & ROOM STATS
// ==============================
$revenue_per_day = [];
$sql = "SELECT DATE(booking_date) AS day, SUM(total_amount) AS total
        FROM bookings
        WHERE status='Completed' $date_filter
        GROUP BY day
        ORDER BY day ASC";

$stmt = $conn->prepare($sql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) $revenue_per_day[$row['day']] = (float)$row['total'];
$stmt->close();

// Most booked rooms
$room_stats = [];
$bookings = $conn->query("SELECT rooms FROM bookings WHERE status='Completed'");
while ($row = $bookings->fetch_assoc()) {
    $rooms = json_decode($row['rooms'], true);
    if (is_array($rooms)) foreach ($rooms as $r) {
        $room_name = $r['name'] ?? null;
        if ($room_name) $room_stats[$room_name] = ($room_stats[$room_name] ?? 0) + 1;
    }
}
arsort($room_stats);
$room_stats = array_slice($room_stats, 0, 5, true);

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin Dashboard | Dampa Booking</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
:root {
  --brown-dark: #3d1202;
  --brown-medium: #6e3821;
  --brown-light: #e7d7c7;
}
body { background: var(--brown-light); font-family: 'Poppins', sans-serif; }
.sidebar { width: 220px; background: var(--brown-dark); min-height: 100vh; color: white; position: fixed; }
.sidebar .nav-link { color: white; margin: 5px 0; background: var(--brown-medium); border-radius: 5px; text-align: center; }
.sidebar .nav-link:hover { background: #2a0a01; }
.main { margin-left: 240px; padding: 20px; }
.table thead { background-color: var(--brown-medium); color: white; }
canvas { width: 100% !important; height: 250px !important; } /* compact charts */
</style>
</head>

<body>
<div class="d-flex">
  <!-- SIDEBAR -->
  <div class="sidebar p-3">
    <h4 class="text-center mb-4">DAMPA Admin</h4>
    <a href="#" class="nav-link">🏠 Dashboard</a>
    <a href="booking_management.php" class="nav-link">📘 Manage Bookings</a>
    <a href="manage_room.php" class="nav-link">🏨 Manage Rooms</a>
    <a data-bs-toggle="collapse" data-bs-target="#paymentsSection" class="nav-link">💰 Payments & Reports</a>
    <hr>
    <div class="text-center">
      <small>Welcome, <?= htmlspecialchars($admin['first_name']) ?></small><br>
      <a href="logout.php" class="btn btn-warning btn-sm mt-2">Logout</a>
    </div>
  </div>

  <!-- MAIN CONTENT -->
  <div class="main flex-grow-1">
    <h2>📊 Dashboard Overview</h2>
    <div class="row g-3 my-4">
      <div class="col-md-4">
        <div class="card text-center p-4 shadow"><h5>Total Customers</h5><h2><?= $total_customers ?></h2></div>
      </div>
      <div class="col-md-4">
        <div class="card text-center p-4 shadow"><h5>Total Bookings</h5><h2><?= $total_bookings ?></h2></div>
      </div>
      <div class="col-md-4 d-flex align-items-center justify-content-center">
        <button class="btn btn-info" data-bs-toggle="modal" data-bs-target="#adminModal">👤 View/Edit Admin</button>
      </div>
    </div>

    <!-- PAYMENTS & REPORTS -->
    <div class="collapse card p-4 shadow" id="paymentsSection">
      <h3>💰 Payments & Reports</h3>
      <form method="GET" class="mb-3 d-flex gap-2">
        <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($start_date) ?>">
        <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($end_date) ?>">
        <button class="btn btn-primary">Filter</button>
        <a href="?download_csv=1<?= $start_date && $end_date ? "&start_date=$start_date&end_date=$end_date" : "" ?>" class="btn btn-success">Download CSV</a>
      </form>

      <div class="table-responsive mb-4">
        <table class="table table-bordered text-center">
          <thead><tr><th>Booking ID</th><th>Customer</th><th>Guest</th><th>Amount</th><th>Status</th><th>Check-In</th><th>Check-Out</th></tr></thead>
          <tbody>
            <?php if($payments->num_rows>0): foreach($payments as $p): ?>
              <tr>
                <td><?= $p['booking_id'] ?></td>
                <td><?= htmlspecialchars($p['first_name'].' '.$p['last_name']) ?></td>
                <td><?= htmlspecialchars($p['guest_name']) ?></td>
                <td>₱ <?= number_format($p['total_amount'],2) ?></td>
                <td><?= $p['status'] ?></td>
                <td><?= $p['check_in'] ?></td>
                <td><?= $p['check_out'] ?></td>
              </tr>
            <?php endforeach; else: ?><tr><td colspan="7">No completed bookings found.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>

      <h4>Revenue Trend</h4>
      <canvas id="revenueChart"></canvas>
      <h4 class="mt-4">Most Booked Rooms</h4>
      <canvas id="roomsChart"></canvas>
    </div>
  </div>
</div>

<!-- ADMIN MODAL -->
<div class="modal fade" id="adminModal" tabindex="-1">
  <div class="modal-dialog modal-lg"><div class="modal-content p-3">
    <form method="POST" enctype="multipart/form-data">
      <div class="modal-header"><h5>Admin Details</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="text-center mb-3">
          <img src="<?= (!empty($admin['profile_photo_path']) && file_exists($admin['profile_photo_path'])) ? $admin['profile_photo_path'] : 'https://via.placeholder.com/150' ?>" class="rounded-circle" width="120">
          <input type="file" name="profile_photo" class="form-control mt-2">
        </div>
        <div class="row g-2">
          <div class="col-md-4"><label>First Name</label><input type="text" name="first_name" class="form-control" value="<?= htmlspecialchars($admin['first_name']) ?>"></div>
          <div class="col-md-4"><label>Middle Name</label><input type="text" name="middle_name" class="form-control" value="<?= htmlspecialchars($admin['middle_name']) ?>"></div>
          <div class="col-md-4"><label>Last Name</label><input type="text" name="last_name" class="form-control" value="<?= htmlspecialchars($admin['last_name']) ?>"></div>
          <div class="col-md-4"><label>Contact</label><input type="text" name="contact_number" class="form-control" value="<?= htmlspecialchars($admin['contact_number']) ?>"></div>
          <div class="col-md-4"><label>Email</label><input type="email" name="email" class="form-control" value="<?= htmlspecialchars($admin['email']) ?>"></div>
          <div class="col-md-2"><label>Age</label><input type="number" name="age" class="form-control" value="<?= htmlspecialchars($admin['age']) ?>"></div>
          <div class="col-md-2">
            <label>Gender</label>
            <select name="gender" class="form-control">
              <option value="Male" <?= $admin['gender']=='Male'?'selected':'' ?>>Male</option>
              <option value="Female" <?= $admin['gender']=='Female'?'selected':'' ?>>Female</option>
            </select>
          </div>
          <div class="col-md-4"><label>DOB</label><input type="date" name="dob" class="form-control" value="<?= htmlspecialchars($admin['dob']) ?>"></div>
        </div>

        <hr><h5>Change Password</h5>
        <?php if($pwd_message) echo "<div class='alert alert-info'>{$pwd_message}</div>"; ?>
        <div class="mb-2"><label>Current Password</label><input type="password" name="current_password" class="form-control"></div>
        <div class="mb-2"><label>New Password</label><input type="password" name="new_password" class="form-control"></div>
      </div>
      <div class="modal-footer">
        <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
        <button type="submit" name="change_password" class="btn btn-warning">Change Password</button>
      </div>
    </form>
  </div></div>
</div>

<script>
const revenueData = <?= json_encode($revenue_per_day) ?>;
new Chart(document.getElementById('revenueChart'), {
  type: 'line',
  data: { labels: Object.keys(revenueData), datasets: [{ label: 'Revenue (₱)', data: Object.values(revenueData), borderColor: '#6e3821', fill: true, tension: 0.3 }] },
  options: { responsive: true }
});

const roomStats = <?= json_encode($room_stats) ?>;
new Chart(document.getElementById('roomsChart'), {
  type: 'bar',
  data: { labels: Object.keys(roomStats), datasets: [{ label: 'Times Booked', data: Object.values(roomStats), backgroundColor: 'rgba(110,56,33,0.6)' }] },
  options: { responsive: true, plugins: { legend: { display: false } } }
});
</script>
</body>
</html>
