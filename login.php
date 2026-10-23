<?php
session_start();
require_once 'config/db.php';

$error = '';

if (isset($_POST['login'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (!empty($email) && !empty($password)) {
        // Fetch user from database
        $stmt = $conn->prepare("SELECT user_id, full_name, password_hash, is_verified FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

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
        } else {
            $error = "No account found with this email address.";
        }
    } else {
        $error = "Please fill in all fields.";
    }
}

include 'includes/header.php';
?>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 p-4">
                <div class="text-center mb-4">
                    <div class="bg-success bg-opacity-10 text-success rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width: 60px; height: 60px; font-size: 1.5rem;">
                        <i class="bi bi-box-arrow-in-right"></i>
                    </div>
                    <h3 class="fw-bold">Welcome Back!</h3>
                    <p class="text-muted small">Log in to your CraftedCeylon account to continue shopping.</p>
                </div>

                <?php if(!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show small" role="alert">
                        <i class="bi bi-exclamation-triangle me-1"></i> <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if(isset($_GET['msg']) && $_GET['msg'] == 'please_login'): ?>
                    <div class="alert alert-warning alert-dismissible fade show small" role="alert">
                        <i class="bi bi-info-circle me-1"></i> Please log in to proceed with your checkout.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if(isset($_GET['msg']) && $_GET['msg'] == 'password_reset_success'): ?>
                    <div class="alert alert-success alert-dismissible fade show small" role="alert">
                        <i class="bi bi-check-circle me-1"></i> Your password has been reset successfully! Please log in.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form action="login.php" method="POST">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i class="bi bi-envelope text-muted"></i></span>
                            <input type="email" name="email" class="form-control border-start-0" required placeholder="name@example.com">
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-semibold small">Password</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i class="bi bi-lock text-muted"></i></span>
                            <input type="password" name="password" id="loginPassword" class="form-control border-start-0 border-end-0" required placeholder="Enter your password">
                            <button class="btn btn-outline-secondary bg-light border-start-0 text-muted" type="button" id="toggleLoginPassword">
                                <i class="bi bi-eye" id="loginEyeIcon"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Creative Forgot Password Link Placed at Bottom Right of Password Field -->
                    <div class="d-flex justify-content-end mb-4">
                        <a href="forgot-password.php" class="text-success small text-decoration-none fw-semibold">
                            <i class="bi bi-key me-1"></i> Forgot Password?
                        </a>
                    </div>

                    <button type="submit" name="login" class="btn btn-success w-100 fw-bold py-2 mb-3">
                        <i class="bi bi-box-arrow-in-right me-1"></i> Log In
                    </button>

                    <div class="text-center small text-muted">
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