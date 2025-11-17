<?php
// --- Database Connection ---
$servername = "localhost";
$username   = "root";
$password   = "";
$database   = "dampa_booking";

$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// --- Handle Add Room Form Submission ---
$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_room'])) {
    $room_name = trim($_POST['room_name']);
    $capacity  = (int) $_POST['capacity'];
    $rate      = (float) $_POST['rate'];
    $amenities = trim($_POST['amenities']);
    $status    = $_POST['status'] ?? 'Available';
    $room_image = null;

    // --- Handle Image Upload ---
    if (!empty($_FILES['room_image']['name'])) {
        $target_dir = "uploads/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);

        $file_name = basename($_FILES['room_image']['name']);
        $target_file = $target_dir . time() . "_" . $file_name;

        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
        $file_type = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        if (in_array($file_type, $allowed_types)) {
            if (move_uploaded_file($_FILES['room_image']['tmp_name'], $target_file)) {
                $room_image = basename($target_file);
            } else {
                $message = "<div class='alert alert-danger'>❌ Failed to upload image.</div>";
            }
        } else {
            $message = "<div class='alert alert-danger'>❌ Invalid image format. Only JPG, PNG, or GIF allowed.</div>";
        }
    }

    // --- Insert into Database ---
    if (empty($message)) {
        $stmt = $conn->prepare("
            INSERT INTO rooms (room_name, capacity, rate, amenities, status, room_image)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("sidsss", $room_name, $capacity, $rate, $amenities, $status, $room_image);

        if ($stmt->execute()) {
            $message = "<div class='alert alert-success'>✅ Room added successfully!</div>";
        } else {
            $message = "<div class='alert alert-danger'>❌ Database error: " . htmlspecialchars($stmt->error) . "</div>";
        }

        $stmt->close();
    }
}

// --- Handle Delete Room ---
if (isset($_GET['delete_id'])) {
    $delete_id = (int) $_GET['delete_id'];

    $imgResult = $conn->query("SELECT room_image FROM rooms WHERE id = $delete_id");
    if ($imgRow = $imgResult->fetch_assoc()) {
        if (!empty($imgRow['room_image']) && file_exists("uploads/".$imgRow['room_image'])) {
            unlink("uploads/".$imgRow['room_image']);
        }
    }

    $conn->query("DELETE FROM rooms WHERE id = $delete_id");
    header("Location: manage_room.php");
    exit;
}

// --- Handle Toggle Status ---
if (isset($_GET['toggle_id'])) {
    $toggle_id = (int) $_GET['toggle_id'];
    $result = $conn->query("SELECT status FROM rooms WHERE id = $toggle_id");
    if ($row = $result->fetch_assoc()) {
        $new_status = ($row['status'] === 'Available') ? 'Not Available' : 'Available';
        $stmt = $conn->prepare("UPDATE rooms SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $toggle_id);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: manage_room.php");
    exit;
}

// --- Fetch all rooms ---
$rooms = $conn->query("SELECT * FROM rooms ORDER BY id DESC");
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Manage Rooms</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
body { background-color: #f8f9fa; }
.container { max-width: 1000px; margin-top: 50px; }
.card { border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
img.room-img { width: 80px; height: 60px; object-fit: cover; border-radius: 6px; }
.container a {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            transition: background-color 0.3s;
            margin-bottom: 0;
        }
</style>
</head>
<body>
<div class="container">
    
    <div class="d-flex justify-content-between align-items-center mb-3">

    
    <a href="admin_dashboard.php" class="fs-4 text-danger">
        <i class="fa fa-arrow-left"></i>
    </a>

   
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRoomModal">
        ➕ Add Room
    </button>

</div>


    <!-- Rooms List -->
    <div class="card p-4">
        <h3 class="mb-3 text-center">🏨 Rooms List</h3>
        <?php if($message) echo $message; ?>
        <table class="table table-bordered table-hover">
            <thead class="table-dark text-center">
                <tr>
                    <th>ID</th>
                    <th>Room Name</th>
                    <th>Capacity</th>
                    <th>Rate</th>
                    <th>Amenities</th>
                    <th>Status</th>
                    <th>Image</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if($rooms->num_rows > 0): ?>
                    <?php while($room = $rooms->fetch_assoc()): ?>
                        <tr class="text-center">
                            <td><?= $room['id'] ?></td>
                            <td><?= htmlspecialchars($room['room_name']) ?></td>
                            <td><?= $room['capacity'] ?></td>
                            <td>₱ <?= number_format($room['rate'], 2) ?></td>
                            <td><?= htmlspecialchars($room['amenities']) ?></td>
                            <td>
                                <a href="?toggle_id=<?= $room['id'] ?>" class="btn btn-sm <?= $room['status']=='Available'?'btn-success':'btn-warning' ?>">
                                    <?= $room['status'] ?>
                                </a>
                            </td>
                            <td>
                                <?php if(!empty($room['room_image'])): ?>
                                    <img src="uploads/<?= $room['room_image'] ?>" class="room-img" alt="Room Image">
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="?delete_id=<?= $room['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this room?');">Delete</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="8" class="text-center">No rooms found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Room Modal -->
<div class="modal fade" id="addRoomModal" tabindex="-1" aria-labelledby="addRoomModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" enctype="multipart/form-data">
          <input type="hidden" name="add_room" value="1">
          <div class="modal-header">
              <h5 class="modal-title" id="addRoomModalLabel">➕ Add New Room</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
              <div class="mb-3">
                  <label class="form-label">Room Name</label>
                  <input type="text" name="room_name" class="form-control" required>
              </div>
              <div class="mb-3">
                  <label class="form-label">Capacity (pax)</label>
                  <input type="number" name="capacity" class="form-control" required min="1">
              </div>
              <div class="mb-3">
                  <label class="form-label">Rate (₱ per night)</label>
                  <input type="number" name="rate" class="form-control" step="0.01" required>
              </div>
              <div class="mb-3">
                  <label class="form-label">Amenities / Description</label>
                  <textarea name="amenities" class="form-control" rows="4" placeholder="Aircon, Private Bathroom, Free WiFi"></textarea>
              </div>
              <div class="mb-3">
                  <label class="form-label">Status</label>
                  <select name="status" class="form-select">
                      <option value="Available" selected>Available</option>
                      <option value="Not Available">Not Available</option>
                  </select>
              </div>
              <div class="mb-3">
                  <label class="form-label">Room Image</label>
                  <input type="file" name="room_image" class="form-control" accept="image/*">
              </div>
          </div>
          <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-primary w-100">Save Room</button>
          </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
