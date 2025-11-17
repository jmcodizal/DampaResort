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

// --- Get customer_id from session ---
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


// Make sure the customer is logged in
if (!isset($_SESSION['customer_logged_in']) || !$_SESSION['customer_logged_in']) {
    header('Location: login.php');
    exit;
}

$customer_id = $_SESSION['customer_id']; // use session value

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

// --- Fetch booking details ---
$booking = null;
if (isset($_GET['booking_id'])) {
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
        die('Booking not found');
    }
}

// --- Handle rating submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_rating'])) {
    $booking_id = intval($_POST['booking_id']);
    $rating = intval($_POST['rating']);
    $review_text = trim($_POST['review_text']);

    // Check if rating exists
    $check_stmt = $conn->prepare('SELECT * FROM ratings WHERE booking_id = ?');
    $check_stmt->bind_param('i', $booking_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        // Update existing rating
        $update_stmt = $conn->prepare('
            UPDATE ratings 
            SET rating = ?, review_text = ?, updated_at = CURRENT_TIMESTAMP 
            WHERE booking_id = ?
        ');
        $update_stmt->bind_param('isi', $rating, $review_text, $booking_id);
        $update_stmt->execute();
        $update_stmt->close();
        $message = "Rating updated successfully!";
    } else {
        // Insert new rating
        $insert_stmt = $conn->prepare('
            INSERT INTO ratings (booking_id, customer_id, rating, review_text) 
            VALUES (?, ?, ?, ?)
        ');
        $insert_stmt->bind_param('iiis', $booking_id, $customer_id, $rating, $review_text);
        $insert_stmt->execute();
        $insert_stmt->close();
        $message = "Thank you for your rating!";
    }
    $check_stmt->close();

    // Redirect back to profile after rating
    header('Location: profile.php?rated=1');
    exit;
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rate Your Stay</title>
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
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .rating-container {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 500px;
        }

        .rating-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .rating-header h1 {
            color: var(--brown-dark);
            margin-bottom: 0.5rem;
        }

        .booking-info {
            background: var(--brown-light);
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }

        .booking-info p {
            margin: 0.25rem 0;
            color: var(--brown-dark);
        }

        .stars-container {
            text-align: center;
            margin: 2rem 0;
        }

        .stars {
            display: inline-flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .star {
            font-size: 2.5rem;
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

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--brown-dark);
            font-weight: 500;
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

        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }

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

        .error {
            color: #dc3545;
            text-align: center;
            margin-bottom: 1rem;
            padding: 0.5rem;
            background: #f8d7da;
            border-radius: 4px;
        }

        .success {
            color: #155724;
            text-align: center;
            margin-bottom: 1rem;
            padding: 0.5rem;
            background: #d4edda;
            border-radius: 4px;
        }
    </style>
</head>
<body>
  <div class="rating-container">
    <div class="rating-header">
        <h1><i class="fas fa-star"></i> Rate Your Stay</h1>
        <p>How was your experience with us?</p>
    </div>

    <?php if (isset($error)): ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if (isset($message)): ?>
        <div class="success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <?php if (!empty($booking)): ?>
        <div class="booking-info">
            <p><strong>Booking #<?php echo htmlspecialchars($booking['booking_id']); ?></strong></p>
            <p>Room: <?php echo getRoomNames($booking['rooms']); ?></p>
            <p>Stay: <?php echo formatSimpleDate($booking['check_in']); ?> - <?php echo formatSimpleDate($booking['check_out']); ?></p>
        </div>

        <form method="POST" action="">
            <input type="hidden" name="booking_id" value="<?php echo htmlspecialchars($booking['booking_id']); ?>">

            <div class="stars-container">
                <div class="stars" id="stars">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <span class="star" data-rating="<?php echo $i; ?>">
                            <i class="fas fa-star"></i>
                        </span>
                    <?php endfor; ?>
                </div>
                <div class="rating-text" id="rating-text">Tap to rate</div>
                <input type="hidden" name="rating" id="rating-value" value="<?php echo $booking['rating'] ?? 0; ?>" required>
            </div>

            <div class="form-group">
                <label for="review_text">Your Review (Optional)</label>
                <textarea 
                    id="review_text" 
                    name="review_text" 
                    placeholder="Tell us about your experience..."
                ><?php echo htmlspecialchars($booking['review_text'] ?? ''); ?></textarea>
            </div>

            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="window.history.back()">Cancel</button>
                <button type="submit" class="btn btn-primary" name="submit_rating">Submit Rating</button>
            </div>
        </form>
    <?php else: ?>
        <div class="error">
            Booking not found or you do not have permission to rate this booking.
        </div>
    <?php endif; ?>
</div>

    <script>
        // Star rating functionality
        const stars = document.querySelectorAll('.star');
        const ratingValue = document.getElementById('rating-value');
        const ratingText = document.getElementById('rating-text');
        
        const ratingTexts = {
            1: 'Poor',
            2: 'Fair', 
            3: 'Good',
            4: 'Very Good',
            5: 'Excellent'
        };

        // Initialize stars if there's an existing rating
        const currentRating = parseInt(ratingValue.value);
        if (currentRating > 0) {
            updateStars(currentRating);
            ratingText.textContent = ratingTexts[currentRating] || 'Tap to rate';
        }

        stars.forEach(star => {
            star.addEventListener('click', () => {
                const rating = parseInt(star.getAttribute('data-rating'));
                ratingValue.value = rating;
                updateStars(rating);
                ratingText.textContent = ratingTexts[rating];
            });

            star.addEventListener('mouseover', () => {
                const rating = parseInt(star.getAttribute('data-rating'));
                highlightStars(rating);
            });
        });

        document.getElementById('stars').addEventListener('mouseleave', () => {
            const currentRating = parseInt(ratingValue.value);
            updateStars(currentRating);
        });

        function updateStars(rating) {
            stars.forEach(star => {
                const starRating = parseInt(star.getAttribute('data-rating'));
                if (starRating <= rating) {
                    star.classList.add('active');
                } else {
                    star.classList.remove('active');
                }
            });
        }

        function highlightStars(rating) {
            stars.forEach(star => {
                const starRating = parseInt(star.getAttribute('data-rating'));
                if (starRating <= rating) {
                    star.style.color = 'var(--icon-color)';
                } else {
                    star.style.color = '#ddd';
                }
            });
        }

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            if (parseInt(ratingValue.value) === 0) {
                e.preventDefault();
                alert('Please select a rating by clicking on the stars');
            }
        });
    </script>
</body>
</html>