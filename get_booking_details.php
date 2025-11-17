<?php
session_start();
$servername = "localhost";
$username = "root";
$password = "";
$database = "dampa_booking";

$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}
session_start();

if (!isset($_SESSION['customer_logged_in']) || !$_SESSION['customer_logged_in']) {
    echo '<div class="error">You must be logged in to rate a booking.</div>';
    exit;
}

$customer_id = $_SESSION['customer_id'];

if (!isset($_GET['booking_id'])) {
    echo '<div class="error">No booking ID provided.</div>';
    exit;
}

$booking_id = intval($_GET['booking_id']);

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
    echo '<div class="error">Booking not found.</div>';
    exit;
}

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

header('Content-Type: application/json');

if (isset($_GET['booking_id'])) {
    $booking_id = intval($_GET['booking_id']);


    $stmt = $conn->prepare('
        SELECT b.*, c.first_name, c.last_name, c.contact_number, c.email 
        FROM bookings b 
        LEFT JOIN customer c ON b.customer_id = c.customer_id 
        WHERE b.booking_id = ?
    ');
    $stmt->bind_param('i', $booking_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $booking = $result->fetch_assoc();

    if ($booking) {
        // --- Calculate duration ---
        $check_in = new DateTime($booking['check_in']);
        $check_out = new DateTime($booking['check_out']);
        $duration = $check_in->diff($check_out)->days;

        // --- Parse room details ---
        $rooms = json_decode($booking['rooms'], true);
        $room_details = '<ul>';
        if (is_array($rooms)) {
            foreach ($rooms as $room) {
                $name = htmlspecialchars($room['name']);
                $price = number_format($room['price'], 2);
                $qty = intval($room['qty']);
                $amount = number_format($room['amount'], 2);
                $room_details .= "<li>{$name} - ₱{$price} × {$qty} = ₱{$amount}</li>";
            }
        } else {
            $room_details .= '<li>No room details</li>';
        }
        $room_details .= '</ul>';

        // --- Send JSON response ---
        echo json_encode([
            'success' => true,
            'booking' => $booking,
            'formatted_check_in' => date('M j, Y g:i A', strtotime($booking['check_in'])),
            'formatted_check_out' => date('M j, Y g:i A', strtotime($booking['check_out'])),
            'formatted_booking_date' => date('M j, Y g:i A', strtotime($booking['booking_date'])),
            'duration' => $duration,
            'room_details' => $room_details
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Booking not found']);
    }

    $stmt->close();
    $conn->close();
} else {
    echo json_encode(['success' => false, 'message' => 'No booking ID provided']);
}
?>
