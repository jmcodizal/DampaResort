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

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $first = trim($_POST['first_name']);
    $middle = trim($_POST['middle_name']);
    $last = trim($_POST['last_name']);
    $age = (int)$_POST['age'];
    $gender = $_POST['gender'];
    $dob = $_POST['dob'];
    $contact = trim($_POST['contact_number']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // --- Validate required fields ---
    if (!$first || !$last || !$age || !$gender || !$dob || !$contact || !$email || !$password) {
        $error = "All required fields must be filled!";
    } else {
        // --- Check for duplicates ---
        $check = $conn->prepare("SELECT * FROM admin WHERE email=? OR contact_number=?");
        $check->bind_param("ss", $email, $contact);
        $check->execute();
        $result = $check->get_result();

        if ($result->num_rows > 0) {
            $error = "Email or contact number already exists.";
        } else {
           // --- Hash password ---
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // --- Insert admin ---
            $stmt = $conn->prepare("
                INSERT INTO admin 
                (first_name, middle_name, last_name, age, gender, dob, contact_number, email, password, profile_photo_path)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NULL)
            ");
            $stmt->bind_param(
                "sssisssss",
                $first,
                $middle,
                $last,
                $age,
                $gender,
                $dob,
                $contact,
                $email,
                $hashed_password
            );

            if ($stmt->execute()) {
                $success = "Admin account created successfully!";
            } else {
                $error = "Failed to register admin: " . $stmt->error;
            }

            $stmt->close();
        }
        $check->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin Signup | Dampa Booking</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-5">
    <div class="col-md-8 mx-auto card p-4 shadow">
        <h3 class="text-center mb-3">Admin Sign Up</h3>

        <?php if ($error) : ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php elseif ($success) : ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label>First Name</label>
                    <input type="text" name="first_name" class="form-control" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label>Middle Name</label>
                    <input type="text" name="middle_name" class="form-control">
                </div>
                <div class="col-md-4 mb-3">
                    <label>Last Name</label>
                    <input type="text" name="last_name" class="form-control" required>
                </div>
            </div>

            <div class="row">
                <div class="col-md-2 mb-3">
                    <label>Age</label>
                    <input type="number" name="age" class="form-control" required min="18">
                </div>
                <div class="col-md-3 mb-3">
                    <label>Gender</label>
                    <select name="gender" class="form-control" required>
                        <option value="">Select</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label>Date of Birth</label>
                    <input type="date" name="dob" class="form-control" required>
                </div>
            </div>

            <div class="mb-3">
                <label>Contact Number</label>
                <input type="text" name="contact_number" class="form-control" required>
            </div>

            <div class="mb-3">
                <label>Email</label>
                <input type="email" name="email" class="form-control" required>
            </div>

            <div class="mb-3">
                <label>Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>

            <button type="submit" class="btn btn-success w-100">Sign Up</button>
        </form>
    </div>
</div>

</body>
</html>
