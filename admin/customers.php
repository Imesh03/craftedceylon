<?php
session_start();
require_once '../config/db.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged'])) {
    header("Location: login.php");
    exit();
}

// Handle Order Status Update if requested from customer profile view
if (isset($_POST['update_order_status'])) {
    $order_id = intval($_POST['order_id']);
    $new_status = $_POST['order_status'];
    $customer_id_redirect = intval($_POST['customer_id']);

    $stmt = $conn->prepare("UPDATE orders SET order_status = ? WHERE order_id = ?");
    $stmt->bind_param("si", $new_status, $order_id);
    $stmt->execute();

    // Preserve order search query if active on redirect
    $redirect_url = "customers.php?view=" . $customer_id_redirect;
    if (isset($_POST['order_search']) && !empty($_POST['order_search'])) {
        $redirect_url .= "&order_search=" . urlencode($_POST['order_search']);
    }
    header("Location: " . $redirect_url);
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

// Check if a specific customer's details are requested
$view_customer_id = isset($_GET['view']) ? intval($_GET['view']) : 0;
$customer_details = null;
$customer_orders = null;

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
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Management - Admin Dark Portal</title>
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
            --border-color: rgba(255, 255, 255, 0.08);
        }

        body {
            background-color: var(--bg-dark);
            color: var(--text-main);
            font-family: 'Plus Jakarta Sans', sans-serif;
            min-height: 100vh;
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

        /* Glassmorphism Dark Card */
        .dark-card {
            background: rgba(30, 30, 47, 0.75);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.4);
        }

        /* High-Contrast Table Styling */
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
            padding: 14px 16px;
            border-bottom: 2px solid var(--border-color);
            background-color: rgba(22, 22, 34, 0.5);
            white-space: nowrap;
        }

        .table-dark-custom td {
            padding: 14px 16px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-main);
            vertical-align: middle;
            font-size: 0.9rem;
        }

        .table-dark-custom tbody tr:hover {
            background-color: rgba(255, 255, 255, 0.03);
        }

        /* Customer Avatar Badge */
        .avatar-circle {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: rgba(25, 135, 84, 0.15);
            color: var(--accent-green);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            border: 1px solid rgba(25, 135, 84, 0.25);
            flex-shrink: 0;
        }

        .avatar-large {
            width: 70px;
            height: 70px;
            border-radius: 16px;
            background: rgba(25, 135, 84, 0.2);
            color: var(--accent-green);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            border: 1px solid rgba(25, 135, 84, 0.3);
        }

        /* Compact & Sleek Orders Badge */
        .badge-orders {
            background-color: rgba(13, 202, 240, 0.12);
            color: #0dcaf0;
            border: 1px solid rgba(13, 202, 240, 0.25);
            font-weight: 600;
            padding: 5px 10px;
            border-radius: 8px;
            white-space: nowrap;
            display: inline-flex;
            align-items: center;
            font-size: 0.82rem;
        }

        .form-control, .form-select {
            background-color: #121216;
            border: 1px solid var(--border-color);
            color: var(--text-main);
            border-radius: 8px;
            padding: 8px 12px;
        }

        .form-control:focus, .form-select:focus {
            background-color: #121216;
            border-color: var(--accent-green);
            color: var(--text-main);
            box-shadow: 0 0 0 0.25rem rgba(25, 135, 84, 0.25);
        }

        /* Modal Customization */
        .modal {
            z-index: 1055 !important;
        }
        .modal-backdrop {
            z-index: 1050 !important;
        }
        .modal-content {
            background-color: #1e1e2f;
            color: var(--text-main);
            border: 1px solid var(--border-color);
            border-radius: 16px;
        }
        .modal-header, .modal-footer {
            border-color: var(--border-color);
        }

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
                        <a href="dashboard.php" class="nav-link">
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
                        <a href="customers.php" class="nav-link active">
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

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 p-4 p-md-5">
                
                <?php if ($view_customer_id > 0 && $customer_details): ?>
                    <!-- ================= SINGLE CUSTOMER DETAILED VIEW ================= -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h2 class="fw-bold text-white mb-1"><i class="bi bi-person-lines-fill me-2 text-success"></i> Customer Profile Details</h2>
                            <p class="text-secondary small mb-0">Detailed view of personal information and order history.</p>
                        </div>
                        <a href="customers.php" class="btn btn-outline-light btn-sm px-3">
                            <i class="bi bi-arrow-left me-1"></i> Back to Customers List
                        </a>
                    </div>

                    <!-- Customer Personal Info Card -->
                    <div class="dark-card p-4 mb-4">
                        <div class="row align-items-center">
                            <div class="col-md-auto text-center mb-3 mb-md-0">
                                <div class="avatar-large mx-auto">
                                    <i class="bi bi-person-fill"></i>
                                </div>
                            </div>
                            <div class="col-md">
                                <h3 class="fw-bold text-white mb-1"><?php echo htmlspecialchars($customer_details['full_name']); ?></h3>
                                <p class="text-success mb-2"><i class="bi bi-envelope me-1"></i> <?php echo htmlspecialchars($customer_details['email']); ?></p>
                                <div class="d-flex flex-wrap gap-3 text-secondary small">
                                    <div><strong class="text-light">Customer ID:</strong> #<?php echo $customer_details['user_id']; ?></div>
                                    <div><strong class="text-light">Registered Date:</strong> <?php echo date('Y-m-d H:i', strtotime($customer_details['created_at'])); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Customer Orders Section with Reference Search Bar -->
                    <div class="dark-card p-4">
                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mb-4 gap-3">
                            <h5 class="fw-bold text-white mb-0"><i class="bi bi-bag-check me-2 text-success"></i> Customer Orders History</h5>
                            
                            <!-- Search by Reference ID -->
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
                                        <th>View Details</th>
                                        <th>Manage Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($customer_orders && $customer_orders->num_rows > 0): ?>
                                        <?php while($ord = $customer_orders->fetch_assoc()): ?>
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
                                            <td>
                                                <!-- Button to trigger View Full Details Modal -->
                                                <button type="button" class="btn btn-sm btn-outline-info fw-semibold px-2 py-1" style="font-size: 0.8rem;" data-bs-toggle="modal" data-bs-target="#orderDetailsModal<?php echo $ord['order_id']; ?>">
                                                    <i class="bi bi-eye me-1"></i> View Details
                                                </button>
                                            </td>
                                            <td>
                                                <!-- Order Status Control Form -->
                                                <form action="customers.php" method="POST" class="d-flex gap-2 align-items-center">
                                                    <input type="hidden" name="order_id" value="<?php echo $ord['order_id']; ?>">
                                                    <input type="hidden" name="customer_id" value="<?php echo $view_customer_id; ?>">
                                                    <?php if (isset($_GET['order_search']) && !empty($_GET['order_search'])): ?>
                                                        <input type="hidden" name="order_search" value="<?php echo htmlspecialchars($_GET['order_search']); ?>">
                                                    <?php endif; ?>
                                                    <select name="order_status" class="form-select form-select-sm" style="width: 110px; font-size: 0.8rem;">
                                                        <option value="Processing" <?php if($st=='Processing') echo 'selected'; ?>>Processing</option>
                                                        <option value="Shipped" <?php if($st=='Shipped') echo 'selected'; ?>>Shipped</option>
                                                        <option value="Delivered" <?php if($st=='Delivered') echo 'selected'; ?>>Delivered</option>
                                                        <option value="Cancelled" <?php if($st=='Cancelled') echo 'selected'; ?>>Cancelled</option>
                                                    </select>
                                                    <button type="submit" name="update_order_status" class="btn btn-sm btn-success fw-bold px-2 py-1" style="font-size: 0.8rem;">
                                                        <i class="bi bi-check-lg"></i> Save
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-secondary py-4">No orders found matching your search criteria.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Modals for Viewing Full Order Details -->
                    <?php 
                    if ($customer_orders && $customer_orders->num_rows > 0) {
                        $customer_orders->data_seek(0);
                        while($ord = $customer_orders->fetch_assoc()) {
                            $st = $ord['order_status'];
                            $b_class = 'warning text-dark';
                            if ($st == 'Delivered') $b_class = 'success bg-opacity-25 text-success border border-success';
                            elseif ($st == 'Shipped') $b_class = 'info bg-opacity-25 text-info border border-info';
                            elseif ($st == 'Cancelled') $b_class = 'danger';
                    ?>
                    <div class="modal fade" id="orderDetailsModal<?php echo $ord['order_id']; ?>" tabindex="-1" aria-labelledby="modalLabel<?php echo $ord['order_id']; ?>" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title fw-bold text-success" id="modalLabel<?php echo $ord['order_id']; ?>">
                                        <i class="bi bi-receipt me-2"></i> Order Details: <?php echo htmlspecialchars($ord['order_reference']); ?>
                                    </h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body p-4">
                                    <div class="row g-4">
                                        <div class="col-md-6">
                                            <h6 class="text-success fw-bold border-bottom border-secondary pb-2 mb-3"><i class="bi bi-person-badge me-1"></i> Customer & Shipping Information</h6>
                                            <p class="mb-2"><strong>Full Name:</strong> <span class="text-white"><?php echo htmlspecialchars($ord['shipping_name'] ?? $customer_details['full_name']); ?></span></p>
                                            <p class="mb-2"><strong>Email:</strong> <span class="text-secondary"><?php echo htmlspecialchars($ord['shipping_email'] ?? $customer_details['email']); ?></span></p>
                                            <p class="mb-2"><strong>Phone Number:</strong> <span class="text-warning fw-bold"><?php echo htmlspecialchars($ord['shipping_phone'] ?? 'N/A'); ?></span></p>
                                            <p class="mb-2"><strong>Country / Region:</strong> <span class="text-success fw-bold"><?php echo htmlspecialchars($ord['shipping_country'] ?? 'N/A'); ?></span></p>
                                            <p class="mb-2"><strong>Delivery Address:</strong> <span class="text-white"><?php echo htmlspecialchars($ord['shipping_address'] ?? 'N/A'); ?></span></p>
                                            <p class="mb-2"><strong>City:</strong> <span class="text-white"><?php echo htmlspecialchars($ord['city'] ?? 'N/A'); ?></span></p>
                                            <p class="mb-0"><strong>Postal Code:</strong> <span class="text-info fw-bold"><?php echo htmlspecialchars($ord['postal_code'] ?? 'N/A'); ?></span></p>
                                        </div>
                                        <div class="col-md-6">
                                            <h6 class="text-success fw-bold border-bottom border-secondary pb-2 mb-3"><i class="bi bi-box-seam me-1"></i> Order Summary & Status</h6>
                                            <p class="mb-2"><strong>Order Date:</strong> <span class="text-white"><?php echo htmlspecialchars($ord['order_date'] ?? 'N/A'); ?></span></p>
                                            <p class="mb-2"><strong>Total Amount:</strong> <span class="text-success fw-bold"><?php echo htmlspecialchars($ord['currency_code'] ?? 'LKR') . ' ' . number_format($ord['total_amount'], 2); ?></span></p>
                                            <p class="mb-2"><strong>Current Status:</strong> <span class="badge bg-<?php echo $b_class; ?>"><?php echo htmlspecialchars($ord['order_status']); ?></span></p>
                                            <p class="mb-2"><strong>Tracking Number:</strong> <span class="text-white"><?php echo !empty($ord['tracking_number']) ? htmlspecialchars($ord['tracking_number']) : '<span class="text-secondary fst-italic">Not Assigned</span>'; ?></span></p>
                                            <p class="mb-2"><strong>Gift Wrapped:</strong> <?php echo (isset($ord['is_gift_wrapped']) && $ord['is_gift_wrapped'] == 1) ? '<span class="text-success fw-bold">Yes</span> (Fee: ' . number_format($ord['gift_wrap_fee'] ?? 0, 2) . ')' : '<span class="text-secondary">No</span>'; ?></p>
                                            <?php if (!empty($ord['gift_message'])): ?>
                                                <p class="mt-3 mb-0"><strong>Gift Note:</strong><br><span class="text-secondary small bg-dark p-2 rounded d-block mt-1 border border-secondary"><?php echo htmlspecialchars($ord['gift_message']); ?></span></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary btn-sm px-4" data-bs-dismiss="modal">Close</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php 
                        }
                    } 
                    ?>

                <?php else: ?>
                    <!-- ================= ALL CUSTOMERS LIST VIEW ================= -->
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mb-4 gap-3">
                        <div>
                            <h2 class="fw-bold text-white mb-1">Registered Customers Management</h2>
                            <p class="text-secondary small mb-0">View customer profiles and manage their shopping activities.</p>
                        </div>

                        <!-- Search Form by Name or Email -->
                        <form action="customers.php" method="GET" class="d-flex gap-2" style="width: 100%; max-width: 350px;">
                            <div class="input-group input-group-sm">
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
                                        <th style="width: 120px;">Customer ID</th>
                                        <th>Full Name</th>
                                        <th>Email Address</th>
                                        <th style="width: 140px;">Total Orders</th>
                                        <th style="width: 150px;">Registered Date</th>
                                        <th style="width: 100px; text-align: center;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($result && $result->num_rows > 0): ?>
                                        <?php while($customer = $result->fetch_assoc()): ?>
                                        <tr>
                                            <td class="fw-bold text-secondary">#<?php echo $customer['user_id']; ?></td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-circle me-2">
                                                        <i class="bi bi-person-fill"></i>
                                                    </div>
                                                    <span class="fw-semibold text-white"><?php echo htmlspecialchars($customer['full_name']); ?></span>
                                                </div>
                                            </td>
                                            <td><span class="text-light opacity-75"><?php echo htmlspecialchars($customer['email']); ?></span></td>
                                            <td>
                                                <span class="badge-orders">
                                                    <i class="bi bi-bag-check me-1"></i><?php echo $customer['total_orders']; ?> Orders
                                                </span>
                                            </td>
                                            <td>
                                                <span class="text-secondary small">
                                                    <i class="bi bi-calendar3 me-1"></i> 
                                                    <?php echo isset($customer['created_at']) ? date('Y-m-d', strtotime($customer['created_at'])) : 'N/A'; ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <a href="customers.php?view=<?php echo $customer['user_id']; ?>" class="btn btn-sm btn-outline-info fw-semibold px-2 py-1" style="font-size: 0.8rem;" title="View Profile & Orders">
                                                    <i class="bi bi-eye me-1"></i> View
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-secondary py-5">No registered customers found matching your search criteria.</td>
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
    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>