<?php
session_start();

$servername = "localhost";
$username   = "root";
$password   = "";
$database   = "dampa_booking";

$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    die("Database connection failed.");
}
$conn->set_charset("utf8mb4");
$error = '';
$old_input = [
    'email_or_contact' => ''
];

function get_post($key) {
    return isset($_POST[$key]) ? trim($_POST[$key]) : '';
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email_or_contact = get_post('email_or_contact');
    $password_input   = get_post('password');
    $old_input['email_or_contact'] = htmlspecialchars($email_or_contact, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    if ($email_or_contact === '' || $password_input === '') {
        $error = "Please fill in both fields.";
    } else {
        $generic_error = "Invalid email/contact number or password.";
        $sql_admin = $conn->prepare("SELECT admin_id, first_name, last_name, password FROM admin WHERE email = ? OR contact_number = ?");
        
        if ($sql_admin) {
            $sql_admin->bind_param("ss", $email_or_contact, $email_or_contact);

            if ($sql_admin->execute()) {
                $result_admin = $sql_admin->get_result();

                if ($result_admin && $result_admin->num_rows > 0) {
                    $admin = $result_admin->fetch_assoc();

                    if (!empty($admin['password']) && password_verify($password_input, $admin['password'])) {
                        session_regenerate_id(true);
                        $_SESSION['admin_logged_in'] = true;
                        $_SESSION['admin_id'] = $admin['admin_id'];
                        $_SESSION['admin_name'] = $admin['first_name'] . " " . $admin['last_name'];

                        header("Location: admin_dashboard.php");
                        exit;
                    } else {
                        $error = $generic_error;
                    }
                } else {
                    // Check customer account
                    $sql_cust = $conn->prepare("SELECT customer_id, first_name, last_name, password FROM customer WHERE email = ? OR contact_number = ?");
                    
                    if ($sql_cust) {
                        $sql_cust->bind_param("ss", $email_or_contact, $email_or_contact);
                        if ($sql_cust->execute()) {
                            $result_cust = $sql_cust->get_result();
                            if ($result_cust && $result_cust->num_rows > 0) {
                                $cust = $result_cust->fetch_assoc();

                                if (!empty($cust['password']) && password_verify($password_input, $cust['password'])) {
                                    session_regenerate_id(true);
                                    $_SESSION['logged_in'] = true;
                                    $_SESSION['role'] = 'Customer';
                                    $_SESSION['customer_id'] = $cust['customer_id'];
                                    $_SESSION['customer_name'] = $cust['first_name'] . " " . $cust['last_name'];

                                    header("Location: customer_dashboard.php");
                                    exit;
                                } else {
                                    $error = $generic_error;
                                }
                            } else {
                                $error = $generic_error;
                            }
                        } else {
                            $error = "System error.";
                        }
                        $sql_cust->close();
                    }
                }
            } else {
                $error = "System error.";
            }
            $sql_admin->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>DAMPA sa Tabing Dagat | Login</title>
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
  background: url("images/beach-view.jpg") no-repeat center center fixed;
  background-size: cover;
  font-family: "Poppins", sans-serif;
  color: #3b2a26;
}

main.site-content {
  flex: 1 0 auto;
  display: block;
}
.header {
  background: linear-gradient(90deg, #3b130b, #532315);
  color: #f6e9d6;
  padding: 14px 20px;
  font-size: 20px;
  font-weight: 700;
  text-align: center;
}
.hero {
  max-width: 760px;
  margin: 18px auto 0px;
  text-align: center;
  padding: 6px 12px;
}
.hero h2 {
  display:inline-flex;
  align-items:center;
  gap:10px;
  font-size: 34px;
  margin: 6px 0 6px;
  color: #2e1410;
  font-weight: 800;
}
.container-center {
  display:flex;
  justify-content:center;
  padding: 0 12px;
  margin-top: 0;
}
.login-wrap {
  width: 100%;
  max-width: 520px;
  padding-bottom: 40px;
}
.card {
  position: relative;
  background: linear-gradient(180deg, #f7eae0, #efe1cf);
  padding: 44px 28px 28px;
  border-radius: 14px;
  border: none;
  box-shadow: 0 18px 40px rgba(0,0,0,0.12);
  margin-top: 0;
}

.form-label {
  color: #6b341f;
  font-weight: 600;
}
.form-control {
  border-radius: 10px;
  border: 1px solid rgba(0,0,0,0.12);
  padding: 12px 14px;
  background: rgba(255,255,255,0.95);
}
.btn-primary {
  background: linear-gradient(180deg, #e5892e, #cc6f1d);
  border: none;
  border-radius: 10px;
  padding: 12px 20px;
  font-weight: 700;
  box-shadow: 0 6px 18px rgba(229,137,46,0.22);
  width: 50%;
  display: block;      
  margin: 0 auto; 
}

.alert-danger {
  background: #ffecec;
  border: none;
  color: #7a2a1f;
  font-weight: 600;
  border-radius: 8px;
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

footer {
  background: #3b130b;
  color: #e6d5c6;
  text-align: center;
  padding: 12px;
  font-size: 13px;
  flex-shrink: 0;
  width: 100%;
  box-shadow: 0 -2px 6px rgba(0,0,0,0.18);
}

@media (max-width: 600px) {
  .hero h2 { font-size: 26px; }
  .avatar { width: 100px; height: 100px; top:-50px; }
  .login-wrap { padding: 0 8px; }
  .card { padding: 36px 18px 18px; }
}
</style>
</head>
<body>
  <div class="header">DAMPA sa Tabing Dagat — User Login</div>

  <main class="site-content">
    <div class="hero">
      <h2><i class="fa-solid fa-user-shield"></i> User Login</h2>
    </div>
    <div class="container-center">
      <div class="login-wrap">
        <div class="card">
          <?php if (!empty($error)) : ?>
            <div class="alert alert-danger"><?= $error ?></div>
          <?php endif; ?>
          <form method="POST" style="margin-top:8px;">
            <div class="mb-3 mt-2">
              <label class="form-label">Email or Contact Number</label>
              <input type="text" name="email_or_contact" class="form-control" required value="<?= $old_input['email_or_contact'] ?>">
            </div>
            <div class="mb-3">
              <label class="form-label">Password</label>
              <input type="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary">Login</button>
          </form>
          <p class="login-foot">Don't have an account?
            <a href="signup.php" class="btn-link">Sign up here</a>
          </p>
        </div>
      </div>
    </div>
  </main>

  <footer>
   <?= date("Y") ?> DAMPA sa Tabing Dagat. All Rights Reserved.
  </footer>
</body>
</html>
