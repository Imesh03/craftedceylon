<?php
session_start();
require_once '../config/db.php';
date_default_timezone_set('Asia/Colombo');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../includes/PHPMailer/Exception.php';
require '../includes/PHPMailer/PHPMailer.php';
require '../includes/PHPMailer/SMTP.php';

$error = '';

if (isset($_POST['login'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (!empty($email) && !empty($password)) {
        $stmt = $conn->prepare("SELECT admin_id, username, email, password_hash, account_status, role FROM admins WHERE email = ?");
        
        if ($stmt === false) {
            $error = "Database prepare error: " . $conn->error;
        } else {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $admin = $result->fetch_assoc();

                if ($admin['account_status'] !== 'Active') {
                    $status_msg = strtolower($admin['account_status']);
                    $error = "Your admin account has been {$status_msg}. Please contact the Super Administrator.";
                } else {
                    if (password_verify($password, $admin['password_hash'])) {
                        
                        $_SESSION['admin_logged'] = true;
                        $_SESSION['admin_id'] = $admin['admin_id'];
                        $_SESSION['admin_username'] = $admin['username'];
                        $_SESSION['admin_email'] = $email;
                        $_SESSION['admin_role'] = $admin['role'];

                        // --- LOGIN DETAILS & ACTIVITY LOGGING ---
                        $login_time = date("Y-m-d H:i:s");
                        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
                        
                        // Insert Login Activity into admin_activity_logs table
                        $log_stmt = $conn->prepare("INSERT INTO admin_activity_logs (admin_id, activity_description, login_time, ip_address) VALUES (?, 'Logged In', ?, ?)");
                        $log_stmt->bind_param("iss", $admin['admin_id'], $login_time, $ip_address);
                        $log_stmt->execute();
                        
                        // Store log_id in session to update logout time later
                        $_SESSION['admin_log_id'] = $conn->insert_id;

                        $location = 'Unknown';
                        @$geo_data = json_decode(file_get_contents("http://ip-api.com/json/{$ip_address}"), true);
                        if ($geo_data && $geo_data['status'] === 'success') {
                            $location = $geo_data['city'] . ', ' . $geo_data['country'];
                        }

                        // --- SEND EMAIL ALERTS VIA PHPMailer ---
                        $mail = new PHPMailer(true);
                        try {
                            $mail->isSMTP();
                            $mail->Host       = 'smtp.gmail.com';
                            $mail->SMTPAuth   = true;
                            $mail->Username   = 'lasadaimesh@gmail.com'; 
                            $mail->Password   = 'dvmx ibup fbgc iaes';     
                            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                            $mail->Port       = 587;

                            $mail->setFrom('lasadaimesh@gmail.com', 'CraftedCeylon Security');
                            $mail->addAddress($admin['email'], $admin['username']);

                            $super_admins_query = $conn->query("SELECT email, username FROM admins WHERE role = 'Super Admin'");
                            if ($super_admins_query) {
                                while ($sa = $super_admins_query->fetch_assoc()) {
                                    if ($sa['email'] !== $admin['email']) {
                                        $mail->addBCC($sa['email'], $sa['username']);
                                    }
                                }
                            }

                            $mail->isHTML(true);
                            $mail->Subject = 'Security Alert: Admin Login Notification - CraftedCeylon';
                            $mail->Body = "
                                <div style='font-family: Arial, sans-serif; padding: 20px; background-color: #f4f4f4;'>
                                    <div style='max-width: 500px; margin: 0 auto; background: #ffffff; padding: 20px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1);'>
                                        <h2 style='color: #198754; text-align: center;'>CraftedCeylon Security</h2>
                                        <p>Hello <b>Management Team</b>,</p>
                                        <p>Admin account <b>" . htmlspecialchars($admin['username']) . " (" . htmlspecialchars($admin['email']) . ")</b> has successfully logged into the system.</p>
                                        <ul style='color: #333; line-height: 1.6;'>
                                            <li><b>Role:</b> " . htmlspecialchars($admin['role']) . "</li>
                                            <li><b>Time:</b> {$login_time}</li>
                                            <li><b>IP Address:</b> {$ip_address}</li>
                                            <li><b>Location:</b> {$location}</li>
                                        </ul>
                                        <p style='color: #d9534f; font-size: 13px; margin-top: 20px;'>If this login was unauthorized, please take immediate action to secure the platform.</p>
                                    </div>
                                </div>
                            ";

                            $mail->send();
                        } catch (Exception $e) {
                            // Suppress email exceptions to prevent login blocking
                        }

                        header("Location: dashboard.php");
                        exit();
                    } else {
                        $error = "Invalid password! Please try again.";
                    }
                }
            } else {
                $error = "No admin account found with this email address.";
            }
            $stmt->close();
        }
    } else {
        $error = "Please fill in all fields.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - CraftedCeylon Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        body {
            position: relative;
            background-color: #0d0d12;
            color: #ffffff;
            font-family: 'Plus Jakarta Sans', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        body::before {
            content: "";
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background-image: linear-gradient(135deg, rgba(13, 13, 18, 0.95), rgba(20, 30, 25, 0.9));
            background-size: cover; background-position: center;
            z-index: -1;
        }
        .dark-glass-card {
            background: rgba(26, 26, 40, 0.85);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 18px;
            box-shadow: 0 16px 40px rgba(0, 0, 0, 0.6);
            color: #ffffff;
        }
        .form-control {
            background-color: rgba(18, 18, 22, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #ffffff;
        }
        .form-control:focus {
            background-color: rgba(18, 18, 22, 0.95);
            border-color: #198754;
            color: #ffffff;
            box-shadow: 0 0 0 0.25rem rgba(25, 135, 84, 0.25);
        }
        .input-group-text {
            background-color: rgba(18, 18, 22, 0.9) !important;
            border-color: rgba(255, 255, 255, 0.1) !important;
            color: #adb5bd !important;
        }
    </style>
</head>
<body>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="dark-glass-card p-4">
                <div class="text-center mb-4">
                    <div class="bg-success bg-opacity-25 text-success rounded-circle d-inline-flex align-items-center justify-content-center mb-2 shadow-sm" style="width: 60px; height: 60px; font-size: 1.5rem;">
                        <i class="bi bi-shield-lock-fill"></i>
                    </div>
                    <h3 class="fw-bold text-white">Admin Portal</h3>
                    <p class="text-secondary small">Sign in to manage CraftedCeylon system.</p>
                </div>

                <?php if(!empty($error)): ?>
                    <div class="alert alert-danger bg-danger text-white border-0 shadow-sm alert-dismissible fade show small" role="alert">
                        <i class="bi bi-exclamation-triangle me-1"></i> <?php echo $error; ?>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form action="login.php" method="POST">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-secondary">Admin Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text border-end-0"><i class="bi bi-envelope text-secondary"></i></span>
                            <input type="email" name="email" class="form-control border-start-0" required placeholder="admin@craftedceylon.com">
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-semibold small text-secondary">Password</label>
                        <div class="input-group">
                            <span class="input-group-text border-end-0"><i class="bi bi-lock text-secondary"></i></span>
                            <input type="password" name="password" id="loginPassword" class="form-control border-start-0 border-end-0" required placeholder="Enter your password">
                            <button class="btn btn-outline-secondary border-start-0 text-secondary" type="button" id="toggleLoginPassword" style="background-color: rgba(18, 18, 22, 0.9); border-color: rgba(255, 255, 255, 0.1);">
                                <i class="bi bi-eye" id="loginEyeIcon"></i>
                            </button>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end mb-4">
                        <a href="forgot-password.php" class="text-success small text-decoration-none fw-semibold">
                            <i class="bi bi-key me-1"></i> Forgot Password?
                        </a>
                    </div>

                    <button type="submit" name="login" class="btn btn-success w-100 fw-bold py-2 mb-3 shadow-sm">
                        <i class="bi bi-box-arrow-in-right me-1"></i> Secure Admin Log In
                    </button>

                    <div class="text-center small text-secondary border-top pt-3">
                        <a href="../index.php" class="text-muted text-decoration-none"><i class="bi bi-house-door me-1"></i> Return to Main Website</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('toggleLoginPassword')?.addEventListener('click', function () {
    const passwordInput = document.getElementById('loginPassword');
    const eyeIcon = document.getElementById('loginEyeIcon');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        eyeIcon.classList.remove('bi-eye');
        eyeIcon.classList.add('bi-eye-slash');
    } else {
        passwordInput.type = 'password';
        eyeIcon.classList.remove('bi-eye-slash');
        eyeIcon.classList.add('bi-eye');
    }
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>