<?php
session_start();
require_once '../config/db.php';

$error = '';
$success = '';

// Handle Admin Login
if (isset($_POST['admin_login'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    $stmt = $conn->prepare("SELECT * FROM admins WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $row = $result->fetch_assoc()) {
        if (password_verify($password, $row['password_hash'])) {
            $_SESSION['admin_logged'] = true;
            $_SESSION['admin_id'] = $row['admin_id'];
            $_SESSION['admin_username'] = $row['username'];
            header("Location: dashboard.php");
            exit();
        } else {
            $error = "Incorrect password! Please try again.";
        }
    } else {
        $error = "Admin username not found!";
    }
}

// Handle Password Reset Request (Simulation / Secure Reset)
if (isset($_POST['reset_password'])) {
    $reset_email = trim($_POST['reset_email']);

    $stmt = $conn->prepare("SELECT * FROM admins WHERE email = ?");
    $stmt->bind_param("s", $reset_email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $row = $result->fetch_assoc()) {
        // Here you can integrate mail functionality or provide a secure reset token link.
        // For project demonstration, we show a success verification message.
        $success = "Password reset instructions have been sent to your admin email address.";
    } else {
        $error = "This email address is not registered as an administrator.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Portal - CraftedCeylon</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #198754, #212529);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .admin-card {
            border-radius: 15px;
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95);
        }
    </style>
</head>
<body>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="card admin-card shadow-lg p-4 border-0">
                    <div class="text-center mb-4">
                        <i class="bi bi-shield-lock-fill text-success display-4"></i>
                        <h3 class="fw-bold text-dark mt-2">Admin Portal</h3>
                        <p class="text-muted small">CraftedCeylon Management System</p>
                    </div>

                    <?php if(!empty($error)): ?>
                        <div class="alert alert-danger py-2 small"><?php echo $error; ?></div>
                    <?php endif; ?>

                    <?php if(!empty($success)): ?>
                        <div class="alert alert-success py-2 small"><?php echo $success; ?></div>
                    <?php endif; ?>

                    <!-- Login Form -->
                    <form action="login.php" method="POST" id="loginForm">
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-secondary">Username</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="bi bi-person-fill"></i></span>
                                <input type="text" name="username" class="form-control" required placeholder="Enter admin username">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-secondary">Password</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="bi bi-key-fill"></i></span>
                                <input type="password" name="password" class="form-control" required placeholder="Enter password">
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="rememberMe">
                                <label class="form-check-label small text-muted" for="rememberMe">Remember me</label>
                            </div>
                            <a href="#" class="small text-success text-decoration-none fw-bold" onclick="toggleForms(event)">Forgot Password?</a>
                        </div>

                        <button type="submit" name="admin_login" class="btn btn-success w-100 py-2 fw-bold shadow-sm">
                            <i class="bi bi-box-arrow-in-right"></i> Secure Login
                        </button>
                    </form>

                    <!-- Forgot / Reset Password Form (Hidden by default) -->
                    <form action="login.php" method="POST" id="resetForm" style="display: none;">
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-secondary">Admin Recovery Email</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="bi bi-envelope-fill"></i></span>
                                <input type="email" name="reset_email" class="form-control" required placeholder="Enter registered admin email">
                            </div>
                            <div class="form-text small">We will send password recovery instructions to this email.</div>
                        </div>

                        <button type="submit" name="reset_password" class="btn btn-dark w-100 py-2 fw-bold mb-3 shadow-sm">
                            <i class="bi bi-arrow-clockwise"></i> Reset Password
                        </button>

                        <div class="text-center">
                            <a href="#" class="small text-success text-decoration-none fw-bold" onclick="toggleForms(event)"><i class="bi bi-arrow-left"></i> Back to Login</a>
                        </div>
                    </form>

                    <div class="text-center mt-4 border-top pt-3">
                        <a href="../index.php" class="text-muted small text-decoration-none"><i class="bi bi-house-door"></i> Return to Main Website</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Toggle JavaScript for Forms -->
    <script>
        function toggleForms(event) {
            event.preventDefault();
            var loginForm = document.getElementById('loginForm');
            var resetForm = document.getElementById('resetForm');
            
            if (loginForm.style.display === 'none') {
                loginForm.style.display = 'block';
                resetForm.style.display = 'none';
            } else {
                loginForm.style.display = 'none';
                resetForm.style.display = 'block';
            }
        }
    </script>
</body>
</html>