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

// --- Ensure customer is logged in ---
if (
    !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true ||
    !isset($_SESSION['role']) || $_SESSION['role'] !== 'Customer' ||
    !isset($_SESSION['customer_id'])
) {
    echo '<div class="alert alert-danger text-center mt-4">You must be logged in to rate a booking.</div>';
    exit;
}

$customer_id = $_SESSION['customer_id'];

// --- Validate booking_id ---
if (!isset($_GET['booking_id']) || !is_numeric($_GET['booking_id'])) {
    echo '<div class="alert alert-danger text-center mt-4">No booking ID provided.</div>';
    exit;
}

$booking_id = intval($_GET['booking_id']);

// --- Fetch booking with existing rating (if any) ---
$stmt = $conn->prepare('
    SELECT b.*, r.rating, r.review_text 
    FROM bookings b 
    LEFT JOIN ratings r ON b.booking_id = r.booking_id 
    WHERE b.booking_id = ? AND b.customer_id = ?
');
$stmt->bind_param('ii', $booking_id, $customer_id);
$stmt->execute();
$result = $stmt->get_result();
$booking = $result->fetch_assoc();
$stmt->close();

if (!$booking) {
    echo '<div class="alert alert-danger text-center mt-4">Booking not found or does not belong to you.</div>';
    exit;
}

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
?>

<!-- HTML OUTPUT -->
<div class="booking-info">
    <p><strong>Booking #<?php echo htmlspecialchars($booking['booking_id']); ?></strong></p>
    <p>Room: <?php echo getRoomNames($booking['rooms']); ?></p>
    <p>Stay: <?php echo formatSimpleDate($booking['check_in']); ?> - <?php echo formatSimpleDate($booking['check_out']); ?></p>
</div>

<form id="ratingForm">
    <input type="hidden" name="booking_id" value="<?php echo htmlspecialchars($booking['booking_id']); ?>">

    <div class="stars-container">
        <div class="stars" id="stars">
            <?php for ($i = 1; $i <= 5; $i++): ?>
                <span class="star <?php echo (!empty($booking['rating']) && $i <= $booking['rating']) ? 'selected' : ''; ?>" data-rating="<?php echo $i; ?>">
                    <i class="fas fa-star"></i>
                </span>
            <?php endfor; ?>
        </div>
        <div class="rating-text" id="rating-text">
            <?php echo !empty($booking['rating']) ? "You rated this {$booking['rating']}★" : "Tap to rate"; ?>
        </div>
        <input type="hidden" name="rating" id="rating-value" value="<?php echo htmlspecialchars($booking['rating'] ?? 0); ?>" required>
    </div>

    <div class="form-group">
        <label for="review_text">Your Review (Optional)</label>
        <textarea id="review_text" name="review_text" placeholder="Tell us about your experience..."><?php echo htmlspecialchars($booking['review_text'] ?? ''); ?></textarea>
    </div>

    <div class="modal-buttons">
        <button type="button" class="btn btn-secondary" onclick="closeModal('rateStayModal')">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="submitRating()">Submit Rating</button>
    </div>
</form>

<script>
document.querySelectorAll('.star').forEach(star => {
    star.addEventListener('click', function() {
        const rating = parseInt(this.getAttribute('data-rating'));
        document.getElementById('rating-value').value = rating;

        document.querySelectorAll('.star').forEach(s => s.classList.remove('selected'));
        for (let i = 0; i < rating; i++) {
            document.querySelectorAll('.star')[i].classList.add('selected');
        }

        document.getElementById('rating-text').innerText = `You rated this ${rating}★`;
    });
});

function submitRating() {
    const form = document.getElementById('ratingForm');
    const formData = new FormData(form);

    fetch('submit_rating.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            closeModal('rateStayModal');
            location.reload();
        } else {
            alert(data.message || 'Error submitting rating');
        }
    })
    .catch(err => console.error('Error:', err));
}
</script>
