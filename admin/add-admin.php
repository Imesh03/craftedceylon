<?php
session_start();
require_once '../config/db.php';

// 1. Admin කෙනෙකු ලෙස ලොග් වී ඇද්දැයි සහ ඔහු "Super Admin" කෙනෙක් දැයි දැඩිව පරීක්ෂා කිරීම
if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    header("Location: login.php");
    exit();
}

$current_admin_id = $_SESSION['admin_id'];
$role_check = $conn->prepare("SELECT role FROM admins WHERE admin_id = ?");
$role_check->bind_param("i", $current_admin_id);
$role_check->execute();
$role_result = $role_check->get_result()->fetch_assoc();

if (!$role_result || $role_result['role'] !== 'Super Admin') {
    echo "<script>alert('Access Denied! Only Super Admins can add new administrators.'); window.location='dashboard.php';</script>";
    exit();
}

$success_msg = '';
$error_msg = '';

// Handle Form Submission for Adding New Admin
if (isset($_POST['add_admin'])) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $role = $_POST['role']; // 'Super Admin' හෝ 'Admin'

    if (!empty($username) && !empty($email) && !empty($password) && !empty($role)) {
        
        // 1. Check if email already exists
        $check_email = $conn->prepare("SELECT admin_id FROM admins WHERE email = ?");
        $check_email->bind_param("s", $email);
        $check_email->execute();
        $check_email->store_result();

        if ($check_email->num_rows > 0) {
            $error_msg = "This email address is already registered as an administrator.";
        } else {
            // 2. Hash the password securely
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $account_status = 'Active';

            // 3. Insert new admin into database
            $insert_stmt = $conn->prepare("INSERT INTO admins (username, email, password_hash, role, account_status) VALUES (?, ?, ?, ?, ?)");
            $insert_stmt->bind_param("sssss", $username, $email, $password_hash, $role, $account_status);

            if ($insert_stmt->execute()) {
                $success_msg = "New administrator added successfully!";
                // Form fields clear කිරීම සඳහා variables හිස් කළ හැක
                $username = $email = $password = '';
            } else {
                $error_msg = "Database error: " . $conn->error;
            }
        }
        $check_email->close();
    } else {
        $error_msg = "Please fill in all required fields.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Admin - CraftedCeylon</title>
    <!-- Google Font -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        :root {
            --bg-dark: #0d0d12;
            --text-main: #ffffff;
            --text-sub: #a0aec0;
            --border-color: rgba(255, 255, 255, 0.08);
        }

        body {
            background-color: var(--bg-dark);
            background-image: linear-gradient(135deg, rgba(13, 13, 18, 0.98), rgba(15, 25, 20, 0.95)), 
                              radial-gradient(circle at 15% 15%, rgba(25, 135, 84, 0.12) 0%, transparent 45%),
                              radial-gradient(circle at 85% 85%, rgba(13, 202, 240, 0.06) 0%, transparent 45%);
            background-attachment: fixed;
            color: var(--text-main);
            font-family: 'Plus Jakarta Sans', sans-serif;
            min-height: 100vh;
        }

        /* Seamless Form Glassmorphism Wrapper */
        .admin-form-container {
            background: rgba(18, 18, 26, 0.75);
            backdrop-filter: blur(25px);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.6);
            padding: 40px;
        }

        .form-control, .form-select {
            background-color: rgba(20, 20, 28, 0.9);
            border: 1px solid rgba(255, 255, 255, 0.12);
            color: #ffffff;
            border-radius: 12px;
            padding: 12px 16px;
        }
        .form-control:focus, .form-select:focus {
            background-color: rgba(20, 20, 28, 1);
            border-color: #198754;
            color: #ffffff;
            box-shadow: 0 0 0 0.25rem rgba(25, 135, 84, 0.25);
        }

        .input-group-text {
            background-color: rgba(25, 25, 35, 0.9);
            border: 1px solid rgba(255, 255, 255, 0.12);
            color: #a0aec0;
            border-radius: 12px 0 0 12px;
            padding: 0 16px;
        }
        
        .form-label {
            font-weight: 600;
            color: var(--text-sub);
            font-size: 0.85rem;
            margin-bottom: 6px;
        }
    </style>
</head>
<body>

    <!-- Top Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-transparent border-bottom border-secondary border-opacity-10 py-3">
        <div class="container-fluid px-4">
            <a class="navbar-brand fw-bold text-success d-flex align-items-center gap-2" href="dashboard.php">
                <i class="bi bi-shield-shaded fs-4"></i> CraftedCeylon Admin
            </a>
            <div class="navbar-nav ms-auto align-items-center">
                <span class="text-secondary me-3 small">Logged in as: <b class="text-white"><?php echo htmlspecialchars($_SESSION['admin_email'] ?? 'Super Admin'); ?></b> (<span class="text-warning">Super Admin</span>)</span>
                <a href="admin-logout.php" class="btn btn-outline-danger btn-sm rounded-pill px-3">Logout</a>
            </div>
        </div>
    </nav>

    <!-- Main Content Container -->
    <div class="container my-5" style="max-width: 650px;">
        
        <div class="mb-4">
            <h2 class="fw-bold text-white mb-1"><i class="bi bi-person-plus-fill text-success me-2"></i> Add New Administrator</h2>
            <p class="text-secondary small mb-0">Create a new admin or super admin account with secure system privileges.</p>
        </div>

        <?php if (!empty($success_msg)): ?>
            <div class="alert alert-success bg-success bg-opacity-25 text-success border border-success alert-dismissible fade show small rounded-3" role="alert">
                <i class="bi bi-check-circle me-1"></i> <?php echo $success_msg; ?>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-danger bg-danger text-white border-0 shadow-sm alert-dismissible fade show small rounded-3" role="alert">
                <i class="bi bi-exclamation-triangle me-1"></i> <?php echo $error_msg; ?>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Form Card Wrapper -->
        <div class="admin-form-container">
            <form action="add-admin.php" method="POST">
                
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input type="text" name="username" class="form-control" required placeholder="Enter admin username" value="<?php echo htmlspecialchars($username ?? ''); ?>">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Email Address (Login ID)</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                        <input type="email" name="email" class="form-control" required placeholder="admin@craftedceylon.com" value="<?php echo htmlspecialchars($email ?? ''); ?>">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Secure Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" name="password" id="adminPassword" class="form-control border-end-0" required placeholder="Enter strong password">
                        <button class="btn btn-outline-secondary text-secondary border-start-0" type="button" id="togglePassword" style="background-color: rgba(20, 20, 28, 0.9); border-color: rgba(255, 255, 255, 0.12); border-radius: 0 12px 12px 0;">
                            <i class="bi bi-eye" id="eyeIcon"></i>
                        </button>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label">Assign System Role</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-shield-check"></i></span>
                        <select name="role" class="form-select" required>
                            <option value="Admin">Normal Admin (Standard Permissions)</option>
                            <option value="Super Admin">Super Admin (Full Platform Control)</option>
                        </select>
                    </div>
                </div>

                <div class="d-flex gap-3 pt-2">
                    <a href="manage-admins.php" class="btn btn-outline-secondary w-50 py-2 fw-bold rounded-3">Cancel</a>
                    <button type="submit" name="add_admin" class="btn btn-success w-50 py-2 fw-bold shadow-sm rounded-3">
                        <i class="bi bi-check-circle me-1"></i> Create Admin
                    </button>
                </div>
            </form>
        </div>

        <div class="mt-4">
            <a href="manage-admins.php" class="text-decoration-none text-success fw-bold d-inline-flex align-items-center gap-1">
                <i class="bi bi-arrow-left"></i> Back to Manage Admins
            </a>
        </div>
    </div>

    <!-- Password View/Hide JavaScript -->
    <script>
    document.getElementById('togglePassword')?.addEventListener('click', function () {
        const passwordInput = document.getElementById('adminPassword');
        const eyeIcon = document.getElementById('eyeIcon');
        
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

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>