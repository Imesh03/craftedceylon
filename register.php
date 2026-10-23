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

<!-- HTML Form -->
<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 p-4">
                <div class="text-center mb-4">
                    <div class="bg-success bg-opacity-10 text-success rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width: 60px; height: 60px; font-size: 1.5rem;">
                        <i class="bi bi-person-plus"></i>
                    </div>
                    <h3 class="fw-bold">Create Account</h3>
                    <p class="text-muted small">Join CraftedCeylon to explore authentic handmade products.</p>
                </div>

                <?php if(!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show small" role="alert">
                        <i class="bi bi-exclamation-triangle me-1"></i> <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form action="register.php" method="POST">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Full Name</label>
                        <input type="text" name="full_name" class="form-control" required placeholder="Enter your full name">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Email Address</label>
                        <input type="email" name="email" class="form-control" required placeholder="name@example.com">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Password</label>
                        <input type="password" name="password" class="form-control" required placeholder="Create a strong password">
                    </div>
                    <button type="submit" name="register" class="btn btn-success w-100 fw-bold py-2 mb-3">
                        <i class="bi bi-person-check me-1"></i> Register & Send OTP
                    </button>
                    <div class="text-center small text-muted">
                        Already have an account? <a href="login.php" class="text-success fw-semibold text-decoration-none">Log in here</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>