<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['admin_logged'])) {
    header("Location: login.php");
    exit();
}

// Fetch counts and metrics
$product_count = $conn->query("SELECT COUNT(*) as count FROM products")->fetch_assoc()['count'];
$order_count = $conn->query("SELECT COUNT(*) as count FROM orders")->fetch_assoc()['count'];
$customer_count = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
$total_revenue = $conn->query("SELECT SUM(total_amount) as total FROM orders WHERE currency_code='LKR'")->fetch_assoc()['total'];
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
            --bg-dark: #121216;
            --bg-card: #1e1e2f;
            --bg-sidebar: #161622;
            --text-main: #ffffff;
            --text-sub: #adb5bd;
            --accent-green: #198754;
            --accent-blue: #0d6efd;
            --border-color: rgba(255, 255, 255, 0.08);
        }

        body {
            background-color: var(--bg-dark);
            color: var(--text-main);
            font-family: 'Plus Jakarta Sans', sans-serif;
            min-vh: 100vh;
        }

        /* Sidebar Styling */
        .sidebar {
            background-color: var(--bg-sidebar);
            border-right: 1px solid var(--border-color);
            backdrop-filter: blur(10px);
        }

        .nav-link {
            color: var(--text-sub);
            border-radius: 10px;
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
            box-shadow: 0 4px 15px rgba(25, 135, 84, 0.3);
        }

        /* Stat Card Glassmorphism Styling */
        .stat-card {
            background: rgba(30, 30, 47, 0.75);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 24px;
            position: relative;
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .stat-card:hover {
            transform: translateY(-6px);
            border-color: rgba(25, 135, 84, 0.4);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.5), 0 0 20px rgba(25, 135, 84, 0.15);
        }

        /* Icon Styling */
        .stat-icon {
            width: 52px;
            height: 52px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            transition: transform 0.3s ease;
        }

        .stat-card:hover .stat-icon {
            transform: scale(1.1);
        }

        /* High Contrast Text Custom Utilities */
        .text-high-contrast {
            color: var(--text-main) !important;
        }

        .text-subtle {
            color: var(--text-sub) !important;
        }

        /* Glow Elements */
        .glow-success {
            box-shadow: 0 0 15px rgba(25, 135, 84, 0.2);
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
                    <li class="nav-item mt-5">
                        <a href="logout.php" class="nav-link text-danger">
                            <i class="bi bi-box-arrow-right me-2"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Main Content Area -->
            <div class="col-md-9 col-lg-10 p-4 p-md-5">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="fw-bold text-high-contrast mb-1">Dashboard Overview</h2>
                        <p class="text-subtle small mb-0">Welcome back! Real-time performance metrics for CraftedCeylon platform.</p>
                    </div>
                </div>

                <!-- Statistics Grid -->
                <div class="row g-4 mb-5">
                    <!-- Total Products -->
                    <div class="col-sm-6 col-xl-3">
                        <div class="stat-card">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <span class="text-subtle small fw-semibold text-uppercase tracking-wider">Total Products</span>
                                <div class="stat-icon bg-success bg-opacity-10 text-success">
                                    <i class="bi bi-box-seam"></i>
                                </div>
                            </div>
                            <h2 class="fw-bold text-high-contrast mb-0"><?php echo number_format($product_count); ?></h2>
                        </div>
                    </div>

                    <!-- Total Orders -->
                    <div class="col-sm-6 col-xl-3">
                        <div class="stat-card">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <span class="text-subtle small fw-semibold text-uppercase tracking-wider">Total Orders</span>
                                <div class="stat-icon bg-info bg-opacity-10 text-info">
                                    <i class="bi bi-cart-check"></i>
                                </div>
                            </div>
                            <h2 class="fw-bold text-high-contrast mb-0"><?php echo number_format($order_count); ?></h2>
                        </div>
                    </div>

                    <!-- Registered Customers -->
                    <div class="col-sm-6 col-xl-3">
                        <div class="stat-card">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <span class="text-subtle small fw-semibold text-uppercase tracking-wider">Customers</span>
                                <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                                    <i class="bi bi-people"></i>
                                </div>
                            </div>
                            <h2 class="fw-bold text-high-contrast mb-0"><?php echo number_format($customer_count); ?></h2>
                        </div>
                    </div>

                    <!-- Total Revenue -->
                    <div class="col-sm-6 col-xl-3">
                        <div class="stat-card">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <span class="text-subtle small fw-semibold text-uppercase tracking-wider">Total Revenue</span>
                                <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                                    <i class="bi bi-currency-dollar"></i>
                                </div>
                            </div>
                            <h2 class="fw-bold text-success mb-0"><small class="fs-6 text-subtle">Rs.</small> <?php echo number_format($total_revenue ?? 0, 2); ?></h2>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>