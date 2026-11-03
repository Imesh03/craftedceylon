<?php
session_start();
require_once '../config/db.php';
date_default_timezone_set('Asia/Colombo');

// Check if admin is logged in
if (!isset($_SESSION['admin_logged'])) {
    header("Location: login.php");
    exit();
}

$current_admin_id = $_SESSION['admin_id'];

// Check if the logged-in admin is a Super Admin
$role_stmt = $conn->prepare("SELECT role, username FROM admins WHERE admin_id = ?");
$role_stmt->bind_param("i", $current_admin_id);
$role_stmt->execute();
$current_admin = $role_stmt->get_result()->fetch_assoc();
$is_super_admin = ($current_admin && $current_admin['role'] === 'Super Admin');

$view_customer_id = isset($_GET['view']) ? intval($_GET['view']) : 0;
$msg = '';
$error = '';

// Handle Clear Logs action for a specific customer (SUPER ADMIN ONLY)
if (isset($_POST['clear_customer_logs']) && $view_customer_id > 0) {
    if (!$is_super_admin) {
        header("Location: customers.php?view=" . $view_customer_id . "&error=unauthorized");
        exit();
    }

    $clear_stmt = $conn->prepare("DELETE FROM user_activities WHERE user_id = ?");
    if ($clear_stmt) {
        $clear_stmt->bind_param("i", $view_customer_id);
        $clear_stmt->execute();
        
        $clear_login_stmt = $conn->prepare("DELETE FROM user_login_logs WHERE user_id = ?");
        if ($clear_login_stmt) {
            $clear_login_stmt->bind_param("i", $view_customer_id);
            $clear_login_stmt->execute();
        }

        header("Location: customers.php?view=" . $view_customer_id . "&msg=logs_cleared");
        exit();
    }
}

// Handle Order Status Update if requested from customer profile view
if (isset($_POST['update_order_status'])) {
    $order_id = intval($_POST['order_id']);
    $new_status = $_POST['order_status'];
    $customer_id_redirect = intval($_POST['customer_id']);

    $stmt = $conn->prepare("UPDATE orders SET order_status = ? WHERE order_id = ?");
    $stmt->bind_param("si", $new_status, $order_id);
    $stmt->execute();

    $redirect_url = "customers.php?view=" . $customer_id_redirect;
    if (isset($_POST['order_search']) && !empty($_POST['order_search'])) {
        $redirect_url .= "&order_search=" . urlencode($_POST['order_search']);
    }
    header("Location: " . $redirect_url);
    exit();
}

// Handle Customer Account Status (Active/Blocked) Update (SUPER ADMIN ONLY)
if (isset($_POST['update_account_status'])) {
    if (!$is_super_admin) {
        header("Location: customers.php?view=" . intval($_POST['customer_id']) . "&error=unauthorized");
        exit();
    }

    $target_customer_id = intval($_POST['customer_id']);
    $new_account_status = $_POST['account_status'];

    $status_stmt = $conn->prepare("UPDATE users SET account_status = ? WHERE user_id = ?");
    $status_stmt->bind_param("si", $new_account_status, $target_customer_id);
    $status_stmt->execute();

    header("Location: customers.php?view=" . $target_customer_id);
    exit();
}

// Search handling for main customer list
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$sql = "SELECT u.*, 
        (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.user_id) as total_orders 
        FROM users u WHERE 1=1";

if (!empty($search_query)) {
    $escaped = $conn->real_escape_string($search_query);
    $sql .= " AND (u.full_name LIKE '%$escaped%' OR u.email LIKE '%$escaped%')";
}

$sql .= " ORDER BY u.created_at DESC";
$result = $conn->query($sql);

$customer_details = null;
$customer_orders = null;
$customer_login_logs = null;

if ($view_customer_id > 0) {
    // Fetch customer info
    $cust_stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
    $cust_stmt->bind_param("i", $view_customer_id);
    $cust_stmt->execute();
    $customer_details = $cust_stmt->get_result()->fetch_assoc();

    // Fetch orders for this customer with optional order reference search
    $order_search = isset($_GET['order_search']) ? trim($_GET['order_search']) : '';
    $ord_sql = "SELECT * FROM orders WHERE user_id = ?";
    
    if (!empty($order_search)) {
        $escaped_ord = $conn->real_escape_string($order_search);
        $ord_sql .= " AND order_reference LIKE '%$escaped_ord%'";
    }
    $ord_sql .= " ORDER BY order_date DESC";
    
    $ord_stmt = $conn->prepare($ord_sql);
    $ord_stmt->bind_param("i", $view_customer_id);
    $ord_stmt->execute();
    $customer_orders = $ord_stmt->get_result();

    // Fetch security & login logs for this customer
    $log_stmt = $conn->prepare("SELECT * FROM user_login_logs WHERE user_id = ? ORDER BY login_time DESC LIMIT 10");
    $log_stmt->bind_param("i", $view_customer_id);
    $log_stmt->execute();
    $customer_login_logs = $log_stmt->get_result();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Management - CraftedCeylon Admin</title>
    <!-- Google Font -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        :root {
            --bg-dark: #0d0d12;
            --bg-card: rgba(26, 26, 40, 0.85);
            --bg-sidebar: #13131d;
            --text-main: #ffffff;
            --text-sub: #a0aec0;
            --accent-green: #198754;
            --border-color: rgba(255, 255, 255, 0.08);
        }

        body {
            background-color: var(--bg-dark);
            background-image: linear-gradient(135deg, rgba(13, 13, 18, 0.95), rgba(20, 30, 25, 0.9)), 
                              radial-gradient(circle at 10% 20%, rgba(25, 135, 84, 0.15) 0%, transparent 40%),
                              radial-gradient(circle at 90% 80%, rgba(13, 202, 240, 0.08) 0%, transparent 40%);
            background-attachment: fixed;
            color: var(--text-main);
            font-family: 'Plus Jakarta Sans', sans-serif;
            min-height: 100vh;
        }

        .sidebar {
            background-color: var(--bg-sidebar);
            border-right: 1px solid var(--border-color);
            backdrop-filter: blur(15px);
        }

        .nav-link {
            color: var(--text-sub);
            border-radius: 12px;
            padding: 12px 16px;
            font-weight: 500;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.05);
            color: var(--text-main) !important;
            transform: translateX(4px);
        }

        .nav-link.active {
            background: linear-gradient(135deg, #198754 0%, #146c43 100%);
            color: #ffffff !important;
            box-shadow: 0 4px 20px rgba(25, 135, 84, 0.35);
        }

        .dark-card {
            background: var(--bg-card);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--border-color);
            border-radius: 18px;
            box-shadow: 0 16px 40px rgba(0, 0, 0, 0.5);
        }

        .table-dark-custom {
            --bs-table-bg: transparent;
            --bs-table-color: var(--text-main);
            border-color: var(--border-color);
        }

        .table-dark-custom th {
            color: var(--accent-green);
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.8px;
            padding: 12px 16px;
            border-bottom: 2px solid var(--border-color);
            background-color: rgba(19, 19, 29, 0.95);
            white-space: nowrap;
        }

        .table-dark-custom td {
            padding: 12px 16px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-main);
            vertical-align: middle;
            font-size: 0.9rem;
        }

        .table-dark-custom tbody tr:hover {
            background-color: rgba(255, 255, 255, 0.04);
        }

        .security-logs-container {
            max-height: 180px;
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: #198754 #1a1a28;
        }
        .security-logs-container::-webkit-scrollbar {
            width: 6px;
        }
        .security-logs-container::-webkit-scrollbar-track {
            background: rgba(18, 18, 22, 0.5);
            border-radius: 10px;
        }
        .security-logs-container::-webkit-scrollbar-thumb {
            background-color: #198754;
            border-radius: 10px;
        }

        .avatar-large {
            width: 75px;
            height: 75px;
            border-radius: 18px;
            background: rgba(25, 135, 84, 0.2);
            color: var(--accent-green);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.2rem;
            border: 1px solid rgba(25, 135, 84, 0.4);
        }

        .form-control, .form-select {
            background-color: rgba(18, 18, 22, 0.8);
            border: 1px solid var(--border-color);
            color: var(--text-main);
            border-radius: 10px;
            padding: 9px 14px;
        }

        .form-control:focus, .form-select:focus {
            background-color: rgba(18, 18, 22, 0.95);
            border-color: var(--accent-green);
            color: var(--text-main);
            box-shadow: 0 0 0 0.25rem rgba(25, 135, 84, 0.25);
        }

        .modal {
            z-index: 1055 !important;
        }
        .modal-backdrop {
            z-index: 1050 !important;
        }
        .modal-content {
            background-color: #1a1a28;
            color: var(--text-main);
            border: 1px solid var(--border-color);
            border-radius: 18px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.6);
        }
        .modal-header, .modal-footer {
            border-color: var(--border-color);
        }

        .glow-success {
            box-shadow: 0 0 20px rgba(25, 135, 84, 0.3);
        }

        .item-row-card {
            background: rgba(18, 18, 22, 0.7);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 12px;
        }

        @media print {
            body * {
                visibility: hidden;
            }
            .modal.show .modal-content, .modal.show .modal-content * {
                visibility: visible;
            }
            .modal.show .modal-content {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                background: #ffffff !important;
                color: #000000 !important;
                border: none !important;
                box-shadow: none !important;
            }
            .modal-footer, .btn-close {
                display: none !important;
            }
            .text-white, .text-secondary, .text-success, .text-warning, .text-info {
                color: #000000 !important;
            }
            .item-row-card {
                background: #f8f9fa !important;
                border: 1px solid #ddd !important;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar min-vh-100 p-3">
                <div class="d-flex align-items-center gap-2 mb-4 px-2">
                    <div class="bg-success text-white rounded-3 p-2 d-flex align-items-center justify-content-center glow-success">
                        <i class="bi bi-flower1 fs-4"></i>
                    </div>
                    <h5 class="fw-bold text-white mb-0">CraftedCeylon</h5>
                </div>
                <ul class="nav flex-column gap-2">
                    <li class="nav-item"><a href="dashboard.php" class="nav-link"><i class="bi bi-speedometer2 me-2"></i> Dashboard</a></li>
                    <li class="nav-item"><a href="products.php" class="nav-link"><i class="bi bi-box-seam me-2"></i> Products</a></li>
                    <li class="nav-item"><a href="orders.php" class="nav-link"><i class="bi bi-cart-check me-2"></i> Orders & Tracking</a></li>
                    <li class="nav-item"><a href="inventory.php" class="nav-link"><i class="bi bi-stack me-2"></i> Inventory Stock</a></li>
                    <li class="nav-item"><a href="customers.php" class="nav-link active"><i class="bi bi-people me-2"></i> Customers</a></li>
                    <li class="nav-item"><a href="reviews.php" class="nav-link"><i class="bi bi-star-half me-2"></i> Customer Reviews</a></li>
                    <?php if ($is_super_admin): ?>
                    <li class="nav-item"><a href="manage-admins.php" class="nav-link text-warning"><i class="bi bi-shield-lock-fill me-2"></i> Manage Admins</a></li>
                    <?php endif; ?>
                    <li class="nav-item mt-5"><a href="logout.php" class="nav-link text-danger"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
                </ul>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 p-4 p-md-5">
                
                <?php if (isset($_GET['msg']) && $_GET['msg'] == 'logs_cleared'): ?>
                    <div class="alert alert-success bg-success text-white border-0 shadow-sm alert-dismissible fade show mb-4" role="alert">
                        <i class="bi bi-check-circle me-1"></i> Customer security and activity logs cleared successfully!
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET['error']) && $_GET['error'] == 'unauthorized'): ?>
                    <div class="alert alert-danger bg-danger text-white border-0 shadow-sm alert-dismissible fade show mb-4" role="alert">
                        <i class="bi bi-exclamation-triangle me-1"></i> Access Denied! Only Super Admins are authorized to perform this action.
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($view_customer_id > 0 && $customer_details): ?>
                    <!-- ================= SINGLE CUSTOMER DETAILED VIEW ================= -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h2 class="fw-bold text-white mb-1"><i class="bi bi-person-lines-fill me-2 text-success"></i> Customer Profile Details</h2>
                            <p class="text-secondary small mb-0">Detailed view of personal information, login logs, and order history.</p>
                        </div>
                        <a href="customers.php" class="btn btn-outline-light btn-sm px-3">
                            <i class="bi bi-arrow-left me-1"></i> Back to Customers List
                        </a>
                    </div>

                    <!-- Customer Personal Info Card & Security Block Option -->
                    <div class="dark-card p-4 mb-4">
                        <div class="row align-items-center">
                            <div class="col-md-auto text-center mb-3 mb-md-0">
                                <div class="avatar-large mx-auto">
                                    <i class="bi bi-person-fill"></i>
                                </div>
                            </div>
                            <div class="col-md">
                                <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                    <h3 class="fw-bold text-white mb-0"><?php echo htmlspecialchars($customer_details['full_name']); ?></h3>
                                    <?php 
                                        $acc_status = $customer_details['account_status'] ?? 'Active';
                                        $badge_style = ($acc_status == 'Active') ? 'success' : 'danger';
                                    ?>
                                    <span class="badge bg-<?php echo $badge_style; ?> bg-opacity-25 text-<?php echo $badge_style; ?> border border-<?php echo $badge_style; ?>">
                                        Status: <?php echo $acc_status; ?>
                                    </span>
                                </div>
                                <p class="text-success mb-2"><i class="bi bi-envelope me-1"></i> <?php echo htmlspecialchars($customer_details['email']); ?></p>
                                <div class="d-flex flex-wrap gap-3 text-secondary small">
                                    <div><strong class="text-light">Customer ID:</strong> #<?php echo $customer_details['user_id']; ?></div>
                                    <div><strong class="text-light">Registered Date:</strong> <?php echo date('Y-m-d H:i', strtotime($customer_details['created_at'])); ?></div>
                                </div>
                            </div>
                            <!-- Block / Unblock Account Action Form (SUPER ADMIN ONLY) -->
                            <div class="col-md-auto mt-3 mt-md-0 text-md-end">
                                <?php if ($is_super_admin): ?>
                                    <form action="customers.php?view=<?php echo $view_customer_id; ?>" method="POST">
                                        <input type="hidden" name="customer_id" value="<?php echo $view_customer_id; ?>">
                                        <?php if ($acc_status === 'Active'): ?>
                                            <input type="hidden" name="account_status" value="Blocked">
                                            <button type="submit" name="update_account_status" class="btn btn-outline-danger btn-sm px-3 py-2 fw-semibold" onclick="return confirm('Are you sure you want to block this customer account?');">
                                                <i class="bi bi-slash-circle me-1"></i> Block Account
                                            </button>
                                        <?php else: ?>
                                            <input type="hidden" name="account_status" value="Active">
                                            <button type="submit" name="update_account_status" class="btn btn-outline-success btn-sm px-3 py-2 fw-semibold" onclick="return confirm('Are you sure you want to activate this customer account?');">
                                                <i class="bi bi-check-circle me-1"></i> Unblock / Activate
                                            </button>
                                        <?php endif; ?>
                                    </form>
                                <?php else: ?>
                                    <span class="badge bg-secondary text-light px-3 py-2"><i class="bi bi-shield-lock me-1"></i> Super Admin Only Action</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Customer Recent Login Logs & Security Info with View All & Clear Options -->
                    <div class="dark-card p-4 mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="fw-bold text-white mb-0"><i class="bi bi-shield-lock me-2 text-success"></i> Recent Login & Device Security Logs</h5>
                            <div class="d-flex gap-2">
                                <!-- View All Logs Button -->
                                <button type="button" class="btn btn-outline-info btn-sm fw-bold px-3" data-bs-toggle="modal" data-bs-target="#allLogsModal">
                                    <i class="bi bi-list-ul me-1"></i> View All Logs
                                </button>
                                
                                <!-- Clear Logs Button (SUPER ADMIN ONLY) -->
                                <?php if ($is_super_admin): ?>
                                    <form action="customers.php?view=<?php echo $view_customer_id; ?>" method="POST" onsubmit="return confirm('Are you sure you want to clear all activity and security logs for this customer?');" class="d-inline">
                                        <button type="submit" name="clear_customer_logs" class="btn btn-outline-danger btn-sm fw-bold px-3">
                                            <i class="bi bi-trash-fill me-1"></i> Clear Logs
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Scrollable container -->
                        <div class="table-responsive security-logs-container">
                            <table class="table table-dark-custom align-middle mb-0">
                                <thead class="sticky-top">
                                    <tr>
                                        <th>Login Time</th>
                                        <th>Logout Time</th>
                                        <th>IP Address</th>
                                        <th>Location</th>
                                        <th>Device / Browser Agent</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($customer_login_logs && $customer_login_logs->num_rows > 0): ?>
                                        <?php while($log = $customer_login_logs->fetch_assoc()): ?>
                                        <tr>
                                            <td><span class="text-success"><?php echo htmlspecialchars($log['login_time']); ?></span></td>
                                            <td><span class="text-secondary"><?php echo !empty($log['logout_time']) ? htmlspecialchars($log['logout_time']) : '<span class="text-warning fst-italic">Active Session</span>'; ?></span></td>
                                            <td><code class="text-info"><?php echo htmlspecialchars($log['ip_address']); ?></code></td>
                                            <td><span class="text-light"><i class="bi bi-geo-alt me-1 text-danger"></i><?php echo htmlspecialchars($log['location'] ?? 'Unknown'); ?></span></td>
                                            <td><small class="text-secondary text-truncate d-inline-block" style="max-width: 250px;" title="<?php echo htmlspecialchars($log['device_info']); ?>"><?php echo htmlspecialchars($log['device_info']); ?></small></td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center text-secondary py-3">No login logs recorded yet for this customer.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Modal for All Logs, Full History & Duration Details -->
                    <div class="modal fade" id="allLogsModal" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title fw-bold text-success">
                                        <i class="bi bi-clock-history me-2"></i> Complete Login / Logout & Session Duration History
                                    </h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body p-4">
                                    <div class="table-responsive">
                                        <table class="table table-dark table-striped table-bordered align-middle mb-0">
                                            <thead class="table-secondary text-dark">
                                                <tr>
                                                    <th>#ID</th>
                                                    <th>Action Name</th>
                                                    <th>Page URL</th>
                                                    <th>Timestamp</th>
                                                    <th>Session Duration / Details</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                $all_logs_q = "SELECT * FROM user_activities WHERE user_id = ? ORDER BY activity_time DESC";
                                                $al_stmt = $conn->prepare($all_logs_q);
                                                $al_stmt->bind_param("i", $view_customer_id);
                                                $al_stmt->execute();
                                                $all_res = $al_stmt->get_result();

                                                if ($all_res && $all_res->num_rows > 0):
                                                    $rows = [];
                                                    while($r = $all_res->fetch_assoc()) {
                                                        $rows[] = $r;
                                                    }

                                                    for($i = 0; $i < count($rows); $i++):
                                                        $current = $rows[$i];
                                                        $duration_text = "-";
                                                        
                                                        if ($current['action_name'] == 'User Logged Out') {
                                                            if (isset($rows[$i+1]) && $rows[$i+1]['action_name'] == 'User Logged In') {
                                                                $login_time = strtotime($rows[$i+1]['activity_time']);
                                                                $logout_time = strtotime($current['activity_time']);
                                                                $diff = $logout_time - $login_time;
                                                                
                                                                $hours = floor($diff / 3600);
                                                                $minutes = floor(($diff / 60) % 60);
                                                                $seconds = $diff % 60;
                                                                $duration_text = sprintf("%02d hrs %02d mins %02d secs", $hours, $minutes, $seconds);
                                                            }
                                                        } elseif ($current['action_name'] == 'User Logged In') {
                                                            if ($i == 0) {
                                                                $login_time = strtotime($current['activity_time']);
                                                                $diff = time() - $login_time;
                                                                $hours = floor($diff / 3600);
                                                                $minutes = floor(($diff / 60) % 60);
                                                                $duration_text = '<span class="text-success fw-bold">Active Now (Duration: ' . sprintf("%02d hrs %02d mins", $hours, $minutes) . ')</span>';
                                                            }
                                                        }
                                                ?>
                                                <tr>
                                                    <td><?php echo $current['activity_id']; ?></td>
                                                    <td>
                                                        <span class="badge <?php echo ($current['action_name'] == 'User Logged In') ? 'bg-success' : 'bg-danger'; ?>">
                                                            <?php echo htmlspecialchars($current['action_name']); ?>
                                                        </span>
                                                    </td>
                                                    <td><code><?php echo htmlspecialchars($current['page_url']); ?></code></td>
                                                    <td><?php echo $current['activity_time']; ?></td>
                                                    <td class="fw-semibold text-info"><?php echo $duration_text; ?></td>
                                                </tr>
                                                <?php endfor; ?>
                                                <?php else: ?>
                                                    <tr>
                                                        <td colspan="5" class="text-center text-secondary py-4">No activity logs found for this customer.</td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary btn-sm px-4" data-bs-dismiss="modal">Close</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Customer Orders Section with Reference Search Bar -->
                    <div class="dark-card p-4">
                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mb-4 gap-3">
                            <h5 class="fw-bold text-white mb-0"><i class="bi bi-bag-check me-2 text-success"></i> Customer Orders History</h5>
                            
                            <form action="customers.php" method="GET" class="d-flex gap-2" style="width: 100%; max-width: 320px;">
                                <input type="hidden" name="view" value="<?php echo $view_customer_id; ?>">
                                <div class="input-group input-group-sm">
                                    <input type="text" name="order_search" class="form-control" placeholder="Search by Ref ID..." value="<?php echo htmlspecialchars(isset($_GET['order_search']) ? $_GET['order_search'] : ''); ?>">
                                    <button class="btn btn-success" type="submit"><i class="bi bi-search"></i></button>
                                    <?php if (isset($_GET['order_search']) && !empty($_GET['order_search'])): ?>
                                        <a href="customers.php?view=<?php echo $view_customer_id; ?>" class="btn btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-dark-custom align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Ref ID</th>
                                        <th>Order Date</th>
                                        <th>Total Amount</th>
                                        <th>Status</th>
                                        <th class="text-center">View Details</th>
                                        <th>Manage Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($customer_orders && $customer_orders->num_rows > 0): ?>
                                        <?php 
                                        $orders_array = [];
                                        while($ord = $customer_orders->fetch_assoc()) {
                                            $orders_array[] = $ord;
                                        }
                                        foreach($orders_array as $ord): 
                                        ?>
                                        <tr>
                                            <td class="fw-bold text-white"><?php echo htmlspecialchars($ord['order_reference']); ?></td>
                                            <td><span class="text-secondary"><?php echo htmlspecialchars($ord['order_date']); ?></span></td>
                                            <td class="fw-bold text-success">
                                                <?php echo htmlspecialchars($ord['currency_code'] ?? 'LKR') . ' ' . number_format($ord['total_amount'], 2); ?>
                                            </td>
                                            <td>
                                                <?php 
                                                    $st = $ord['order_status'];
                                                    $b_class = 'warning text-dark';
                                                    if ($st == 'Delivered') $b_class = 'success bg-opacity-25 text-success border border-success';
                                                    elseif ($st == 'Shipped') $b_class = 'info bg-opacity-25 text-info border border-info';
                                                    elseif ($st == 'Cancelled') $b_class = 'danger';
                                                ?>
                                                <span class="badge bg-<?php echo $b_class; ?>">
                                                    <?php echo htmlspecialchars($st); ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-sm btn-outline-info fw-semibold px-2 py-1" data-bs-toggle="modal" data-bs-target="#orderModal<?php echo $ord['order_id']; ?>" title="View Full Order Details">
                                                    <i class="bi bi-eye-fill me-1"></i> View
                                                </button>
                                            </td>
                                            <td>
                                                <form action="customers.php?view=<?php echo $view_customer_id; ?>" method="POST" class="d-flex align-items-center gap-2">
                                                    <input type="hidden" name="order_id" value="<?php echo $ord['order_id']; ?>">
                                                    <input type="hidden" name="customer_id" value="<?php echo $view_customer_id; ?>">
                                                    <?php if (isset($_GET['order_search'])): ?>
                                                        <input type="hidden" name="order_search" value="<?php echo htmlspecialchars($_GET['order_search']); ?>">
                                                    <?php endif; ?>
                                                    <select name="order_status" class="form-select form-select-sm" style="width: 140px;">
                                                        <option value="Processing" <?php echo ($st == 'Processing') ? 'selected' : ''; ?>>Processing</option>
                                                        <option value="Shipped" <?php echo ($st == 'Shipped') ? 'selected' : ''; ?>>Shipped</option>
                                                        <option value="Delivered" <?php echo ($st == 'Delivered') ? 'selected' : ''; ?>>Delivered</option>
                                                        <option value="Cancelled" <?php echo ($st == 'Cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                                                    </select>
                                                    <button type="submit" name="update_order_status" class="btn btn-sm btn-outline-success">
                                                        <i class="bi bi-check-lg"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-secondary py-4">No orders found for this customer.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Order Details Modals Loop for Customer Orders -->
                    <?php if (!empty($orders_array)): ?>
                        <?php foreach ($orders_array as $ord): ?>
                            <?php 
                                $current_order_id = intval($ord['order_id']);
                                $items_query = $conn->query("SELECT oi.*, p.product_name, p.main_image FROM order_items oi LEFT JOIN products p ON oi.product_id = p.product_id WHERE oi.order_id = $current_order_id");
                                $ord_status = $ord['order_status'];
                                $badge_class = 'warning text-dark';
                                if ($ord_status == 'Shipped') $badge_class = 'info bg-opacity-25 text-info border border-info';
                                elseif ($ord_status == 'Delivered') $badge_class = 'success bg-opacity-25 text-success border border-success';
                                elseif ($ord_status == 'Cancelled') $badge_class = 'danger';
                            ?>
                            <div class="modal fade" id="orderModal<?php echo $ord['order_id']; ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title fw-bold text-success">
                                                <i class="bi bi-receipt me-2"></i> Order Details: <?php echo htmlspecialchars($ord['order_reference']); ?>
                                            </h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body p-4">
                                            <div class="row g-4 mb-4">
                                                <div class="col-md-6">
                                                    <h6 class="text-success fw-bold border-bottom border-secondary pb-2 mb-3"><i class="bi bi-person-badge me-1"></i> Customer & Shipping Information</h6>
                                                    <p class="mb-2"><strong>Full Name:</strong> <span class="text-white"><?php echo htmlspecialchars($ord['shipping_name'] ?? $customer_details['full_name']); ?></span></p>
                                                    <p class="mb-2"><strong>Email:</strong> <span class="text-secondary"><?php echo htmlspecialchars($ord['shipping_email'] ?? $customer_details['email']); ?></span></p>
                                                    <p class="mb-2"><strong>Phone Number:</strong> <span class="text-warning fw-bold"><?php echo htmlspecialchars($ord['shipping_phone'] ?? 'N/A'); ?></span></p>
                                                    <p class="mb-2"><strong>Country / Region:</strong> <span class="text-success fw-bold"><?php echo htmlspecialchars($ord['shipping_country'] ?? 'Sri Lanka'); ?></span></p>
                                                    <p class="mb-2"><strong>Delivery Address:</strong> <span class="text-white"><?php echo htmlspecialchars($ord['shipping_address'] ?? 'N/A'); ?></span></p>
                                                    <p class="mb-2"><strong>City:</strong> <span class="text-white"><?php echo htmlspecialchars($ord['city'] ?? 'N/A'); ?></span></p>
                                                    <p class="mb-0"><strong>Postal Code:</strong> <span class="text-info fw-bold"><?php echo htmlspecialchars($ord['postal_code'] ?? 'N/A'); ?></span></p>
                                                </div>
                                                <div class="col-md-6">
                                                    <h6 class="text-success fw-bold border-bottom border-secondary pb-2 mb-3"><i class="bi bi-box-seam me-1"></i> Order Summary & Status</h6>
                                                    <p class="mb-2"><strong>Order Date:</strong> <span class="text-white"><?php echo htmlspecialchars($ord['order_date'] ?? 'N/A'); ?></span></p>
                                                    <p class="mb-2"><strong>Total Amount:</strong> <span class="text-success fw-bold"><?php echo htmlspecialchars($ord['currency_code'] ?? 'LKR') . ' ' . number_format($ord['total_amount'], 2); ?></span></p>
                                                    <p class="mb-2"><strong>Current Status:</strong> <span class="badge bg-<?php echo $badge_class; ?>"><?php echo htmlspecialchars($ord['order_status']); ?></span></p>
                                                    <p class="mb-2"><strong>Tracking Number:</strong> <span class="text-white"><?php echo !empty($ord['tracking_number']) ? htmlspecialchars($ord['tracking_number']) : '<span class="text-secondary fst-italic">Not Assigned</span>'; ?></span></p>
                                                    <p class="mb-2"><strong>Gift Wrapped:</strong> <?php echo (isset($ord['is_gift_wrapped']) && $ord['is_gift_wrapped'] == 1) ? '<span class="text-success fw-bold">Yes</span> (Fee: ' . number_format($ord['gift_wrap_fee'] ?? 0, 2) . ')' : '<span class="text-secondary">No</span>'; ?></p>
                                                    <?php if (!empty($ord['gift_message'])): ?>
                                                        <p class="mt-2 mb-0"><strong>Gift Note:</strong><br><span class="text-secondary small bg-dark p-2 rounded d-block mt-1 border border-secondary"><?php echo htmlspecialchars($ord['gift_message']); ?></span></p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <!-- Ordered Items & Quantity Section -->
                                            <div class="mt-3">
                                                <h6 class="text-success fw-bold border-bottom border-secondary pb-2 mb-3"><i class="bi bi-basket me-1"></i> Ordered Items & Quantities</h6>
                                                <div class="d-flex flex-column gap-2">
                                                    <?php if ($items_query && $items_query->num_rows > 0): ?>
                                                        <?php while($item = $items_query->fetch_assoc()): ?>
                                                            <div class="item-row-card d-flex align-items-center justify-content-between">
                                                                <div class="d-flex align-items-center gap-3">
                                                                    <?php if (!empty($item['main_image'])): ?>
                                                                        <img src="../<?php echo htmlspecialchars($item['main_image']); ?>" alt="Product" class="rounded border border-secondary" style="width: 45px; height: 45px; object-fit: cover;">
                                                                    <?php else: ?>
                                                                        <div class="rounded border border-secondary bg-dark d-flex align-items-center justify-content-center text-secondary" style="width: 45px; height: 45px;">
                                                                            <i class="bi bi-box"></i>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                    <div>
                                                                        <h6 class="mb-0 text-white fw-bold" style="font-size: 0.9rem;"><?php echo htmlspecialchars($item['product_name'] ?? 'Crafted Product'); ?></h6>
                                                                        <small class="text-secondary">
                                                                            Unit Price: <?php echo htmlspecialchars($ord['currency_code'] ?? 'LKR') . ' ' . number_format($item['unit_price'], 2); ?>
                                                                        </small>
                                                                    </div>
                                                                </div>
                                                                <div class="text-end">
                                                                    <span class="badge bg-success bg-opacity-25 text-success border border-success me-2">Qty: <?php echo intval($item['quantity']); ?></span>
                                                                    <span class="fw-bold text-white" style="font-size: 0.9rem;">
                                                                        <?php echo htmlspecialchars($ord['currency_code'] ?? 'LKR') . ' ' . number_format($item['line_total'], 2); ?>
                                                                    </span>
                                                                </div>
                                                            </div>
                                                        <?php endwhile; ?>
                                                    <?php else: ?>
                                                        <p class="text-secondary small fst-italic">No individual item details stored for this order.</p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                        </div>
                                        <div class="modal-footer d-flex justify-content-between">
                                            <button type="button" onclick="window.print()" class="btn btn-outline-success btn-sm px-3">
                                                <i class="bi bi-file-earmark-pdf me-1"></i> Download PDF Invoice
                                            </button>
                                            <button type="button" class="btn btn-secondary btn-sm px-4" data-bs-dismiss="modal">Close</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                <?php else: ?>
                    <!-- ================= MAIN CUSTOMERS LIST VIEW ================= -->
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mb-4 gap-3">
                        <div>
                            <h2 class="fw-bold text-white mb-1"><i class="bi bi-people-fill me-2 text-success"></i> Customer Management</h2>
                            <p class="text-secondary small mb-0">View registered customers, monitor their orders, and manage platform users.</p>
                        </div>
                        
                        <!-- Main Search Form -->
                        <form action="customers.php" method="GET" class="d-flex gap-2" style="width: 100%; max-width: 350px;">
                            <div class="input-group">
                                <input type="text" name="search" class="form-control" placeholder="Search by name or email..." value="<?php echo htmlspecialchars($search_query); ?>">
                                <button class="btn btn-success" type="submit"><i class="bi bi-search"></i></button>
                                <?php if (!empty($search_query)): ?>
                                    <a href="customers.php" class="btn btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>

                    <div class="dark-card p-4">
                        <div class="table-responsive">
                            <table class="table table-dark-custom align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>#ID</th>
                                        <th>Customer Name</th>
                                        <th>Email Address</th>
                                        <th>Total Orders</th>
                                        <th>Account Status</th>
                                        <th>Registered Date</th>
                                        <th class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($result && $result->num_rows > 0): ?>
                                        <?php while($cust = $result->fetch_assoc()): ?>
                                        <tr>
                                            <td class="text-secondary">#<?php echo $cust['user_id']; ?></td>
                                            <td class="fw-bold text-white"><?php echo htmlspecialchars($cust['full_name']); ?></td>
                                            <td class="text-secondary"><?php echo htmlspecialchars($cust['email']); ?></td>
                                            <td><span class="badge bg-secondary"><?php echo $cust['total_orders']; ?> Orders</span></td>
                                            <td>
                                                <?php 
                                                    $c_status = $cust['account_status'] ?? 'Active';
                                                    $c_badge = ($c_status == 'Active') ? 'success' : 'danger';
                                                ?>
                                                <span class="badge bg-<?php echo $c_badge; ?> bg-opacity-25 text-<?php echo $c_badge; ?> border border-<?php echo $c_badge; ?>">
                                                    <?php echo $c_status; ?>
                                                </span>
                                            </td>
                                            <td class="text-secondary small"><?php echo date('Y-m-d H:i', strtotime($cust['created_at'])); ?></td>
                                            <td class="text-center">
                                                <a href="customers.php?view=<?php echo $cust['user_id']; ?>" class="btn btn-sm btn-outline-success px-3 fw-semibold">
                                                    <i class="bi bi-eye me-1"></i> View Profile
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center text-secondary py-5">No customers found in the database.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>