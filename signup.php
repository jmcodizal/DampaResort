<?php
$servername = "localhost";
$username = "root";
$password = "";
$database = "dampa_booking";

$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    die("Database connection failed.");
}

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $first = trim($_POST['first_name']);
    $middle = trim($_POST['middle_name']);
    $last = trim($_POST['last_name']);
    $contact = trim($_POST['contact_number']);
    $email = trim($_POST['email']);
    $pass = $_POST['password'];

    $check = $conn->prepare("SELECT * FROM customer WHERE email = ? OR contact_number = ?");
    $check->bind_param("ss", $email, $contact);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows > 0) {
        $error = "Email or contact number already exists.";
    } else {
        $hashed_pass = password_hash($pass, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("
            INSERT INTO customer (first_name, middle_name, last_name, contact_number, email, password)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("ssssss", $first, $middle, $last, $contact, $email, $hashed_pass);

        if ($stmt->execute()) {
            $success = "Account created successfully! You can now login.";
        } else {
            $error = "Registration failed. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>DAMPA sa Tabing Dagat | Sign Up</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">

<style>
  
  html, body {
      height: 100%;
      margin: 0;
  }

  body {
      display: flex;
      flex-direction: column;
      min-height: 100vh;
      font-family: 'Poppins', sans-serif;
      background: linear-gradient(180deg, rgba(235,226,216,0.35), rgba(244,234,224,0.35)), 
                  url("images/beach-view.jpg") no-repeat center center fixed;
      background-size: cover;
  }

  .content-wrap {
      flex: 1;
      padding-bottom: 40px;
  }

  .site-header {
      background: #3b130b;
      padding: 14px 20px;
      text-align: center;
      color: #f4e7da;
      font-weight: 700;
      font-size: 20px;
      box-shadow: 0 4px 14px rgba(0,0,0,0.25);
  }

  .site-footer {
      background: #3b130b;
      color: #f4e7da;
      text-align:center;
      padding: 14px;
      font-size: 13px;
      margin-top: auto; 
  }

  .hero {
    max-width: 760px;
    margin: 18px auto 0; 
    text-align: center;
    padding: 6px 12px;
  }

  .hero h2 {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    font-size: 34px;
    margin: 6px 0 6px;
    color: #2e1410;
    font-weight: 800;
  }

  .signup-wrap {
      display: flex;
      justify-content: center;
      margin-top: 6px; 
  }

  .card {
      max-width: 520px; 
      width: 100%;
      background: linear-gradient(180deg, #f7eae0, #efe1cf);
      border-radius: 14px;
      border: none;
      padding: 44px 28px 28px;
      box-shadow: 0 18px 40px rgba(0,0,0,0.12);
  }

  .title {
      color: #3b130b;
      font-weight: 700;
      font-size: 22px;
      text-align: center;
  }

  .subtitle {
      text-align:center;
      color: #8f6b55;
      margin-bottom:18px;
      font-size:13px;
  }

  .form-label {
      color: #6b341f;
      font-weight: 600;
      font-size: 13px;
  }

  .form-control {
      border-radius:10px;
      border: 1px solid rgba(107,52,31,0.12);
      background: rgba(255,255,255,0.9);
      padding:12px 14px;
      color: #3d2316;
  }

  .form-control:focus {
      border-color: #e5892e;
      box-shadow: 0 6px 20px rgba(101,54,31,0.08);
  }

  .btn-primary {
      background: linear-gradient(180deg, #e5892e, #cc6f1d);
      border: none;
      border-radius: 10px;
      padding: 10px;
      font-weight: 700;
      box-shadow: 0 6px 18px rgba(229,137,46,0.25);
      width: 50%;
      display: block;      
      margin: 0 auto; 
  }

  .btn-primary:hover {
      transform: translateY(-1px);
      filter:brightness(0.98);
  }

  .login-foot {
      text-align: center;
      margin-top: 14px;
      color: #8f6b55;
  }

  .btn-link {
  font-weight: 700;
  color: #6b341f;
  text-decoration: none;
  }
</style>
</head>

<body>

<div class="content-wrap">

<!-- HEADER -->
<div class="site-header">
  DAMPA sa Tabing Dagat — User Registration
</div>

<div class="hero">
  <h2><i class="fa-solid fa-user"></i> User Registration</h2>
</div>

<div class="signup-wrap">
  <div class="card">
    <h3 class="title">Create Your Account</h3>
    <p class="subtitle">Fill out the form to get started</p>

    <?php if (!empty($error)) : ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php elseif (!empty($success)) : ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="row">
        <div class="col-md-4 mb-3">
          <label class="form-label">First Name</label>
          <input type="text" name="first_name" class="form-control" required>
        </div>

        <div class="col-md-4 mb-3">
          <label class="form-label">Middle Name</label>
          <input type="text" name="middle_name" class="form-control">
        </div>

        <div class="col-md-4 mb-3">
          <label class="form-label">Last Name</label>
          <input type="text" name="last_name" class="form-control" required>
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label">Contact Number</label>
        <input type="text" name="contact_number" class="form-control" required>
      </div>

      <div class="mb-3">
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-control" required>
      </div>

      <div class="mb-3">
        <label class="form-label">Password</label>
        <input type="password" name="password" class="form-control" required>
      </div>

      <button type="submit" class="btn btn-primary">Sign Up</button>

      <p class="login-foot">
        Already have an account? <a href="login.php" class="btn-link">Login here</a>
      </p>
    </form>
  </div>
</div>

</div> 


<div class="site-footer">
  © <?= date("Y") ?> DAMPA sa Tabing Dagat. All rights reserved.
</div>

</body>
</html>
