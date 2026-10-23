<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['admin_logged'])) {
    header("Location: login.php");
    exit();
}

// Handle Order Status Update
if (isset($_POST['update_status'])) {
    $order_id = intval($_POST['order_id']);
    $new_status = $_POST['order_status'];
    $tracking_number = trim($_POST['tracking_number']);

    $stmt = $conn->prepare("UPDATE orders SET order_status = ?, tracking_number = ? WHERE order_id = ?");
    $stmt->bind_param("ssi", $new_status, $tracking_number, $order_id);
    $stmt->execute();
    
    // Preserve search query on redirect if active
    $redirect_url = "orders.php";
    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $redirect_url .= "?search=" . urlencode($_GET['search']);
    }
    header("Location: " . $redirect_url);
    exit();
}

// Search and Filter Handling - Only show orders that are NOT Delivered or Cancelled
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$sql = "SELECT * FROM orders WHERE order_status NOT IN ('Delivered', 'Cancelled')";

if (!empty($search_query)) {
    $escaped_search = $conn->real_escape_string($search_query);
    $sql .= " AND (order_reference LIKE '%$escaped_search%' OR shipping_name LIKE '%$escaped_search%' OR full_name LIKE '%$escaped_search%')";
}

$sql .= " ORDER BY order_date DESC";
$orders_result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders & Tracking - Admin Dark Portal</title>
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

        /* Compact Form Controls for Action Column */
        .action-form .form-select, 
        .action-form .form-control {
            background-color: #121216;
            border: 1px solid var(--border-color);
            color: var(--text-main);
            border-radius: 6px;
            font-size: 0.8rem;
            padding: 5px 8px;
        }

        .action-form .form-select:focus, 
        .action-form .form-control:focus {
            background-color: #121216;
            border-color: var(--accent-green);
            color: var(--text-main);
            box-shadow: 0 0 0 0.2rem rgba(25, 135, 84, 0.25);
        }

        /* Custom Modal Styling for Dark Theme */
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
                        <a href="orders.php" class="nav-link active">
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

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 p-4 p-md-5">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mb-4 gap-3">
                    <div>
                        <h2 class="fw-bold text-white mb-1">Customer Orders & Fulfillment</h2>
                        <p class="text-secondary small mb-0">Monitor active order statuses and assign shipment tracking numbers.</p>
                    </div>

                    <!-- Search Form by Reference ID or Name -->
                    <form action="orders.php" method="GET" class="d-flex gap-2" style="width: 100%; max-width: 350px;">
                        <div class="input-group input-group-sm">
                            <input type="text" name="search" class="form-control bg-dark text-white border-secondary" placeholder="Search by Ref ID or Name..." value="<?php echo htmlspecialchars($search_query); ?>">
                            <button class="btn btn-success" type="submit"><i class="bi bi-search"></i></button>
                            <?php if (!empty($search_query)): ?>
                                <a href="orders.php" class="btn btn-outline-secondary" title="Reset Search"><i class="bi bi-x-lg"></i></a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
                
                <div class="dark-card p-3 p-md-4">
                    <div class="table-responsive">
                        <table class="table table-dark-custom align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Ref ID</th>
                                    <th>Customer</th>
                                    <th>Total Amount</th>
                                    <th>Status</th>
                                    <th>Customer Details</th>
                                    <th style="min-width: 280px;">Action / Status Update</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                if ($orders_result && $orders_result->num_rows > 0): 
                                    while($order = $orders_result->fetch_assoc()): 
                                ?>
                                <tr>
                                    <td class="fw-bold text-white"><?php echo htmlspecialchars($order['order_reference']); ?></td>
                                    <td>
                                        <span class="text-white fw-semibold"><?php echo htmlspecialchars($order['shipping_name'] ?? $order['full_name'] ?? 'N/A'); ?></span><br>
                                        <small class="text-secondary"><i class="bi bi-telephone me-1"></i><?php echo htmlspecialchars($order['shipping_phone'] ?? 'N/A'); ?></small>
                                    </td>
                                    <td class="fw-bold text-success">
                                        <?php echo htmlspecialchars($order['currency_code'] ?? 'LKR') . ' ' . number_format($order['total_amount'], 2); ?>
                                    </td>
                                    <td>
                                        <?php 
                                            $status = $order['order_status'];
                                            $badge_class = 'warning text-dark';
                                            if ($status == 'Shipped') $badge_class = 'info bg-opacity-25 text-info border border-info';
                                        ?>
                                        <span class="badge bg-<?php echo $badge_class; ?>">
                                            <?php echo htmlspecialchars($status); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <!-- Button to trigger View Full Details Modal -->
                                        <button type="button" class="btn btn-sm btn-outline-info fw-semibold px-2 py-1" style="font-size: 0.8rem;" data-bs-toggle="modal" data-bs-target="#orderDetailsModal<?php echo $order['order_id']; ?>">
                                            <i class="bi bi-eye me-1"></i> View Details
                                        </button>
                                    </td>
                                    <td>
                                        <form action="orders.php<?php echo !empty($search_query) ? '?search='.urlencode($search_query) : ''; ?>" method="POST" class="action-form d-flex gap-1 align-items-center">
                                            <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                            <select name="order_status" class="form-select" style="width: 105px;">
                                                <option value="Processing" <?php if($order['order_status']=='Processing') echo 'selected'; ?>>Processing</option>
                                                <option value="Shipped" <?php if($order['order_status']=='Shipped') echo 'selected'; ?>>Shipped</option>
                                                <option value="Delivered">Delivered</option>
                                                <option value="Cancelled">Cancelled</option>
                                            </select>
                                            <input type="text" name="tracking_number" value="<?php echo htmlspecialchars($order['tracking_number'] ?? ''); ?>" placeholder="Tracking No" class="form-control" style="width: 105px;">
                                            <button type="submit" name="update_status" class="btn btn-sm btn-success fw-bold px-2 py-1" style="font-size: 0.8rem;" title="Save Status">
                                                <i class="bi bi-check-lg"></i> Save
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php 
                                    endwhile; 
                                else: 
                                ?>
                                <tr>
                                    <td colspan="6" class="text-center text-secondary py-5">No active customer orders found.</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modals rendered completely OUTSIDE the table -->
    <?php 
    if ($orders_result && $orders_result->num_rows > 0) {
        $orders_result->data_seek(0);
        while($order = $orders_result->fetch_assoc()) {
            $status = $order['order_status'];
            $badge_class = 'warning text-dark';
            if ($status == 'Shipped') $badge_class = 'info bg-opacity-25 text-info border border-info';
    ?>
    <div class="modal fade" id="orderDetailsModal<?php echo $order['order_id']; ?>" tabindex="-1" aria-labelledby="modalLabel<?php echo $order['order_id']; ?>" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-success" id="modalLabel<?php echo $order['order_id']; ?>">
                        <i class="bi bi-receipt me-2"></i> Order Details: <?php echo htmlspecialchars($order['order_reference']); ?>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <h6 class="text-success fw-bold border-bottom border-secondary pb-2 mb-3"><i class="bi bi-person-badge me-1"></i> Customer & Shipping Information</h6>
                            <p class="mb-2"><strong>Full Name:</strong> <span class="text-white"><?php echo htmlspecialchars($order['shipping_name'] ?? 'N/A'); ?></span></p>
                            <p class="mb-2"><strong>Email:</strong> <span class="text-secondary"><?php echo htmlspecialchars($order['shipping_email'] ?? $order['email'] ?? 'N/A'); ?></span></p>
                            <p class="mb-2"><strong>Phone Number:</strong> <span class="text-warning fw-bold"><?php echo htmlspecialchars($order['shipping_phone'] ?? 'N/A'); ?></span></p>
                            <p class="mb-2"><strong>Country / Region:</strong> <span class="text-success fw-bold"><?php echo htmlspecialchars($order['shipping_country'] ?? 'N/A'); ?></span></p>
                            <p class="mb-2"><strong>Delivery Address:</strong> <span class="text-white"><?php echo htmlspecialchars($order['shipping_address'] ?? 'N/A'); ?></span></p>
                            <p class="mb-2"><strong>City:</strong> <span class="text-white"><?php echo htmlspecialchars($order['city'] ?? 'N/A'); ?></span></p>
                            <p class="mb-0"><strong>Postal Code:</strong> <span class="text-info fw-bold"><?php echo htmlspecialchars($order['postal_code'] ?? 'N/A'); ?></span></p>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-success fw-bold border-bottom border-secondary pb-2 mb-3"><i class="bi bi-box-seam me-1"></i> Order Summary & Status</h6>
                            <p class="mb-2"><strong>Order Date:</strong> <span class="text-white"><?php echo htmlspecialchars($order['order_date'] ?? 'N/A'); ?></span></p>
                            <p class="mb-2"><strong>Total Amount:</strong> <span class="text-success fw-bold"><?php echo htmlspecialchars($order['currency_code'] ?? 'LKR') . ' ' . number_format($order['total_amount'], 2); ?></span></p>
                            <p class="mb-2"><strong>Current Status:</strong> <span class="badge bg-<?php echo $badge_class; ?>"><?php echo htmlspecialchars($order['order_status']); ?></span></p>
                            <p class="mb-2"><strong>Tracking Number:</strong> <span class="text-white"><?php echo !empty($order['tracking_number']) ? htmlspecialchars($order['tracking_number']) : '<span class="text-secondary fst-italic">Not Assigned</span>'; ?></span></p>
                            <p class="mb-2"><strong>Gift Wrapped:</strong> <?php echo ($order['is_gift_wrapped'] == 1) ? '<span class="text-success fw-bold">Yes</span> (Fee: ' . number_format($order['gift_wrap_fee'] ?? 0, 2) . ')' : '<span class="text-secondary">No</span>'; ?></p>
                            <?php if (!empty($order['gift_message'])): ?>
                                <p class="mt-3 mb-0"><strong>Gift Note:</strong><br><span class="text-secondary small bg-dark p-2 rounded d-block mt-1 border border-secondary"><?php echo htmlspecialchars($order['gift_message']); ?></span></p>
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

    <!-- Bootstrap JS Bundle CDN -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>