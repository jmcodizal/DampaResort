<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dampa Resort - Home Dashboard</title>
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

        /* Header Styles */
        .header {
            background-color: var(--brown-dark);
            color: white;
            padding: 1rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .logo {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--icon-color);
        }

        .header-title h1 {
            font-size: 1.3rem;
            font-weight: 600;
            margin: 0;
        }

        .header-title p {
            font-size: 0.8rem;
            opacity: 0.9;
            margin: 0;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .notification-icon {
            position: relative;
            cursor: pointer;
            font-size: 1.3rem;
            transition: color 0.3s;
        }

        .notification-icon:hover {
            color: var(--icon-color);
        }

        .notification-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #e74c3c;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Welcome Banner */
        .welcome-banner {
            background: linear-gradient(135deg, var(--brown-medium), var(--brown-dark));
            color: white;
            padding: 2rem 1.5rem;
            margin: 2rem;
            border-radius: 16px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            margin-right: 170px;
            margin-left: 170px;
            
        }

        .welcome-content h2 {
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }

        .welcome-content h2 i {
            color: var(--icon-color);
        }

        .welcome-content p {
            opacity: 0.95;
            margin-bottom: 1rem;
            font-size: 1rem;
        }

        .quick-book-btn {
            background: var(--icon-color);
            color: white;
            border: none;
            padding: 0.8rem 1.5rem;
            border-radius: 8px;
            font-size: 1rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: transform 0.3s, box-shadow 0.3s;
            font-weight: 500;
        }

        .quick-book-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(230, 133, 36, 0.4);
        }

        /* Main Content */
        .main-container {
            padding: 0 1.5rem 1.5rem;
            flex: 1;
            max-width: 1400px;
            margin: 0 auto;
            width: 100%;
        }

        /* Section Title */
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
        }

        /* Notifications */
        .notification-card {
            background: var(--card-background);
            border-left: 5px solid var(--icon-color);
            padding: 1rem 1.25rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .notification-card i {
            color: var(--icon-color);
            margin-right: 0.5rem;
        }

        .notification-card strong {
            color: var(--brown-dark);
        }

        /* Quick Actions Grid */
        .quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .quick-action-card {
            background: var(--card-background);
            border-radius: 16px;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
            border: 2px solid transparent;
            box-shadow: 0 3px 12px rgba(0, 0, 0, 0.08);
            cursor: pointer;
        }

        .quick-action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
            border-color: var(--icon-color);
        }

        .quick-action-card i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--icon-color);
        }

        .quick-action-card h5 {
            color: var(--brown-dark);
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .quick-action-card p {
            color: var(--brown-medium);
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }

        .action-btn {
            background: var(--brown-medium);
            color: white;
            border: none;
            padding: 0.6rem 1.5rem;
            border-radius: 8px;
            font-size: 0.9rem;
            cursor: pointer;
            transition: background 0.3s;
        }

        .action-btn:hover {
            background: var(--brown-dark);
        }

        /* Current Bookings */
        .bookings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .booking-card {
            background: var(--card-background);
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 3px 12px rgba(0, 0, 0, 0.08);
            border-left: 5px solid var(--icon-color);
            transition: all 0.3s ease;
        }

        .booking-card:hover {
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.12);
            transform: translateY(-3px);
        }

        .room-image {
            width: 100%;
            height: 180px;
            object-fit: cover;
            border-radius: 12px;
            margin-bottom: 1rem;
        }

        .booking-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
        }

        .booking-header h5 {
            color: var(--brown-dark);
            font-weight: 600;
            margin: 0;
        }

        .status-badge {
            padding: 0.4rem 0.8rem;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .status-upcoming {
            background: #fff3cd;
            color: #856404;
        }

        .status-confirmed {
            background: #d1ecf1;
            color: #0c5460;
        }

        .status-completed {
            background: #d4edda;
            color: #155724;
        }

        .booking-details {
            margin-bottom: 1rem;
        }

        .booking-details p {
            margin-bottom: 0.5rem;
            color: var(--brown-medium);
            font-size: 0.9rem;
        }

        .booking-details i {
            width: 20px;
            color: var(--icon-color);
        }

        .booking-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .btn-view {
            padding: 0.5rem 1rem;
            border: 2px solid var(--brown-medium);
            background: white;
            color: var(--brown-medium);
            border-radius: 6px;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.3s;
            flex: 1;
        }

        .btn-view:hover {
            background: var(--brown-medium);
            color: white;
        }

        .btn-cancel {
            padding: 0.5rem 1rem;
            border: 2px solid #e74c3c;
            background: white;
            color: #e74c3c;
            border-radius: 6px;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.3s;
            flex: 1;
        }

        .btn-cancel:hover {
            background: #e74c3c;
            color: white;
        }

        /* No Bookings State */
        .no-bookings {
            text-align: center;
            padding: 3rem 2rem;
            color: var(--brown-medium);
            background: var(--card-background);
            border-radius: 16px;
            box-shadow: 0 3px 12px rgba(0, 0, 0, 0.08);
        }

        .no-bookings i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .no-bookings h3 {
            color: var(--brown-dark);
            margin-bottom: 0.5rem;
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

        /* Responsive Design */
        @media (max-width: 768px) {
            .header {
                padding: 0.8rem 1rem;
            }

            .header-title h1 {
                font-size: 1.1rem;
            }

            .header-title p {
                font-size: 0.75rem;
            }

            .logo {
                width: 40px;
                height: 40px;
            }

            .welcome-banner {
                padding: 1.5rem 1rem;
                margin: 1rem;
            }

            .welcome-content h2 {
                font-size: 1.4rem;
            }

            .welcome-content p {
                font-size: 0.9rem;
            }

            .main-container {
                padding: 0 1rem 1rem;
            }

            .quick-actions-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .quick-action-card {
                padding: 1.5rem;
            }

            .bookings-grid {
                grid-template-columns: 1fr;
            }

            .section-title {
                font-size: 1.1rem;
            }
        }

        @media (max-width: 480px) {
            .header-left {
                gap: 0.5rem;
            }

            .logo {
                width: 35px;
                height: 35px;
            }

            .header-title h1 {
                font-size: 1rem;
            }

            .welcome-banner {
                margin: 0.75rem;
                padding: 1.2rem 0.8rem;
            }

            .welcome-content h2 {
                font-size: 1.2rem;
            }

            .quick-book-btn {
                padding: 0.7rem 1.2rem;
                font-size: 0.9rem;
            }

            .quick-action-card i {
                font-size: 2.5rem;
            }

            .nav-item {
                font-size: 0.75rem;
            }

            .nav-item i {
                font-size: 1.1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-left">
            <img src="https://images.unsplash.com/photo-1566073771259-6a8506099945?w=100" alt="Logo" class="logo">
            <div class="header-title">
                <h1>Dampa sa Tabing Dagat</h1>
                <p>Your Beach Paradise</p>
            </div>
        </div>
        <div class="header-right">
            <div class="notification-icon" onclick="toggleNotifications()">
                <i class="fas fa-bell"></i>
                <span class="notification-badge">2</span>
            </div>
        </div>
    </header>

    <!-- Welcome Banner -->
    <div class="welcome-banner">
        <div class="welcome-content">
            <h2><i class="fas fa-hand-sparkles"></i> <span id="greeting">Welcome back</span>, <span id="guestName">Juan</span>!</h2>
            <p>Ready for your next seaside escape? Manage your bookings and explore our beautiful rooms.</p>
            <button class="quick-book-btn" onclick="bookRoom()">
                <i class="fas fa-calendar-plus"></i>
                Book Now
            </button>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-container">
        <!-- Notifications -->
        <div style="margin-bottom: 2rem;">
            <h2 class="section-title">
                <i class="fas fa-bell"></i> Notifications
            </h2>
            <div class="notification-card">
                <i class="fas fa-info-circle"></i>
                <strong>Reminder:</strong> Your check-in for "Room number 1" is in 3 days (November 13, 2025)
            </div>
            <div class="notification-card">
                <i class="fas fa-check-circle"></i>
                <strong>Confirmed:</strong> Your booking for December 2025 has been approved!
            </div>
        </div>

        <!-- Quick Actions -->
        <div style="margin-bottom: 2rem;">
            <h2 class="section-title">
                <i class="fas fa-bolt"></i> Quick Actions
            </h2>
            <div class="quick-actions-grid">
                <div class="quick-action-card" onclick="bookRoom()">
                    <i class="fas fa-calendar-plus"></i>
                    <h5>Book a Room</h5>
                    <p>Browse available rooms and make a new reservation</p>
                    <button class="action-btn">Book Now</button>
                </div>
                <div class="quick-action-card" onclick="viewHistory()">
                    <i class="fas fa-history"></i>
                    <h5>Booking History</h5>
                    <p>View all your past and current bookings</p>
                    <button class="action-btn">View History</button>
                </div>
                <div class="quick-action-card" onclick="manageProfile()">
                    <i class="fas fa-user-edit"></i>
                    <h5>Manage Profile</h5>
                    <p>Update your personal information and preferences</p>
                    <button class="action-btn">Edit Profile</button>
                </div>
            </div>
        </div>

        <!-- Current Bookings -->
        <div style="margin-bottom: 2rem;">
            <h2 class="section-title">
                <i class="fas fa-suitcase"></i> Current Bookings
            </h2>
            <div class="bookings-grid">
                <!-- Booking Card 1 -->
                <div class="booking-card">
                    <img src="https://images.unsplash.com/photo-1582719508461-905c673771fd?w=400" alt="Room" class="room-image">
                    <div class="booking-header">
                        <h5>Room number 1</h5>
                        <span class="status-badge status-upcoming">Upcoming</span>
                    </div>
                    <div class="booking-details">
                        <p><i class="fas fa-star"></i> Rating: ★★★★★</p>
                        <p><i class="fas fa-calendar"></i> Check-in: Nov 13, 2025</p>
                        <p><i class="fas fa-calendar"></i> Check-out: Nov 18, 2025</p>
                        <p><i class="fas fa-hashtag"></i> Booking ID: #BK2025001</p>
                    </div>
                    <div class="booking-actions">
                        <button class="btn-view" onclick="viewDetails(1)">
                            <i class="fas fa-eye"></i> View Details
                        </button>
                        <button class="btn-cancel" onclick="cancelBooking(1)">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </div>

                <!-- Booking Card 2 -->
                <div class="booking-card">
                    <img src="https://images.unsplash.com/photo-1566665797739-1674de7a421a?w=400" alt="Room" class="room-image">
                    <div class="booking-header">
                        <h5>Room number 2</h5>
                        <span class="status-badge status-confirmed">Confirmed</span>
                    </div>
                    <div class="booking-details">
                        <p><i class="fas fa-star"></i> Rating: ★★★★★</p>
                        <p><i class="fas fa-calendar"></i> Check-in: Dec 20, 2025</p>
                        <p><i class="fas fa-calendar"></i> Check-out: Dec 27, 2025</p>
                        <p><i class="fas fa-hashtag"></i> Booking ID: #BK2025002</p>
                    </div>
                    <div class="booking-actions">
                        <button class="btn-view" onclick="viewDetails(2)">
                            <i class="fas fa-eye"></i> View Details
                        </button>
                        <button class="btn-cancel" onclick="cancelBooking(2)">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

        <!-- Navigation Bar -->
    <nav class="nav-bar">
        <div class="nav-container">
            <a href="userDampa.php" class="nav-item <?php echo ($current_page == 'userDampa.php') ? 'active' : ''; ?>">
                <i class="fas fa-home"></i> Home
            </a>
            <a href="explore_rooms.php" class="nav-item <?php echo ($current_page == 'explore.php') ? 'active' : ''; ?>">
                <i class="fas fa-tree"></i> Explore
            </a>
            <a href="book_room.php" class="nav-item <?php echo ($current_page == 'bookRoom.php') ? 'active' : ''; ?>">
                <i class="fas fa-book-open"></i> Book Room
            </a>
            <a href="customer_dashboard.php" class="nav-item <?php echo ($current_page == 'profile.php') ? 'active' : ''; ?>">
                <i class="fas fa-user"></i> Profile
            </a>
        </div>
    </nav>

    <script>
        // Dynamic greeting based on time
        window.onload = function() {
            const hour = new Date().getHours();
            const greetingEl = document.getElementById('greeting');
            let greeting = 'Welcome back';
            
            if(hour < 12) {
                greeting = 'Good morning';
            } else if(hour < 18) {
                greeting = 'Good afternoon';
            } else {
                greeting = 'Good evening';
            }
            
            greetingEl.textContent = greeting;
        }

        // Navigation Functions
        function bookRoom() {
            alert('Redirecting to booking page...');
            // window.location.href = 'booking.php';
        }

        function viewHistory() {
            alert('Redirecting to booking history...');
            // window.location.href = 'history.php';
        }

        function manageProfile() {
            alert('Redirecting to profile management...');
            // window.location.href = 'profile.php';
        }

        function viewDetails(bookingId) {
            alert('Viewing details for booking #' + bookingId);
            // window.location.href = 'booking-details.php?id=' + bookingId;
        }

        function cancelBooking(bookingId) {
            if(confirm('Are you sure you want to cancel this booking?')) {
                alert('Booking #' + bookingId + ' has been cancelled');
                // Send cancellation request to backend
            }
        }

        function toggleNotifications() {
            alert('Notification panel would open here');
            // Open notification modal or dropdown
        }
    </script>
</body>
</html>