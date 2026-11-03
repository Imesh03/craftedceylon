<?php
session_start();
require_once 'config/db.php';
date_default_timezone_set('Asia/Colombo');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'includes/PHPMailer/Exception.php';
require 'includes/PHPMailer/PHPMailer.php';
require 'includes/PHPMailer/SMTP.php';

$error = '';

if (isset($_POST['register'])) {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    // Check if email already exists in Database as a verified user
    $chk = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND is_verified = 1");
    $chk->bind_param("s", $email);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0) {
        $error = "This email address is already registered and verified!";
    } else {
        // Generate OTP and Expiry Time
        $otp = rand(100000, 999999);
        $expiry = date("Y-m-d H:i:s", strtotime("+10 minutes"));
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);

        // Store registration data temporarily in Session (Not in DB yet)
        $_SESSION['temp_user'] = [
            'full_name' => $full_name,
            'email' => $email,
            'password_hash' => $hashed_password,
            'otp_code' => $otp,
            'otp_expiry' => $expiry
        ];

        // Send OTP Email
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'lasadaimesh@gmail.com'; 
            $mail->Password   = 'dvmx ibup fbgc iaes';   
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            $mail->setFrom('lasadaimesh@gmail.com', 'CraftedCeylon Admin');
            $mail->addAddress($email, $full_name);

            $mail->isHTML(true);
            $mail->Subject = 'CraftedCeylon - Your Verification OTP Code';
            $mail->Body = "
                <div style='font-family: Arial, sans-serif; padding: 20px; background-color: #f4f4f4;'>
                    <div style='max-width: 500px; margin: 0 auto; background: #ffffff; padding: 20px; border-radius: 8px;'>
                        <h2 style='color: #198754; text-align: center;'>CraftedCeylon</h2>
                        <p>Hello <b>{$full_name}</b>,</p>
                        <p>Please use the following One-Time Password (OTP) to complete your registration:</p>
                        <div style='text-align: center; margin: 20px 0;'>
                            <span style='font-size: 28px; font-weight: bold; letter-spacing: 5px; color: #198754; background: #e8f5e9; padding: 10px 20px; border-radius: 5px;'>{$otp}</span>
                        </div>
                        <p style='font-size: 12px; color: #777;'>This code will expire in 10 minutes.</p>
                    </div>
                </div>
            ";

            $mail->send();
            $_SESSION['verify_email'] = $email;
            header("Location: verify-otp.php");
            exit();
        } catch (Exception $e) {
            $error = "Failed to send verification email. Mail Error: " . $mail->ErrorInfo;
        }
    }
}

include 'includes/header.php';
?>

<!-- Creative Blurred Background & Dark Styling -->
<style>
    body {
        position: relative;
        background-color: #0d0d12;
        color: #ffffff;
        font-family: 'Plus Jakarta Sans', sans-serif;
    }
    body::before {
        content: "";
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-image: url('assets/images/cover.jpg');
        background-size: cover;
        background-position: center;
        filter: blur(20px) brightness(0.35);
        z-index: -1;
    }

    .dark-glass-card {
        background: rgba(26, 26, 40, 0.85);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 18px;
        box-shadow: 0 16px 40px rgba(0, 0, 0, 0.6);
        color: #ffffff;
    }

    .form-control {
        background-color: rgba(18, 18, 22, 0.8);
        border: 1px solid rgba(255, 255, 255, 0.1);
        color: #ffffff;
        border-radius: 10px;
    }
    .form-control:focus {
        background-color: rgba(18, 18, 22, 0.95);
        border-color: #198754;
        color: #ffffff;
        box-shadow: 0 0 0 0.25rem rgba(25, 135, 84, 0.25);
    }
</style>

<!-- HTML Form -->
<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="dark-glass-card p-4">
                <div class="text-center mb-4">
                    <div class="bg-success bg-opacity-25 text-success rounded-circle d-inline-flex align-items-center justify-content-center mb-2 shadow-sm" style="width: 60px; height: 60px; font-size: 1.5rem;">
                        <i class="bi bi-person-plus"></i>
                    </div>
                    <h3 class="fw-bold text-white">Create Account</h3>
                    <p class="text-secondary small">Join CraftedCeylon to explore authentic handmade products.</p>
                </div>

                <?php if(!empty($error)): ?>
                    <div class="alert alert-danger bg-danger text-white border-0 shadow-sm alert-dismissible fade show small" role="alert">
                        <i class="bi bi-exclamation-triangle me-1"></i> <?php echo $error; ?>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form action="register.php" method="POST">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-secondary">Full Name</label>
                        <input type="text" name="full_name" class="form-control" required placeholder="Enter your full name">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-secondary">Email Address</label>
                        <input type="email" name="email" class="form-control" required placeholder="name@example.com">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-secondary">Password</label>
                        <input type="password" name="password" class="form-control" required placeholder="Create a strong password">
                    </div>
                    <button type="submit" name="register" class="btn btn-success w-100 fw-bold py-2 mb-3 shadow-sm">
                        <i class="bi bi-person-check me-1"></i> Register & Send OTP
                    </button>
                    <div class="text-center small text-secondary">
                        Already have an account? <a href="login.php" class="text-success fw-semibold text-decoration-none">Log in here</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>