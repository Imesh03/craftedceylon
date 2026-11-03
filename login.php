<?php
session_start();
require_once 'config/db.php';

$error = '';

if (isset($_POST['login'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (!empty($email) && !empty($password)) {
        // Fetch user from database including account_status
        $stmt = $conn->prepare("SELECT user_id, full_name, password_hash, is_verified, account_status FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            // Check if account is blocked or suspended by Admin
            if ($user['account_status'] === 'Blocked' || $user['account_status'] === 'Suspended') {
                $error = "Your account has been " . strtolower($user['account_status']) . ". Please contact support.";
            } else {
                // Verify Password
                if (password_verify($password, $user['password_hash'])) {
                    
                    // Check if email is verified via OTP
                    if ($user['is_verified'] == 0) {
                        $_SESSION['verify_email'] = $email;
                        header("Location: verify-otp.php");
                        exit();
                    }

                    // Set Session variables
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['user_name'] = $user['full_name'];
                    $_SESSION['user_email'] = $email;

                    // Capture Login Details for Logging System
                    $user_id = $user['user_id'];
                    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
                    $device_info = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';
                    
                    // Simple Geo-IP Location Fetch (Using free ip-api.com service)
                    $location = 'Unknown';
                    @$geo_data = json_decode(file_get_contents("http://ip-api.com/json/{$ip_address}"), true);
                    if ($geo_data && $geo_data['status'] === 'success') {
                        $location = $geo_data['city'] . ', ' . $geo_data['country'];
                    }

                    // Insert Login Log into 'user_login_logs'
                    $log_stmt = $conn->prepare("INSERT INTO user_login_logs (user_id, ip_address, device_info, location, login_status) VALUES (?, ?, ?, ?, 'Success')");
                    $log_stmt->bind_param("isss", $user_id, $ip_address, $device_info, $location);
                    $log_stmt->execute();
                    
                    // Save log_id in session for logout tracking later if needed
                    $_SESSION['login_log_id'] = $conn->insert_id;

                    // Insert Activity Log into 'user_activities'
                    $act_stmt = $conn->prepare("INSERT INTO user_activities (user_id, action_name, page_url) VALUES (?, 'User Logged In', 'login.php')");
                    $act_stmt->bind_param("i", $user_id);
                    $act_stmt->execute();

                    // Redirect to previous requested page or account
                    if (isset($_SESSION['redirect_after_login'])) {
                        $redirect_url = $_SESSION['redirect_after_login'];
                        unset($_SESSION['redirect_after_login']);
                        header("Location: $redirect_url");
                    } else {
                        header("Location: account.php");
                    }
                    exit();
                } else {
                    $error = "Invalid password! Please try again.";
                }
            }
        } else {
            $error = "No account found with this email address.";
        }
    } else {
        $error = "Please fill in all fields.";
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

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="dark-glass-card p-4">
                <div class="text-center mb-4">
                    <div class="bg-success bg-opacity-25 text-success rounded-circle d-inline-flex align-items-center justify-content-center mb-2 shadow-sm" style="width: 60px; height: 60px; font-size: 1.5rem;">
                        <i class="bi bi-box-arrow-in-right"></i>
                    </div>
                    <h3 class="fw-bold text-white">Welcome Back!</h3>
                    <p class="text-secondary small">Log in to your CraftedCeylon account to continue shopping.</p>
                </div>

                <?php if(!empty($error)): ?>
                    <div class="alert alert-danger bg-danger text-white border-0 shadow-sm alert-dismissible fade show small" role="alert">
                        <i class="bi bi-exclamation-triangle me-1"></i> <?php echo $error; ?>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if(isset($_GET['msg']) && $_GET['msg'] == 'please_login'): ?>
                    <div class="alert alert-warning bg-warning bg-opacity-25 text-warning border border-warning alert-dismissible fade show small" role="alert">
                        <i class="bi bi-info-circle me-1"></i> Please log in to proceed with your checkout.
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if(isset($_GET['msg']) && $_GET['msg'] == 'password_reset_success'): ?>
                    <div class="alert alert-success bg-success bg-opacity-25 text-success border border-success alert-dismissible fade show small" role="alert">
                        <i class="bi bi-check-circle me-1"></i> Your password has been reset successfully! Please log in.
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form action="login.php" method="POST">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-secondary">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text border-end-0"><i class="bi bi-envelope text-secondary"></i></span>
                            <input type="email" name="email" class="form-control border-start-0" required placeholder="name@example.com">
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

                    <!-- Forgot Password Link -->
                    <div class="d-flex justify-content-end mb-4">
                        <a href="forgot-password.php" class="text-success small text-decoration-none fw-semibold">
                            <i class="bi bi-key me-1"></i> Forgot Password?
                        </a>
                    </div>

                    <button type="submit" name="login" class="btn btn-success w-100 fw-bold py-2 mb-3 shadow-sm">
                        <i class="bi bi-box-arrow-in-right me-1"></i> Log In
                    </button>

                    <div class="text-center small text-secondary">
                        Don't have an account? <a href="register.php" class="text-success fw-semibold text-decoration-none">Register here</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Password View/Hide JavaScript -->
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

<?php include 'includes/footer.php'; ?>