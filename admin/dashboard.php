<?php
session_start();
require_once '../config/db.php';
date_default_timezone_set('Asia/Colombo');

if (!isset($_SESSION['admin_logged'])) {
    header("Location: login.php");
    exit();
}

$current_admin_id = $_SESSION['admin_id'];

// 1. Check if the logged-in admin is a Super Admin
$role_stmt = $conn->prepare("SELECT role, username FROM admins WHERE admin_id = ?");
$role_stmt->bind_param("i", $current_admin_id);
$role_stmt->execute();
$current_admin = $role_stmt->get_result()->fetch_assoc();
$is_super_admin = ($current_admin && $current_admin['role'] === 'Super Admin');

// Fetch counts and metrics
$product_count = $conn->query("SELECT COUNT(*) as count FROM products")->fetch_assoc()['count'] ?? 0;
$order_count = $conn->query("SELECT COUNT(*) as count FROM orders")->fetch_assoc()['count'] ?? 0;
$customer_count = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'] ?? 0;
$total_revenue = $conn->query("SELECT SUM(total_amount) as total FROM orders WHERE currency_code='LKR'")->fetch_assoc()['total'] ?? 0;

// 2. Fetch Low Stock Items (Stock quantity <= 5)
$low_stock_query = "SELECT product_id, product_name, stock_quantity FROM products WHERE stock_quantity <= 5 ORDER BY stock_quantity ASC";
$low_stock_result = $conn->query($low_stock_query);

if (!$low_stock_result) {
    $low_stock_query = "SELECT product_id, product_name, quantity as stock_quantity FROM products WHERE quantity <= 5 ORDER BY quantity ASC";
    $low_stock_result = $conn->query($low_stock_query);
}

// 3. Fetch Active Admins ONLY FOR SUPER ADMIN
$active_admins_result = null;
$all_admins_for_modal = [];
if ($is_super_admin) {
    $active_admins_query = "SELECT admin_id, username, email, role, created_at FROM admins WHERE account_status = 'Active' ORDER BY admin_id DESC";
    $active_admins_result = $conn->query($active_admins_query);
    
    // Fetch all admins for modal popups if clicked from dashboard
    $all_res = $conn->query("SELECT admin_id, username, email FROM admins");
    while($ar = $all_res->fetch_assoc()){
        $all_admins_for_modal[] = $ar;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - CraftedCeylon</title>
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

        /* Fixed Sidebar Styling */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            background-color: var(--bg-sidebar);
            border-right: 1px solid var(--border-color);
            backdrop-filter: blur(15px);
            overflow-y: auto;
            z-index: 1030;
        }

        /* Main Content Offset for Fixed Sidebar */
        .main-content-wrapper {
            margin-left: 16.666667%; /* matches col-lg-2 width */
        }
        @media (max-width: 768px) {
            .main-content-wrapper {
                margin-left: 0;
            }
            .sidebar {
                position: relative;
                height: auto;
            }
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

        /* Creative Stat Card Styling */
        .stat-card {
            background: var(--bg-card);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--border-color);
            border-radius: 18px;
            padding: 24px;
            position: relative;
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 16px 40px rgba(0, 0, 0, 0.5);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: linear-gradient(90deg, transparent, var(--accent-green), transparent);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-6px);
            border-color: rgba(25, 135, 84, 0.4);
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.7), 0 0 25px rgba(25, 135, 84, 0.2);
        }

        .stat-card:hover::before {
            opacity: 1;
        }

        .stat-icon {
            width: 54px;
            height: 54px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            transition: transform 0.3s ease;
        }

        .stat-card:hover .stat-icon {
            transform: scale(1.1) rotate(5deg);
        }

        .glow-success {
            box-shadow: 0 0 20px rgba(25, 135, 84, 0.3);
        }

        /* Dashboard Glass Cards */
        .dashboard-glass-card {
            background: var(--bg-card);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--border-color);
            border-radius: 18px;
            padding: 24px;
            box-shadow: 0 16px 40px rgba(0, 0, 0, 0.5);
        }

        /* Table Design */
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
            padding: 16px 20px;
            border-bottom: 2px solid var(--border-color);
            background-color: rgba(19, 19, 29, 0.7);
        }

        .table-dark-custom td {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-main);
            vertical-align: middle;
        }

        .table-dark-custom tbody tr:hover {
            background-color: rgba(255, 255, 255, 0.04);
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

        /* Dark Modal Styling */
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
    </style>
</head>
<body>
    <div class="container-fluid p-0">
        <div class="row g-0">
            <!-- Fixed Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar p-3">
                <div class="d-flex align-items-center gap-2 mb-4 px-2">
                    <div class="bg-success text-white rounded-3 p-2 d-flex align-items-center justify-content-center glow-success">
                        <i class="bi bi-flower1 fs-4"></i>
                    </div>
                    <h5 class="fw-bold text-white mb-0">CraftedCeylon</h5>
                </div>
                <ul class="nav flex-column gap-2">
                    <li class="nav-item">
                        <a href="dashboard.php" class="nav-link active">
                            <i class="bi bi-speedometer2 me-2"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="products.php" class="nav-link">
                            <i class="bi bi-box-seam me-2"></i> Products
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="orders.php" class="nav-link">
                            <i class="bi bi-cart-check me-2"></i> Orders & Tracking
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="inventory.php" class="nav-link">
                            <i class="bi bi-stack me-2"></i> Inventory Stock
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="customers.php" class="nav-link">
                            <i class="bi bi-people me-2"></i> Customers
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="reviews.php" class="nav-link">
                            <i class="bi bi-star-half me-2"></i> Customer Reviews
                        </a>
                    </li>

                    <!-- Super Admin Only Menu Item -->
                    <?php if ($is_super_admin): ?>
                    <li class="nav-item">
                        <a href="manage-admins.php" class="nav-link text-warning">
                            <i class="bi bi-shield-lock-fill me-2"></i> Manage Admins
                        </a>
                    </li>
                    <?php endif; ?>

                    <li class="nav-item mt-5">
                        <a href="admin-logout.php" class="nav-link text-danger">
                            <i class="bi bi-box-arrow-right me-2"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Main Content Area with Left Margin Offset -->
            <div class="col-md-9 col-lg-10 ms-auto main-content-wrapper p-4 p-md-5">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="fw-bold text-white mb-1">Dashboard Overview</h2>
                        <p class="text-secondary small mb-0">Welcome back, <span class="text-white fw-bold"><?php echo htmlspecialchars($current_admin['username'] ?? 'Admin'); ?></span>! Real-time performance metrics for CraftedCeylon platform.</p>
                    </div>
                </div>

                <!-- Statistics Grid -->
                <div class="row g-4 mb-5">
                    <!-- Total Products -->
                    <div class="col-sm-6 col-xl-3">
                        <div class="stat-card">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <span class="text-secondary small fw-semibold text-uppercase tracking-wider">Total Products</span>
                                <div class="stat-icon bg-success bg-opacity-10 text-success border border-success border-opacity-25">
                                    <i class="bi bi-box-seam"></i>
                                </div>
                            </div>
                            <h2 class="fw-bold text-white mb-0"><?php echo number_format($product_count); ?></h2>
                        </div>
                    </div>

                    <!-- Total Orders -->
                    <div class="col-sm-6 col-xl-3">
                        <div class="stat-card">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <span class="text-secondary small fw-semibold text-uppercase tracking-wider">Total Orders</span>
                                <div class="stat-icon bg-info bg-opacity-10 text-info border border-info border-opacity-25">
                                    <i class="bi bi-cart-check"></i>
                                </div>
                            </div>
                            <h2 class="fw-bold text-white mb-0"><?php echo number_format($order_count); ?></h2>
                        </div>
                    </div>

                    <!-- Registered Customers -->
                    <div class="col-sm-6 col-xl-3">
                        <div class="stat-card">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <span class="text-secondary small fw-semibold text-uppercase tracking-wider">Customers</span>
                                <div class="stat-icon bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25">
                                    <i class="bi bi-people"></i>
                                </div>
                            </div>
                            <h2 class="fw-bold text-white mb-0"><?php echo number_format($customer_count); ?></h2>
                        </div>
                    </div>

                    <!-- Total Revenue -->
                    <div class="col-sm-6 col-xl-3">
                        <div class="stat-card">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <span class="text-secondary small fw-semibold text-uppercase tracking-wider">Total Revenue</span>
                                <div class="stat-icon bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25">
                                    <i class="bi bi-currency-dollar"></i>
                                </div>
                            </div>
                            <h2 class="fw-bold text-success mb-0"><small class="fs-6 text-secondary">Rs.</small> <?php echo number_format($total_revenue ?? 0, 2); ?></h2>
                        </div>
                    </div>
                </div>

                <!-- SECTION: Low Stock Alert & Active Admins Grid -->
                <div class="row g-4">
                    
                    <!-- 1. LOW STOCK INVENTORY ALERT -->
                    <div class="<?php echo $is_super_admin ? 'col-lg-6' : 'col-12'; ?>">
                        <div class="dashboard-glass-card h-100">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <h5 class="fw-bold text-white mb-0 d-flex align-items-center gap-2">
                                    <i class="bi bi-exclamation-triangle-fill text-danger fs-5"></i> Low Stock Alert (≤ 5 Items)
                                </h5>
                                <a href="inventory.php" class="btn btn-sm btn-outline-success">View All</a>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table table-dark-custom align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>#ID</th>
                                            <th>Product Name</th>
                                            <th class="text-end">Current Stock</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($low_stock_result && $low_stock_result->num_rows > 0): ?>
                                            <?php while ($item = $low_stock_result->fetch_assoc()): ?>
                                                <tr>
                                                    <td class="text-secondary small">#<?php echo $item['product_id']; ?></td>
                                                    <td class="fw-bold text-white"><?php echo htmlspecialchars($item['product_name']); ?></td>
                                                    <td class="text-end">
                                                        <span class="badge bg-danger bg-opacity-25 text-danger border border-danger px-2 py-1">
                                                            <i class="bi bi-box-seam me-1"></i> <?php echo number_format($item['stock_quantity']); ?> Left
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="3" class="text-center text-secondary py-4">
                                                    <i class="bi bi-check-circle-fill text-success fs-5 d-block mb-1"></i>
                                                    All inventory stock levels are healthy (> 5 items).
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- 2. ACTIVE ADMINISTRATORS LIST (Super Admin Only) -->
                    <?php if ($is_super_admin): ?>
                    <div class="col-lg-6">
                        <div class="dashboard-glass-card h-100">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <h5 class="fw-bold text-white mb-0 d-flex align-items-center gap-2">
                                    <i class="bi bi-shield-check text-warning fs-5"></i> Active System Admins
                                </h5>
                                <a href="manage-admins.php" class="btn btn-sm btn-outline-warning">Manage</a>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-dark-custom align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Admin Name</th>
                                            <th>Email</th>
                                            <th class="text-end">Role</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($active_admins_result && $active_admins_result->num_rows > 0): ?>
                                            <?php while ($active_admin = $active_admins_result->fetch_assoc()): ?>
                                                <tr>
                                                    <td class="fw-bold text-white">
                                                        <a href="#" class="admin-link fw-bold" data-bs-toggle="modal" data-bs-target="#activityModal<?php echo $active_admin['admin_id']; ?>">
                                                            <i class="bi bi-person-circle text-secondary me-1"></i>
                                                            <?php echo htmlspecialchars($active_admin['username']); ?> <i class="bi bi-box-arrow-up-right small text-info"></i>
                                                        </a>
                                                    </td>
                                                    <td class="text-secondary small"><?php echo htmlspecialchars($active_admin['email']); ?></td>
                                                    <td class="text-end">
                                                        <?php if ($active_admin['role'] === 'Super Admin'): ?>
                                                            <span class="badge bg-warning bg-opacity-25 text-warning border border-warning px-2 py-1">Super Admin</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-info bg-opacity-25 text-info border border-info px-2 py-1">Admin</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="3" class="text-center text-secondary py-4">No active administrators found.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>

    <!-- Admin Activity Modals for Dashboard Quick View -->
    <?php if ($is_super_admin && !empty($all_admins_for_modal)): ?>
        <?php foreach ($all_admins_for_modal as $ar): ?>
            <div class="modal fade" id="activityModal<?php echo $ar['admin_id']; ?>" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content p-3">
                        <div class="modal-header border-bottom border-secondary border-opacity-25 pb-3">
                            <h5 class="modal-title fw-bold text-info d-flex align-items-center gap-2">
                                <i class="bi bi-clock-history"></i> Activity Logs: <?php echo htmlspecialchars($ar['username']); ?> (<?php echo htmlspecialchars($ar['email']); ?>)
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        
                        <div class="modal-body py-3">
                            <div class="table-responsive" style="max-height: 350px; overflow-y: auto;">
                                <table class="table table-dark-custom table-hover table-sm align-middle mb-0">
                                    <thead>
                                        <tr style="font-size: 12px;">
                                            <th>Activity / Action</th>
                                            <th>Login Time</th>
                                            <th>Logout Time</th>
                                            <th>IP Address</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $m_id = $ar['admin_id'];
                                        $m_res = $conn->query("SELECT * FROM admin_activity_logs WHERE admin_id = $m_id ORDER BY log_id DESC LIMIT 20");
                                        if ($m_res && $m_res->num_rows > 0) {
                                            while ($mlog = $m_res->fetch_assoc()) {
                                                echo '<tr style="font-size: 13px;">';
                                                echo '<td><span class="badge bg-info bg-opacity-25 text-info border border-info px-2 py-1">' . htmlspecialchars($mlog['activity_description'] ?? 'Logged In') . '</span></td>';
                                                echo '<td class="text-success small">' . htmlspecialchars($mlog['login_time'] ?? '-') . '</td>';
                                                echo '<td class="text-danger small">' . htmlspecialchars($mlog['logout_time'] ?? 'Active Session') . '</td>';
                                                echo '<td class="text-secondary small">' . htmlspecialchars($mlog['ip_address'] ?? 'N/A') . '</td>';
                                                echo '</tr>';
                                            }
                                        } else {
                                            echo '<tr><td colspan="4" class="text-center text-secondary py-4">No activity logs found for this administrator.</td></tr>';
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="modal-footer border-top border-secondary border-opacity-25 pt-3">
                            <button type="button" class="btn btn-secondary btn-sm rounded-3 px-4" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>