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


// --- FETCH CUSTOMER FULL NAME ---
$customerFullName = '';

if (isset($_SESSION['customer_id'])) {
    $customer_id = $_SESSION['customer_id'];

    $stmt = $conn->prepare("SELECT first_name, middle_name, last_name FROM customer WHERE customer_id = ?");
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $stmt->bind_result($first_name, $middle_name, $last_name);

    if ($stmt->fetch()) {
        // Include middle name only if it exists
        $customerFullName = trim("$first_name " . ($middle_name ? "$middle_name " : "") . "$last_name");
    }

    $stmt->close();
}

// --- HANDLE BOOKING SAVE REQUEST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER["CONTENT_TYPE"]) && str_contains($_SERVER["CONTENT_TYPE"], "application/json")) {

    if (!isset($_SESSION['customer_id'])) {
        http_response_code(403);
        die(json_encode(["error" => "Not logged in."]));
    }

    $customer_id = $_SESSION['customer_id'];
    $data = json_decode(file_get_contents("php://input"), true);

    if (!$data) {
        http_response_code(400);
        die(json_encode(["error" => "Invalid input data."]));
    }

    $guest_name   = $data['guestName'] ?? '';
    $check_in     = $data['checkIn'] ?? '';
    $check_out    = $data['checkOut'] ?? '';
    $pax          = intval($data['pax'] ?? 1);
    $rooms_json   = json_encode($data['rooms'] ?? []);
    $total_amount = floatval($data['total'] ?? 0);
    $booking_date = $data['bookingDate'] ?? date('Y-m-d H:i:s');

    if (empty($guest_name) || empty($check_in) || empty($check_out)) {
        http_response_code(400);
        die(json_encode(["error" => "Missing required fields."]));
    }

    $stmt = $conn->prepare("
        INSERT INTO bookings (customer_id, guest_name, check_in, check_out, pax, rooms, total_amount, booking_date, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending')
    ");
    $stmt->bind_param("isssisds", 
        $customer_id, 
        $guest_name, 
        $check_in, 
        $check_out, 
        $pax, 
        $rooms_json, 
        $total_amount, 
        $booking_date
    );

    if ($stmt->execute()) {
        echo json_encode(["success" => true, "booking_id" => $stmt->insert_id]);
    } else {
        http_response_code(500);
        echo json_encode(["error" => "Failed to save booking: " . $stmt->error]);
    }

    $stmt->close();
    $conn->close();
    exit;
}

$selectedRooms = [];
if (isset($_GET['rooms'])) {
    $decoded = json_decode(urldecode($_GET['rooms']), true);
    if (is_array($decoded)) {
        $selectedRooms = $decoded;
    }
}
?>


<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Book — DAMPA Resort</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    html,body { height:100%; margin:0; }
    body {
      font-family: "Inter", system-ui, -apple-system, "Segoe UI", Roboto, Arial;
      background: linear-gradient(180deg, #E8C580 0%, #E48523 100%);
      color: #3D1203;
      -webkit-font-smoothing:antialiased;
      -moz-osx-font-smoothing:grayscale;
      padding:32px;
      display:flex;
      align-items:flex-start;
      justify-content:center;
    }

    .book-wrapper {
      width: 100%;
      max-width: 980px;
      background: #fffdf8;
      border-radius: 16px;
      box-shadow: 0 16px 40px rgba(61,18,3,0.16);
      border: 1px solid rgba(61,18,3,0.06);
      overflow: hidden;
    }

    .book-header {
      background: #3D1203;
      color: #E8C580;
      padding: 18px 22px;
      border-bottom: 3px solid #E48523;
    }

    .book-header .title { font-weight:800; font-size:20px; letter-spacing:0.4px; }
    .book-header .subtitle { font-size:13px; opacity:0.9; margin-top:4px; }

    .book-body {
      padding: 24px;
      background: linear-gradient(180deg, rgba(232,197,128,0.06), #fffdf8);
    }

    label { display:block; font-weight:700; color:#3D1203; margin-bottom:6px; }
    .input-plain {
      width:100%;
      padding:10px 12px;
      border-radius:8px;
      border:1px solid rgba(61,18,3,0.12);
      background:#fffefb;
      color:#3D1203;
      box-sizing:border-box;
    }
    .input-plain:focus { outline:none; box-shadow:0 4px 14px rgba(186,61,3,0.12); border-color:#BA3D03; }

    .room-types-box {
      background:#fff;
      border-radius:10px;
      padding:10px;
      border:1px solid rgba(61,18,3,0.05);
      max-height:220px;
      overflow:auto;
    }

    .room-item {
      display:flex;
      justify-content:space-between;
      align-items:center;
      padding:10px;
      margin-bottom:10px;
      border-radius:8px;
      background:#fffaf5;
      border:1px solid rgba(61,18,3,0.04);
    }
    .room-item .r-name { color:#BA3D03; font-weight:700; }
    .room-item .r-amt { color:#E48523; font-weight:800; }

    .summary-card {
      background:#BA3D03;
      color:#fff;
      padding:18px;
      border-radius:12px;
      border:1px solid rgba(255,255,255,0.08);
      box-shadow:0 10px 28px rgba(61,18,3,0.14);
    }
    .summary-card .label { font-weight:700; font-size:13px; }
    .summary-card .meta { color: rgba(255,255,255,0.9); font-size:13px; margin-top:6px; }
    .summary-card .total { font-size:26px; font-weight:900; text-align:right; }

    .btn-book {
      width:100%;
      padding:12px 14px;
      border-radius:10px;
      border:none;
      background:#3D1203;
      color:#E8C580;
      font-weight:800;
      font-size:15px;
      cursor:pointer;
      margin-top:12px;
    }
    .btn-book:hover { background:#BA3D03; color:#fff; transform:translateY(-1px); }

    @media (max-width: 767px) {
      body { padding:16px; }
      .book-header { padding:14px; }
      .book-body { padding:16px; }
    }
  </style>
</head>
<body>
  <div class="book-wrapper">
    <div class="book-header d-flex justify-content-between align-items-start">
      <div>
        <div class="title">Book Reservation</div>
        <div class="subtitle">Complete the form to confirm your reservation</div>
      </div>
      <div id="bookingDateLabel" class="small text-white-50"></div>
    </div>

    <div class="book-body">
      <div class="container-fluid">
        <div class="row gx-4">
          <div class="col-md-7">
            <div class="mb-3">
              <label for="guestName">Guest Name</label>
              <input id="guestName" class="input-plain" type="text" placeholder="Guest name (from account)">
            </div>

            <div class="mb-3">
              <label>Room Type(s)</label>
              <div id="roomTypes" class="room-types-box"></div>
            </div>

            <div class="row g-2 mb-3">
              <div class="col-6">
                <label>Check-in Date</label>
                <input id="checkInDate" class="input-plain" type="date">
              </div>
              <div class="col-6">
                <label>Check-in Time</label>
                <input id="checkInTime" class="input-plain" type="time">
              </div>
            </div>

            <div class="row g-2 mb-3">
              <div class="col-6">
                <label>Check-out Date</label>
                <input id="checkOutDate" class="input-plain" type="date">
              </div>
              <div class="col-6">
                <label>Check-out Time</label>
                <input id="checkOutTime" class="input-plain" type="time">
              </div>
            </div>

            <div class="mb-3">
              <label for="pax">Pax</label>
              <input id="pax" class="input-plain" type="number" min="1" value="1">
            </div>
          </div>

          <div class="col-md-5">
            <div class="summary-card">
              <div class="d-flex justify-content-between align-items-start">
                <div>
                  <div class="label">Total</div>
                  <div id="roomCount" class="meta">0 rooms</div>
                </div>
                <div id="totalAmount" class="total">₱0.00</div>
              </div>

              <div class="mt-2">
                <div class="label">Reservation Fee</div>
                <div class="total">₱1,000.00</div>
              </div>

              <div class="mt-3">
                <div class="small-muted" style="color:rgba(255,255,255,0.9)">Booking Date</div>
                <div id="bookingDateSmall" class="meta"></div>
              </div>

              <button id="confirmBook" class="btn-book">Pay Reservation Fee & Book</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div id="notify" style="position:fixed; bottom:18px; right:18px; z-index:3000; display:none;"></div>

<script>
  const loggedInUserName = <?= json_encode($customerFullName ?: '') ?>;
  const selectedRooms = <?= json_encode($selectedRooms ?? []) ?>;
  const RESERVATION_FEE = 1000; // fixed reservation fee

  function readBooking() {
    try { return JSON.parse(localStorage.getItem('dampa_booking')) || null; }
    catch { return null; }
  }

  function saveBooking(data) {
    localStorage.setItem('dampa_booking', JSON.stringify(data));
  }

  function formatCurrency(n) { return '₱' + Number(n || 0).toLocaleString(); }

  function showNotification(msg, ok = true) {
    const el = document.createElement('div');
    el.textContent = msg;
    el.style.background = ok ? '#2d6a2d' : '#b02a2a';
    el.style.color = '#fff';
    el.style.padding = '10px 14px';
    el.style.borderRadius = '8px';
    el.style.boxShadow = '0 6px 20px rgba(0,0,0,0.12)';
    const wrap = document.getElementById('notify');
    wrap.appendChild(el);
    wrap.style.display = 'block';
    setTimeout(() => { el.remove(); if(!wrap.children.length) wrap.style.display = 'none'; }, 2600);
  }

  function populateBookingUI(b) {
    if(!b) return;
    const d = b.bookingDate ? new Date(b.bookingDate) : new Date();
    document.getElementById('bookingDateLabel').textContent = d.toLocaleString();
    document.getElementById('bookingDateSmall').textContent = d.toLocaleString();

    const box = document.getElementById('roomTypes');
    box.innerHTML = '';
    let totalAmount = 0;

    (b.rooms || []).forEach(r => {
      const qty = Number(r.qty || 1);
      const price = Number(r.price || r.amount || 0);
      const subtotal = qty * price;

      const item = document.createElement('div');
      item.className = 'room-item';
      item.innerHTML = `<div class="r-name">${r.name} x${qty}</div>
                        <div class="r-amt">${formatCurrency(subtotal)}</div>`;
      box.appendChild(item);

      totalAmount += subtotal;
    });

    document.getElementById('roomCount').textContent = `${b.rooms.length} room${b.rooms.length > 1 ? 's' : ''}`;
    document.getElementById('totalAmount').textContent = formatCurrency(totalAmount);
    b.total = totalAmount; // total for display only
    saveBooking(b);
  }

  function init() {
    let booking = readBooking() || {};
    const now = new Date();

    if (!booking.rooms || booking.rooms.length === 0) {
      booking = { rooms: selectedRooms, total: 0, bookingDate: now.toISOString() };
    }

    populateBookingUI(booking);

    const guestInput = document.getElementById('guestName');
    if(loggedInUserName){
      guestInput.value = loggedInUserName;
      guestInput.readOnly = true;
      booking.guestName = loggedInUserName;
      saveBooking(booking);
    }

    document.getElementById('confirmBook').addEventListener('click', async () => {
      const guest = guestInput.value.trim();
      const checkInDate = document.getElementById('checkInDate').value;
      const checkInTime = document.getElementById('checkInTime').value;
      const checkOutDate = document.getElementById('checkOutDate').value;
      const checkOutTime = document.getElementById('checkOutTime').value;
      const pax = Number(document.getElementById('pax').value || 1);

      if(!guest) return showNotification('Please provide guest name.', false);
      if(!checkInDate || !checkInTime || !checkOutDate || !checkOutTime)
        return showNotification('Select check-in & check-out date/time.', false);

      const payload = {
        guestName: guest,
        checkIn: `${checkInDate}T${checkInTime}`,
        checkOut: `${checkOutDate}T${checkOutTime}`,
        pax,
        rooms: booking.rooms,
        total: booking.total, // for display only
        bookingDate: booking.bookingDate
      };

      try {
        const res = await fetch('book.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });

        const result = await res.json();
        if(res.ok && result.success){
          showNotification('✅ Booking saved successfully!');
          localStorage.removeItem('dampa_booking');
          // Redirect to payment page with booking_id & **reservation fee only**
          setTimeout(() => {
            window.location.href = `customer_payment.php?booking_id=${result.booking_id}&amount=${RESERVATION_FEE}`;
          }, 1000);
        } else {
          showNotification(result.error || 'Failed to save booking.', false);
        }
      } catch(e){
        console.error(e);
        showNotification('An error occurred while saving booking.', false);
      }
    });
  }

  document.addEventListener('DOMContentLoaded', init);
</script>


</html>