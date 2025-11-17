<?php
session_start();

// --- DATABASE CONNECTION ---
$conn = new mysqli("localhost", "root", "", "dampa_booking");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// --- FETCH CUSTOMER NAME (if logged in) ---
$customerFullName = '';
if (isset($_SESSION['customer_id'])) {
    $customer_id = $_SESSION['customer_id'];
    $stmt = $conn->prepare("SELECT first_name, last_name FROM customer WHERE customer_id = ?");
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $stmt->bind_result($first_name, $last_name);
    if ($stmt->fetch()) {
        $customerFullName = trim("$first_name $last_name");
    }
    $stmt->close();
}

// --- FETCH BOOKED ROOMS (Pending or Confirmed) ---
$bookedRooms = [];
$bookingQuery = "SELECT rooms, check_in, check_out FROM bookings WHERE status IN ('Pending', 'Confirmed')";
$bookingResult = $conn->query($bookingQuery);
if ($bookingResult && $bookingResult->num_rows > 0) {
    while ($row = $bookingResult->fetch_assoc()) {
        $rooms = json_decode($row['rooms'], true);
        if (is_array($rooms)) {
            foreach ($rooms as $r) {
                $bookedRooms[] = [
                    'name' => $r['name'],
                    'check_in' => $row['check_in'],
                    'check_out' => $row['check_out']
                ];
            }
        }
    }
}

// --- FETCH ALL ROOMS FROM ROOMS TABLE ---
$roomsData = [];
$roomsQuery = "SELECT id, room_name, capacity, rate, amenities, status, room_image FROM rooms ORDER BY id ASC";
$roomsResult = $conn->query($roomsQuery);
if ($roomsResult && $roomsResult->num_rows > 0) {
    while ($room = $roomsResult->fetch_assoc()) {
        // Use images/ folder and URL-encode filename to handle spaces/parentheses
        $imageFile = $room['room_image'];
        $room['image_path'] = (!empty($imageFile) && file_exists("images/" . $imageFile))
                              ? "images/" . rawurlencode($imageFile)
                              : "images/default-room.jpg";
        $roomsData[] = $room;
    }
}

// --- CLOSE DATABASE CONNECTION ---
$conn->close();
?>





<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>DAMPA Resort - Rooms</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

  <style>
    html, body {
      height: 100%;
    }

    body {
      font-family: "Inter", Arial, sans-serif;
      background: #E8C580;
      color: #3D1203;
      margin: 0;
      -webkit-font-smoothing: antialiased;
      min-height: 100vh;
    }

    header {
      background: url("images/transient-house-view-2.jpg") no-repeat center / cover;
      height: 160px;
      border-radius: 0 0 14px 14px;
      position: relative;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #fff;
      padding: 10px;
    }

    header::after {
      content: "";
      position: absolute;
      inset: 0;
      background: rgba(61, 18, 3, 0.38);
      border-radius: 0 0 14px 14px;
    }

    .header-inner {
      position: relative;
      z-index: 2;
      width: 100%;
      max-width: 1100px;
      display: flex;
      gap: 12px;
      align-items: center;
      justify-content: space-between;
      padding: 6px 8px;
    }

    .brand {
      display: flex;
      gap: 10px;
      align-items: center;
      color: #fff;
    }

    .brand img {
      width: 64px;
      height: auto;
      border-radius: 8px;
    }

   
    .filter-bar {
      display: flex;
      gap: 8px;
      align-items: center;
      background: rgba(255, 255, 255, 0.12);
      padding: 6px 8px;
      border-radius: 8px;
    }

    .filter-bar label {
      color: #fff;
      font-weight: 600;
      font-size: 0.9rem;
      margin-right: 4px;
    }

    .filter-bar input,
    .filter-bar select {
      border: none;
      border-radius: 6px;
      padding: 6px 8px;
      outline: none;
      min-width: 110px;
    }

    .filter-bar button {
      background: #E48523;
      color: #fff;
      border: none;
      border-radius: 6px;
      padding: 8px 12px;
      font-weight: 700;
    }

    main.container {
      max-width: 1100px;
      margin-top: 18px;
      margin-bottom: 18px;
    }

   
    .room-row {
      display: flex;
      gap: 12px;
      align-items: center;
      background: #fff;
      padding: 12px;
      border-radius: 10px;
      margin-bottom: 12px;
      box-shadow: 0 3px 8px rgba(0, 0, 0, 0.12);
      position: relative;
    }

    .room-image {
      flex: 0 0 160px;
      height: 100px;
      overflow: hidden;
      border-radius: 8px;
    }

    .room-image img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
    }

    .room-info {
      flex: 1;
      display: flex;
      flex-direction: column;
      gap: 6px;
      padding: 4px 6px;
    }

    .room-info h4 {
      margin: 0;
      color: #C04000;
      font-size: 1.05rem;
      font-weight: 800;
    }

    .room-info p {
      margin: 0;
      color: #3D1203;
    }

    .room-actions {
      display: flex;
      flex-direction: column;
      gap: 8px;
      align-items: flex-end;
      min-width: 180px;
    }

    .add-btn {
      background-color: #E48523;
      color: #fff;
      border: none;
      border-radius: 6px;
      padding: 8px 12px;
      font-weight: 700;
    }

    .add-btn:hover {
      background: #C04000;
      color: #fff;
    }

    
    .selected-section {
      margin-top: 18px;
      background: #F2D19B;
      border-radius: 12px;
      padding: 14px;
      box-shadow: 0 3px 8px rgba(0, 0, 0, 0.12);
    }

    .selected-section h5 {
      margin: 0 0 10px 0;
      font-weight: 800;
      color: #3D1203;
    }

    .room-list-wrapper {
      flex-grow: 1;
      max-height: 100px;
      overflow-y: auto;
      border: 1px solid #d8cab0;
      background: #fff7ed;
      border-radius: 6px;
      padding: 6px;
    }

    #room-list {
      list-style: none;
      padding: 0;
      margin: 0;
    }

    #room-list li {
      display: flex;
      justify-content: space-between;
      align-items: center;
      background: #fff;
      margin: 8px 0;
      padding: 10px 14px;
      border-radius: 8px;
      box-shadow: 0 1px 0 rgba(0, 0, 0, 0.03);
    }

    #room-list li .room-left strong {
      font-weight: 600;
      color: #3D1203;
    }

    .remove-room {
      background: none;
      border: none;
      color: #ff4d4d;
      font-size: 20px;
      cursor: pointer;
    }

    .selected-footer {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-top: 12px;
    }

    .total-label {
      font-weight: 800;
      color: #3D1203;
    }

    .book-btn {
      background: #C04000;
      color: #fff;
      border: none;
      border-radius: 8px;
      padding: 10px 16px;
      font-weight: 700;
      cursor: pointer;
    }

    .book-btn:hover {
      background: #3D1203;
      transform: translateY(-1px);
    }

    @media (max-width: 767px) {
      .room-row {
        flex-direction: column;
        align-items: stretch;
      }
      .room-image {
        width: 100%;
        height: 160px;
      }
      .room-actions {
        align-items: stretch;
        min-width: auto;
        flex-direction: row;
        justify-content: space-between;
      }
      .room-list-wrapper {
        height: 180px;
      }
      .book-btn {
        width: 100%;
      }
    }
    .details-link {
        cursor: pointer;
        color: #C04000;
        }
        .details-link:hover {
        color: #C04000;
        }

  </style>
</head>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>DAMPA Resort - Book Rooms</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    .room-row { display: flex; gap: 12px; border: 1px solid #ddd; padding: 12px; margin-bottom: 12px; border-radius: 8px; }
    .room-image img { width: 150px; height: 100px; object-fit: cover; border-radius: 8px; }
    .room-info { flex: 1; }
    .room-actions { display: flex; flex-direction: column; justify-content: center; }
    .add-btn { padding: 6px 12px; background: #C04000; color: #fff; border: none; border-radius: 6px; cursor: pointer; }
    .add-btn:disabled { background: #ccc; cursor: not-allowed; }
  </style>
</head>
<body>

<header>
  <div class="header-inner d-flex justify-content-between align-items-center p-3 bg-dark text-white">
    <div class="brand d-flex align-items-center gap-2">
  <a href="user_Home.php" style="color:white; text-decoration:none;">
    <i class="fa fa-arrow-left" style="font-size:20px;"></i>
  </a>

  <div>
    <h3 style="margin:0; font-size:1.1rem;">DAMPA Resort</h3>
    <small style="opacity:0.9;">Rooms & Reservations</small>
  </div>
</div>

    <div class="filter-bar">
      <label for="filterDate">Date:</label>
      <input type="date" id="filterDate">
      <button id="filterBtn" class="btn btn-light btn-sm">Filter</button>
    </div>

    <div class="welcome-user">
      <?php if (!empty($customerFullName)): ?>
        <span style="font-weight:600;">Choose your room, <?= htmlspecialchars($customerFullName) ?>!</span>
      <?php else: ?>
        <span style="font-weight:600;">Welcome, Guest!</span>
      <?php endif; ?>
    </div>
  </div>
</header>

<main class="container my-4">

<?php if (!empty($roomsData)): ?>
  <?php foreach ($roomsData as $room):
      $statusColor = ($room['status'] === 'Available') ? '#2ecc71' : '#e74c3c';
      $statusText = htmlspecialchars($room['status']);
  ?>
  <div class="room-row"
       data-price="<?= htmlspecialchars($room['rate']) ?>"
       data-capacity="<?= htmlspecialchars($room['capacity']) ?>"
       data-key="<?= htmlspecialchars($room['room_name']) ?>">

    <div class="room-image">
    <img src="<?= $room['image_path'] ?>" alt="<?= htmlspecialchars($room['room_name']) ?>" loading="lazy">
  </div>

    <div class="room-info">
      <h4><?= htmlspecialchars($room['room_name']) ?></h4>
      <p><strong>₱<?= number_format($room['rate'], 2) ?> / night</strong></p>
      <p>Capacity: <?= htmlspecialchars($room['capacity']) ?> pax</p>
      <?php if (!empty($room['amenities'])): ?>
        <small class="text-muted"><?= nl2br(htmlspecialchars($room['amenities'])) ?></small>
      <?php endif; ?>
      <span class="details-link" onclick="showRoomDetails('<?= addslashes($room['room_name']) ?>')">ℹ️ Details</span>
    </div>

    <div class="room-actions">
      <div>Status:
        <span class="room-status" style="font-weight:800; color:<?= $statusColor ?>;">
          <?= $statusText ?>
        </span>
      </div>
      <?php if ($room['status'] === 'Available'): ?>
        <button class="add-btn"
                onclick="addRoom('<?= addslashes($room['room_name']) ?>', <?= htmlspecialchars($room['rate']) ?>, this)">
          Add
        </button>
      <?php else: ?>
        <button class="add-btn" disabled>Unavailable</button>
      <?php endif; ?>
    </div>

  </div>
  <?php endforeach; ?>
<?php else: ?>
  <p class="text-center text-muted">No rooms available at the moment.</p>
<?php endif; ?>

<!-- Selected Rooms Section -->
<div class="selected-section mt-4">
  <h5>Selected Rooms</h5>
  <div class="room-list-wrapper mb-3">
    <ul id="room-list"></ul>
  </div>
  <div class="selected-footer d-flex justify-content-between align-items-center">
    <div class="total-label">Total: ₱<span id="total">0</span>.00</div>
    <button class="book-btn btn btn-primary" id="bookBtn">Book</button>
  </div>
</div>

<!-- Room Details Modal -->
<div class="modal fade" id="roomModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius: 12px;">
      <div class="modal-header" style="background:#C04000; color:#fff;">
        <h5 class="modal-title" id="roomModalLabel"></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="filter: invert(1);"></button>
      </div>
      <div class="modal-body" id="roomModalBody" style="color:#3D1203;"></div>
    </div>
  </div>
</div>

</main>

<div id="notification" style="position: fixed; bottom: 20px; right: 20px; background: #C04000; color: #fff; padding: 10px 18px; border-radius: 8px; box-shadow: 0 3px 6px rgba(0,0,0,0.2); display: none; z-index: 2000; font-weight: 600;"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const loggedInUserName = <?= json_encode($customerFullName ?: '') ?>;
const bookedRooms = <?= json_encode($bookedRooms) ?>;
const roomsData = <?= json_encode($roomsData, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>;

function addRoom(name, price, btn) {
  const key = btn.closest('.room-row').dataset.key || name;
  const list = document.getElementById("room-list");
  const existing = [...list.children].find(li => li.dataset.roomName === key);
  if (existing) { showNotification(`⚠️ ${key} is already added.`); return; }

  const li = document.createElement("li");
  li.dataset.roomName = key;
  li.dataset.price = price;
  li.dataset.qty = 1;
  li.innerHTML = `
    <div class="room-left"><strong>${key}</strong></div>
    <div style="display:flex; align-items:center; gap:12px;">
      <div class="room-right">₱${price.toLocaleString()}</div>
      <button class="remove-room" title="Remove" aria-label="Remove ${key}">&times;</button>
    </div>
  `;
  li.querySelector('.remove-room').addEventListener('click', () => {
    li.remove(); updateTotal(); showNotification(`❌ Removed ${key}`);
  });
  list.appendChild(li);
  updateTotal(); showNotification(`✅ Added ${key} to selected rooms.`);
}

function updateTotal() {
  let total = 0;
  document.querySelectorAll("#room-list li").forEach(li => total += Number(li.dataset.price) * (Number(li.dataset.qty) || 1));
  document.getElementById("total").textContent = total.toLocaleString();
}

function showNotification(message) {
  const box = document.getElementById("notification"); if (!box) return;
  box.textContent = message; box.style.display = "block"; box.style.opacity = "1";
  setTimeout(()=>{ box.style.transition="opacity 0.5s"; box.style.opacity="0"; },1800);
  setTimeout(()=>{ box.style.display="none"; box.style.opacity="1"; box.style.transition="none"; },2300);
}

function showRoomDetails(roomName){
  const room = roomsData.find(r=>r.room_name===roomName); if(!room) return;
  document.getElementById('roomModalLabel').textContent = room.room_name;
  document.getElementById('roomModalBody').innerHTML = `
    <img src="${room.image_path}" alt="${room.room_name}" style="width:100%;height:auto;margin-bottom:10px;border-radius:8px;">
    <strong>₱${parseFloat(room.rate).toLocaleString()} / night</strong><br>
    Capacity: ${room.capacity} pax<br><br>
    ${room.amenities ? room.amenities.replace(/\n/g,'<br>') : 'No amenities listed.'}
  `;
  new bootstrap.Modal(document.getElementById('roomModal')).show();
}

function applyFilters(){
  const selectedDate=document.getElementById("filterDate")?.value; if(!selectedDate){alert("Please select a date first."); return;}
  const selected=new Date(selectedDate); selected.setHours(0,0,0,0);
  document.querySelectorAll(".room-row").forEach(room=>{
    let reserved=false;
    bookedRooms.forEach(b=>{
      const checkInDate=new Date(b.check_in.split(" ")[0]);
      const checkOutDate=new Date(b.check_out.split(" ")[0]);
      checkInDate.setHours(0,0,0,0); checkOutDate.setHours(0,0,0,0);
      if(b.name.toLowerCase().trim()===room.dataset.key.toLowerCase().trim() &&
         selected>=checkInDate && selected<=checkOutDate){ reserved=true; }
    });
    const statusEl=room.querySelector(".room-actions span");
    const addBtn=room.querySelector(".add-btn");
    if(reserved){ statusEl.textContent="Reserved"; statusEl.style.color="red"; addBtn.disabled=true; addBtn.style.opacity="0.6"; addBtn.style.cursor="not-allowed"; }
    else{ statusEl.textContent="Available"; statusEl.style.color="#2ecc71"; addBtn.disabled=false; addBtn.style.opacity="1"; addBtn.style.cursor="pointer"; }
  });
}
document.getElementById("filterBtn").addEventListener("click", applyFilters);

document.getElementById("bookBtn").addEventListener("click",()=>{
  if(!loggedInUserName){ alert("Please log in first before booking."); window.location.href="customer-login.php"; return; }
  const list=document.querySelectorAll("#room-list li");
  if(list.length===0){ showNotification("⚠️ Please select at least one room before booking."); return; }
  const selectedRooms=[]; list.forEach(li=>{
    const name=li.dataset.roomName, price=Number(li.dataset.price), qty=Number(li.dataset.qty||1), amount=price*qty;
    selectedRooms.push({name,price,qty,amount});
  });
  const totalAmount=selectedRooms.reduce((sum,r)=>sum+r.amount,0);
  const bookingData={rooms:selectedRooms,total:totalAmount,bookingDate:new Date().toISOString()};
  localStorage.setItem("dampa_booking",JSON.stringify(bookingData));
  const roomsDataEncoded=encodeURIComponent(JSON.stringify(selectedRooms));
  window.location.href=`book.php?rooms=${roomsDataEncoded}`;
});
</script>
</body>
</html>