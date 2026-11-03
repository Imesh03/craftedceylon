<?php
session_start();
require_once '../config/db.php';

// PHPMailer inclusions for sending report via email
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../includes/PHPMailer/Exception.php';
require '../includes/PHPMailer/PHPMailer.php';
require '../includes/PHPMailer/SMTP.php';

// 1. Admin කෙනෙකු ලෙස ලොග් වී ඇද්දැයි සහ ඔහු "Super Admin" කෙනෙක් දැයි පරීක්ෂා කිරීම
if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    header("Location: login.php");
    exit();
}

// HANDLE AJAX EMAIL REQUEST FOR PDF REPORT
if (isset($_POST['send_report_email'])) {
    $response = ['status' => 'error', 'message' => ''];
    $admin_email = trim($_POST['admin_email']);
    $admin_name = trim($_POST['admin_name']);
    $pdf_base64 = $_POST['pdf_data']; // Base64 encoded PDF from jsPDF

    if (!empty($admin_email) && !empty($pdf_base64)) {
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
            $mail->addAddress($admin_email, $admin_name);

            // Properly strip data URI scheme and decode base64 binary safely
            if (strpos($pdf_base64, ',') !== false) {
                $pdf_base64 = explode(',', $pdf_base64)[1];
            }
            $pdf_binary = base64_decode($pdf_base64);

            // Attach string data as a valid PDF file
            $mail->addStringAttachment($pdf_binary, 'Admin_Activity_Report.pdf', 'base64', 'application/pdf');

            $mail->isHTML(true);
            $mail->Subject = 'Your Activity Report - CraftedCeylon';
            $mail->Body = "
                <div style='font-family: Arial, sans-serif; padding: 20px; background-color: #f4f4f4;'>
                    <div style='max-width: 500px; margin: 0 auto; background: #ffffff; padding: 20px; border-radius: 8px;'>
                        <h2 style='color: #198754; text-align: center;'>CraftedCeylon Admin Portal</h2>
                        <p>Hello <b>" . htmlspecialchars($admin_name) . "</b>,</p>
                        <p>Attached is your requested admin activity report generated from the CraftedCeylon management system.</p>
                        <p style='color: #666; font-size: 13px; margin-top: 20px;'>This is an automated system notification.</p>
                    </div>
                </div>
            ";

            $mail->send();
            $response['status'] = 'success';
            $response['message'] = 'Report successfully emailed to ' . $admin_email;
        } catch (Exception $e) {
            $response['message'] = 'Mailer Error: ' . $mail->ErrorInfo;
        }
    } else {
        $response['message'] = 'Invalid email or PDF data.';
    }
    echo json_encode($response);
    exit();
}

$current_admin_id = $_SESSION['admin_id'];
$role_check = $conn->prepare("SELECT role FROM admins WHERE admin_id = ?");
$role_check->bind_param("i", $current_admin_id);
$role_check->execute();
$role_result = $role_check->get_result()->fetch_assoc();

if (!$role_result || $role_result['role'] !== 'Super Admin') {
    echo "<script>alert('Access Denied! Only Super Admins can manage administrators.'); window.location='dashboard.php';</script>";
    exit();
}

$success_msg = '';
$error_msg = '';

// Check if Activity Logs table exists, if not create it dynamically
$conn->query("CREATE TABLE IF NOT EXISTS admin_activity_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT,
    activity_description VARCHAR(255),
    login_time DATETIME DEFAULT CURRENT_TIMESTAMP,
    logout_time DATETIME DEFAULT NULL,
    ip_address VARCHAR(45)
)");

// Clear All Activities Action
if (isset($_POST['clear_activities'])) {
    $target_clear_admin = isset($_POST['clear_admin_id']) ? intval($_POST['clear_admin_id']) : 0;
    if ($target_clear_admin > 0) {
        $c_stmt = $conn->prepare("DELETE FROM admin_activity_logs WHERE admin_id = ?");
        $c_stmt->bind_param("i", $target_clear_admin);
        $c_stmt->execute();
    } else {
        $conn->query("DELETE FROM admin_activity_logs");
    }
    $success_msg = "Activity logs cleared successfully!";
}

// 1. Update Role
if (isset($_POST['update_role'])) {
    $target_admin_id = intval($_POST['admin_id']);
    $new_role = trim($_POST['new_role']);

    if ($new_role === 'Super Admin' || $new_role === 'Admin') {
        if ($target_admin_id == $current_admin_id && $new_role !== 'Super Admin') {
            $error_msg = "You cannot demote your own Super Admin account!";
        } else {
            $stmt = $conn->prepare("UPDATE admins SET role = ? WHERE admin_id = ?");
            $stmt->bind_param("si", $new_role, $target_admin_id);
            if ($stmt->execute()) {
                $success_msg = "Admin role updated successfully!";
            } else {
                $error_msg = "Failed to update role.";
            }
        }
    }
}

// 2. Edit Admin Details
if (isset($_POST['edit_admin'])) {
    $target_admin_id = intval($_POST['admin_id']);
    $new_username = trim($_POST['username']);
    $new_email = trim($_POST['email']);

    if (!empty($new_username) && !empty($new_email)) {
        $stmt = $conn->prepare("UPDATE admins SET username = ?, email = ? WHERE admin_id = ?");
        $stmt->bind_param("ssi", $new_username, $new_email, $target_admin_id);
        if ($stmt->execute()) {
            $success_msg = "Admin details updated successfully!";
        } else {
            $error_msg = "Failed to update admin details.";
        }
    } else {
        $error_msg = "All fields are required for editing.";
    }
}

// 3. Toggle Status (Block / Unblock)
if (isset($_GET['action']) && $_GET['action'] == 'toggle_status' && isset($_GET['id'])) {
    $target_admin_id = intval($_GET['id']);
    
    if ($target_admin_id == $current_admin_id) {
        $error_msg = "You cannot block your own active account!";
    } else {
        $status_q = $conn->prepare("SELECT account_status FROM admins WHERE admin_id = ?");
        $status_q->bind_param("i", $target_admin_id);
        $status_q->execute();
        $res = $status_q->get_result()->fetch_assoc();

        if ($res) {
            $new_status = ($res['account_status'] === 'Blocked') ? 'Active' : 'Blocked';
            $update_s = $conn->prepare("UPDATE admins SET account_status = ? WHERE admin_id = ?");
            $update_s->bind_param("si", $new_status, $target_admin_id);
            if ($update_s->execute()) {
                $success_msg = "Admin status changed to {$new_status} successfully!";
            } else {
                $error_msg = "Failed to change account status.";
            }
        }
    }
}

// 4. Delete Admin
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $target_admin_id = intval($_GET['id']);

    if ($target_admin_id == $current_admin_id) {
        $error_msg = "You cannot delete your own account!";
    } else {
        $del_stmt = $conn->prepare("DELETE FROM admins WHERE admin_id = ?");
        $del_stmt->bind_param("i", $target_admin_id);
        if ($del_stmt->execute()) {
            $success_msg = "Admin deleted successfully!";
        } else {
            $error_msg = "Failed to delete admin.";
        }
    }
}

// Fetch all admins
$query = "SELECT admin_id, username, email, role, account_status, created_at FROM admins ORDER BY admin_id DESC";
$result = $conn->query($query);
$admins_list = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $admins_list[] = $row;
    }
}

$active_modal_id = isset($_GET['open_modal']) ? intval($_GET['open_modal']) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Admins & Activity Report - CraftedCeylon</title>
    <!-- Google Font -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <!-- jsPDF for PDF Report Generation -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
    
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

        .admin-table-container {
            background: rgba(18, 18, 26, 0.75);
            backdrop-filter: blur(25px);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.6);
        }

        .table {
            color: #ffffff !important;
            margin-bottom: 0;
            vertical-align: middle;
            background-color: transparent !important;
        }
        .table th {
            background-color: rgba(13, 13, 18, 0.95);
            color: #8fa3ad;
            font-weight: 600;
            letter-spacing: 0.5px;
            border-bottom: 1px solid var(--border-color);
            padding: 16px 20px;
        }
        .table td {
            padding: 16px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.04);
            color: #ffffff !important;
            background-color: transparent !important;
        }
        .table-hover tbody tr:hover {
            background-color: rgba(255, 255, 255, 0.04) !important;
        }

        .admin-link {
            color: #0dcaf0;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .admin-link:hover {
            color: #31d2f2;
            text-decoration: underline;
        }

        .search-box {
            background: rgba(18, 18, 26, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            color: #ffffff;
            padding: 10px 16px 10px 42px;
            transition: all 0.3s ease;
        }
        .search-box:focus {
            background: rgba(18, 18, 26, 0.95);
            border-color: #198754;
            color: #ffffff;
            box-shadow: 0 0 15px rgba(25, 135, 84, 0.25);
        }
        .search-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #a0aec0;
        }

        .form-select-sm, .form-control-sm {
            background-color: rgba(25, 25, 35, 0.9);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #ffffff;
            border-radius: 8px;
        }

        .modal-content {
            background: #13131c;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.9);
            color: #ffffff;
        }
        .modal-header, .modal-footer {
            border-color: rgba(255, 255, 255, 0.08);
        }
        .form-control {
            background-color: rgba(20, 20, 28, 0.9);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #ffffff;
            border-radius: 10px;
        }
        .form-control:focus {
            background-color: rgba(20, 20, 28, 1);
            border-color: #198754;
            color: #ffffff;
            box-shadow: 0 0 0 0.25rem rgba(25, 135, 84, 0.25);
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
                <a href="logout.php" class="btn btn-outline-danger btn-sm rounded-pill px-3">Logout</a>
            </div>
        </div>
    </nav>

    <!-- Main Content Container -->
    <div class="container-fluid px-4 my-4" style="max-width: 1400px;">
        
        <!-- Header & Action Row -->
        <div class="row align-items-center mb-4 g-3">
            <div class="col-md-6">
                <h2 class="fw-bold text-white mb-1"><i class="bi bi-people-fill text-success me-2"></i> Administrators Directory</h2>
                <p class="text-secondary small mb-0">Click on any admin's name or email to view their activity logs and date-range reports.</p>
            </div>
            
            <div class="col-md-6 d-flex flex-wrap justify-content-md-end gap-3 align-items-center">
                <div class="position-relative" style="width: 250px;">
                    <i class="bi bi-search search-icon"></i>
                    <input type="text" id="adminSearchInput" class="form-control search-box w-100" placeholder="Search email..." onkeyup="filterAdmins()">
                </div>
                
                <a href="add-admin.php" class="btn btn-success fw-bold px-3 py-2 shadow-sm rounded-3 d-flex align-items-center gap-2">
                    <i class="bi bi-person-plus-fill"></i> Add Admin
                </a>
            </div>
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

        <!-- Admins Directory Table -->
        <div class="admin-table-container">
            <div class="table-responsive">
                <table class="table table-hover" id="adminTable">
                    <thead>
                        <tr>
                            <th>#ID</th>
                            <th>Username</th>
                            <th>Email Address</th>
                            <th>Access Role</th>
                            <th>Account Status</th>
                            <th>Created Date</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($admins_list)): ?>
                            <?php foreach ($admins_list as $row): ?>
                                <tr class="admin-row">
                                    <td class="text-secondary fw-semibold">#<?php echo $row['admin_id']; ?></td>
                                    <td>
                                        <a href="#" class="fw-bold admin-link" data-bs-toggle="modal" data-bs-target="#activityModal<?php echo $row['admin_id']; ?>" title="Click to view activity logs">
                                            <?php echo htmlspecialchars($row['username']); ?> <i class="bi bi-box-arrow-up-right small text-info ms-1"></i>
                                        </a>
                                    </td>
                                    <td class="admin-email text-secondary">
                                        <a href="#" class="admin-link text-secondary" data-bs-toggle="modal" data-bs-target="#activityModal<?php echo $row['admin_id']; ?>">
                                            <?php echo htmlspecialchars($row['email']); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <form action="manage-admins.php" method="POST" class="d-flex gap-2 align-items-center">
                                            <input type="hidden" name="admin_id" value="<?php echo $row['admin_id']; ?>">
                                            <select name="new_role" class="form-select form-select-sm py-1" style="width: 135px;">
                                                <option value="Super Admin" <?php echo ($row['role'] === 'Super Admin') ? 'selected' : ''; ?>>Super Admin</option>
                                                <option value="Admin" <?php echo ($row['role'] === 'Admin') ? 'selected' : ''; ?>>Admin</option>
                                            </select>
                                            <button type="submit" name="update_role" class="btn btn-sm btn-outline-success px-2 py-1" title="Save Role">
                                                <i class="bi bi-check-lg"></i>
                                            </button>
                                        </form>
                                    </td>
                                    <td>
                                        <?php if ($row['account_status'] === 'Active'): ?>
                                            <span class="badge bg-success bg-opacity-25 text-success border border-success px-2 py-1">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger bg-opacity-25 text-danger border border-danger px-2 py-1">Blocked</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-secondary small"><?php echo $row['created_at'] ?? 'N/A'; ?></td>
                                    <td class="text-center">
                                        <div class="d-flex justify-content-center gap-1">
                                            <button type="button" class="btn btn-sm btn-outline-info px-2 py-1" data-bs-toggle="modal" data-bs-target="#activityModal<?php echo $row['admin_id']; ?>" title="View Activity Report">
                                                <i class="bi bi-clock-history"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary px-2 py-1" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $row['admin_id']; ?>" title="Edit Admin">
                                                <i class="bi bi-pencil-fill"></i>
                                            </button>
                                            <a href="manage-admins.php?action=toggle_status&id=<?php echo $row['admin_id']; ?>" class="btn btn-sm btn-outline-warning px-2 py-1" onclick="return confirm('Are you sure you want to change the status?');" title="Block/Unblock">
                                                <i class="bi bi-lock-fill"></i>
                                            </a>
                                            <?php if ($current_admin_id != $row['admin_id']): ?>
                                                <a href="manage-admins.php?action=delete&id=<?php echo $row['admin_id']; ?>" class="btn btn-sm btn-outline-danger px-2 py-1" onclick="return confirm('Are you sure you want to delete permanently?');" title="Delete">
                                                    <i class="bi bi-trash-fill"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center text-secondary py-5">No administrators found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="mt-4">
            <a href="dashboard.php" class="text-decoration-none text-success fw-bold d-inline-flex align-items-center gap-1">
                <i class="bi bi-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <!-- Modals Section -->
    <?php if (!empty($admins_list)): ?>
        <?php foreach ($admins_list as $row): ?>
            
            <!-- 1. Edit Admin Modal -->
            <div class="modal fade" id="editModal<?php echo $row['admin_id']; ?>" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content p-3">
                        <div class="modal-header border-bottom border-secondary border-opacity-10 pb-3">
                            <h5 class="modal-title fw-bold text-white d-flex align-items-center gap-2">
                                <i class="bi bi-pencil-square text-success"></i> Edit Administrator
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form action="manage-admins.php" method="POST">
                            <div class="modal-body py-4">
                                <input type="hidden" name="admin_id" value="<?php echo $row['admin_id']; ?>">
                                <div class="mb-3">
                                    <label class="form-label small text-secondary fw-semibold">Admin Username</label>
                                    <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($row['username']); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small text-secondary fw-semibold">Email Address</label>
                                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($row['email']); ?>" required>
                                </div>
                            </div>
                            <div class="modal-footer border-top border-secondary border-opacity-10 pt-3">
                                <button type="button" class="btn btn-outline-secondary btn-sm rounded-3 px-3" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" name="edit_admin" class="btn btn-success btn-sm fw-bold rounded-3 px-4 shadow-sm">Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- 2. Admin Activity Logs & Report Modal -->
            <div class="modal fade" id="activityModal<?php echo $row['admin_id']; ?>" tabindex="-1" aria-hidden="true" data-admin-id="<?php echo $row['admin_id']; ?>">
                <div class="modal-dialog modal-dialog-centered modal-xl">
                    <div class="modal-content p-3">
                        <div class="modal-header border-bottom border-secondary border-opacity-10 pb-3">
                            <h5 class="modal-title fw-bold text-info d-flex align-items-center gap-2">
                                <i class="bi bi-clock-history"></i> Activity Report: <?php echo htmlspecialchars($row['username']); ?> (<?php echo htmlspecialchars($row['email']); ?>)
                            </h5>
                            <button type="button" class="btn-close btn-close-white closeModalBtn" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        
                        <div class="modal-body py-3">
                            <!-- Date Range Filter Form -->
                            <form method="GET" action="manage-admins.php" class="row g-2 mb-4 p-3 rounded-3 align-items-end filterForm" style="background: rgba(13, 13, 18, 0.6); border: 1px solid rgba(255,255,255,0.05);">
                                <input type="hidden" name="open_modal" value="<?php echo $row['admin_id']; ?>">
                                <div class="col-md-4">
                                    <label class="form-label text-secondary small">From Date</label>
                                    <input type="date" name="from_date_<?php echo $row['admin_id']; ?>" class="form-control form-control-sm fromDateInput" value="<?php echo htmlspecialchars($_GET['from_date_' . $row['admin_id']] ?? ''); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label text-secondary small">To Date</label>
                                    <input type="date" name="to_date_<?php echo $row['admin_id']; ?>" class="form-control form-control-sm toDateInput" value="<?php echo htmlspecialchars($_GET['to_date_' . $row['admin_id']] ?? ''); ?>">
                                </div>
                                <div class="col-md-4 d-flex gap-2">
                                    <button type="submit" class="btn btn-success btn-sm w-50 fw-bold"><i class="bi bi-filter"></i> Filter</button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm w-50 text-center resetFilterBtn">Reset</button>
                                </div>
                            </form>

                            <?php
                            // Fetch Data with Date Range Filtering & Summary Calculation
                            $modal_admin_id = $row['admin_id'];
                            $m_sql = "SELECT * FROM admin_activity_logs WHERE admin_id = $modal_admin_id";
                            
                            $from_date_key = 'from_date_' . $modal_admin_id;
                            $to_date_key = 'to_date_' . $modal_admin_id;
                            
                            $from_val = $_GET[$from_date_key] ?? '';
                            $to_val = $_GET[$to_date_key] ?? '';

                            if (!empty($from_val)) {
                                $f_safe = $conn->real_escape_string($from_val);
                                $m_sql .= " AND DATE(login_time) >= '$f_safe'";
                            }
                            if (!empty($to_val)) {
                                $t_safe = $conn->real_escape_string($to_val);
                                $m_sql .= " AND DATE(login_time) <= '$t_safe'";
                            }
                            
                            $m_sql .= " ORDER BY log_id DESC";
                            $m_res = $conn->query($m_sql);

                            $total_active_days = 0;
                            $total_seconds = 0;
                            $logs_data = [];
                            $unique_days = [];

                            if ($m_res && $m_res->num_rows > 0) {
                                while ($mlog = $m_res->fetch_assoc()) {
                                    $logs_data[] = $mlog;
                                    $log_day = date('Y-m-d', strtotime($mlog['login_time']));
                                    $unique_days[$log_day] = true;

                                    if (!empty($mlog['logout_time'])) {
                                        $duration = strtotime($mlog['logout_time']) - strtotime($mlog['login_time']);
                                        if ($duration > 0) {
                                            $total_seconds += $duration;
                                        }
                                    }
                                }
                            }
                            $total_active_days = count($unique_days);
                            $total_hours = floor($total_seconds / 3600);
                            $total_mins = floor(($total_seconds % 3600) / 60);
                            ?>

                            <!-- Summary Cards Inside Modal -->
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <div class="p-3 rounded-3 bg-dark bg-opacity-50 border border-secondary border-opacity-25 d-flex align-items-center justify-content-between">
                                        <div>
                                            <span class="text-secondary small d-block">Total Active Days (Range)</span>
                                            <h4 class="fw-bold text-success mb-0"><?php echo $total_active_days; ?> Days</h4>
                                        </div>
                                        <div class="fs-2 text-success opacity-50"><i class="bi bi-calendar-check"></i></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="p-3 rounded-3 bg-dark bg-opacity-50 border border-secondary border-opacity-25 d-flex align-items-center justify-content-between">
                                        <div>
                                            <span class="text-secondary small d-block">Total Time Spent Logged In</span>
                                            <h4 class="fw-bold text-info mb-0"><?php echo $total_hours; ?> hrs <?php echo $total_mins; ?> mins</h4>
                                        </div>
                                        <div class="fs-2 text-info opacity-50"><i class="bi bi-stopwatch"></i></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Action Buttons Bar -->
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-outline-info btn-sm fw-bold px-3 shadow-sm" onclick="openPDFReport('<?php echo htmlspecialchars($row['username']); ?>', '<?php echo htmlspecialchars($row['email']); ?>', '<?php echo $from_val; ?>', '<?php echo $to_val; ?>', '<?php echo $total_active_days; ?>', '<?php echo $total_hours; ?> hrs <?php echo $total_mins; ?> mins', 'reportTable_<?php echo $row['admin_id']; ?>')">
                                        <i class="bi bi-eye me-1"></i> View Report (Open in Browser)
                                    </button>
                                    <button type="button" class="btn btn-outline-success btn-sm fw-bold px-3 shadow-sm" onclick="sendPDFReportEmail('<?php echo htmlspecialchars($row['username']); ?>', '<?php echo htmlspecialchars($row['email']); ?>', '<?php echo $from_val; ?>', '<?php echo $to_val; ?>', '<?php echo $total_active_days; ?>', '<?php echo $total_hours; ?> hrs <?php echo $total_mins; ?> mins', 'reportTable_<?php echo $row['admin_id']; ?>')">
                                        <i class="bi bi-envelope-fill me-1"></i> Send Report to Email
                                    </button>
                                </div>
                                
                                <form method="POST" onsubmit="return confirm('Clear activity history for this admin?');">
                                    <input type="hidden" name="clear_admin_id" value="<?php echo $row['admin_id']; ?>">
                                    <button type="submit" name="clear_activities" class="btn btn-outline-danger btn-sm py-1" style="font-size: 11px;">
                                        <i class="bi bi-trash"></i> Clear Logs
                                    </button>
                                </form>
                            </div>

                            <!-- Logs Table inside Modal -->
                            <div class="table-responsive" style="max-height: 350px; overflow-y: auto;">
                                <table class="table table-hover table-sm align-middle" id="reportTable_<?php echo $row['admin_id']; ?>">
                                    <thead>
                                        <tr style="font-size: 12px;">
                                            <th>Activity / Status</th>
                                            <th>Login Date & Time</th>
                                            <th>Logout Date & Time</th>
                                            <th>Time Spent</th>
                                            <th>IP Address</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($logs_data)): ?>
                                            <?php foreach ($logs_data as $mlog): ?>
                                                <tr style="font-size: 13px;">
                                                    <td><span class="badge bg-info bg-opacity-25 text-info border border-info px-2 py-1"><?php echo htmlspecialchars($mlog['activity_description'] ?? 'Logged In'); ?></span></td>
                                                    <td class="text-success small"><?php echo htmlspecialchars($mlog['login_time'] ?? '-'); ?></td>
                                                    <td class="text-danger small"><?php echo htmlspecialchars($mlog['logout_time'] ?? 'Active Session'); ?></td>
                                                    <td class="text-warning small">
                                                        <?php
                                                        if (!empty($mlog['logout_time'])) {
                                                            $secs = strtotime($mlog['logout_time']) - strtotime($mlog['login_time']);
                                                            $h = floor($secs / 3600);
                                                            $m = floor(($secs % 3600) / 60);
                                                            $s = $secs % 60;
                                                            echo ($h > 0 ? $h . 'h ' : '') . $m . 'm ' . $s . 's';
                                                        } else {
                                                            echo 'Ongoing';
                                                        }
                                                        ?>
                                                    </td>
                                                    <td class="text-secondary small"><?php echo htmlspecialchars($mlog['ip_address'] ?? 'N/A'); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr><td colspan="5" class="text-center text-secondary py-4">No activity logs found for the selected date range.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="modal-footer border-top border-secondary border-opacity-10 pt-3">
                            <button type="button" class="btn btn-outline-secondary btn-sm rounded-3 px-4 closeModalBtn" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>

        <?php endforeach; ?>
    <?php endif; ?>

    <!-- JavaScript for Live Search, Modal Persistence, URL Clearing & PDF Actions -->
    <script>
    function filterAdmins() {
        let input = document.getElementById('adminSearchInput').value.toLowerCase();
        let rows = document.getElementsByClassName('admin-row');

        for (let i = 0; i < rows.length; i++) {
            let emailTd = rows[i].getElementsByClassName('admin-email')[0];
            if (emailTd) {
                let emailText = emailTd.textContent || emailTd.innerText;
                if (emailText.toLowerCase().indexOf(input) > -1) {
                    rows[i].style.display = "";
                } else {
                    rows[i].style.display = "none";
                }
            }
        }
    }

    // Automatically reopen modal if filter/date was submitted
    document.addEventListener("DOMContentLoaded", function() {
        <?php if ($active_modal_id > 0): ?>
            let modalElement = document.getElementById('activityModal<?php echo $active_modal_id; ?>');
            if (modalElement) {
                let myModal = new bootstrap.Modal(modalElement);
                myModal.show();
            }
        <?php endif; ?>

        // Handle Close & Reset buttons to clean up URL parameters cleanly
        document.querySelectorAll('.closeModalBtn').forEach(btn => {
            btn.addEventListener('click', function() {
                if (window.location.search.includes('open_modal')) {
                    window.history.replaceState({}, document.title, window.location.pathname);
                }
            });
        });

        document.querySelectorAll('.resetFilterBtn').forEach(btn => {
            btn.addEventListener('click', function() {
                let form = this.closest('form');
                form.querySelectorAll('input[type="date"]').forEach(input => input.value = '');
                window.history.replaceState({}, document.title, window.location.pathname);
                form.submit();
            });
        });
    });

    // Helper function to generate jsPDF instance
    function generatePDFReportDoc(username, email, fromDate, toDate, activeDays, totalTime, tableId) {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();

        doc.setFont("helvetica", "bold");
        doc.setFontSize(18);
        doc.setTextColor(25, 135, 84);
        doc.text("CraftedCeylon - Admin Activity Report", 14, 20);

        doc.setFontSize(11);
        doc.setTextColor(100, 100, 100);
        doc.text(`Administrator: ${username} (${email})`, 14, 28);
        
        let rangeText = "Date Range: All Time / Unfiltered";
        if (fromDate && toDate) {
            rangeText = `Date Range: ${fromDate} to ${toDate}`;
        } else if (fromDate) {
            rangeText = `Date Range: From ${fromDate} onwards`;
        } else if (toDate) {
            rangeText = `Date Range: Up to ${toDate}`;
        }
        doc.text(rangeText, 14, 35);
        doc.text(`Generated On: ${new Date().toLocaleString()}`, 14, 42);

        doc.setFillColor(240, 245, 240);
        doc.roundedRect(14, 48, 182, 16, 3, 3, 'F');
        doc.setFont("helvetica", "bold");
        doc.setFontSize(10);
        doc.setTextColor(40, 40, 40);
        doc.text(`Summary: Total Active Days: ${activeDays} | Total Login Duration: ${totalTime}`, 18, 58);

        doc.autoTable({
            html: '#' + tableId,
            startY: 68,
            theme: 'grid',
            headStyles: { fillColor: [25, 135, 84], textColor: [255, 255, 255] },
            styles: { fontSize: 9, cellPadding: 4 },
            columnStyles: { 0: { cellWidth: 35 }, 1: { cellWidth: 50 }, 2: { cellWidth: 50 }, 3: { cellWidth: 25 }, 4: { cellWidth: 22 } }
        });

        return doc;
    }

    // 1. Open PDF Report in Browser Tab
    function openPDFReport(username, email, fromDate, toDate, activeDays, totalTime, tableId) {
        let doc = generatePDFReportDoc(username, email, fromDate, toDate, activeDays, totalTime, tableId);
        const pdfOutput = doc.output('bloburl');
        window.open(pdfOutput, '_blank');
    }

    // 2. Generate PDF and send via Email to Admin using AJAX
    function sendPDFReportEmail(username, email, fromDate, toDate, activeDays, totalTime, tableId) {
        if (!confirm(`Are you sure you want to email this activity report to ${email}?`)) {
            return;
        }

        let doc = generatePDFReportDoc(username, email, fromDate, toDate, activeDays, totalTime, tableId);
        let pdfData = doc.output('datauristring'); // Get base64 string

        let formData = new FormData();
        formData.append('send_report_email', '1');
        formData.append('admin_email', email);
        formData.append('admin_name', username);
        formData.append('pdf_data', pdfData);

        let btn = event.target;
        let originalText = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Sending...';
        btn.disabled = true;

        fetch('manage-admins.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            alert(data.message);
            btn.innerHTML = originalText;
            btn.disabled = false;
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while sending the email.');
            btn.innerHTML = originalText;
            btn.disabled = false;
        });
    }
    </script>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>