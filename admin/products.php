<?php
session_start();
require_once '../config/db.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged'])) {
    header("Location: login.php");
    exit();
}

$success_msg = '';

// Handle Gift Wrapping Fee Update
if (isset($_POST['update_gift_fee'])) {
    $new_fee_lkr = floatval($_POST['gift_wrap_fee_lkr']);
    $new_fee_usd = floatval($_POST['gift_wrap_fee_usd']);
    
    $conn->query("UPDATE site_settings SET setting_value = '$new_fee_lkr' WHERE setting_key = 'gift_wrap_fee_lkr'");
    $conn->query("UPDATE site_settings SET setting_value = '$new_fee_usd' WHERE setting_key = 'gift_wrap_fee_usd'");
    
    $success_msg = "Gift wrapping fees updated successfully!";
}

// Fetch current gift wrap fees
$res_lkr = $conn->query("SELECT setting_value FROM site_settings WHERE setting_key = 'gift_wrap_fee_lkr'");
$current_lkr = ($res_lkr && $res_lkr->num_rows > 0) ? $res_lkr->fetch_assoc()['setting_value'] : 700.00;

$res_usd = $conn->query("SELECT setting_value FROM site_settings WHERE setting_key = 'gift_wrap_fee_usd'");
$current_usd = ($res_usd && $res_usd->num_rows > 0) ? $res_usd->fetch_assoc()['setting_value'] : 2.50;

// Handle Product Deletion
if (isset($_GET['action']) && $_GET['action'] == 'delete') {
    $product_id = intval($_GET['id']);
    
    $stmt = $conn->prepare("DELETE FROM products WHERE product_id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    
    header("Location: products.php");
    exit();
}

// Search and Filter Handling
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$sql = "SELECT p.*, c.category_name FROM products p LEFT JOIN categories c ON p.category_id = c.category_id WHERE 1=1";

if (!empty($search_query)) {
    $escaped_search = $conn->real_escape_string($search_query);
    $sql .= " AND (p.product_name LIKE '%$escaped_search%' OR p.description LIKE '%$escaped_search%')";
}

$sql .= " ORDER BY p.created_at DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Products & Settings - Admin Dark Portal</title>
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
            padding: 16px 20px;
            border-bottom: 2px solid var(--border-color);
            background-color: rgba(22, 22, 34, 0.5);
        }

        .table-dark-custom td {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-main);
            vertical-align: middle;
        }

        .table-dark-custom tbody tr {
            transition: all 0.2s ease;
        }

        .table-dark-custom tbody tr:hover {
            background-color: rgba(255, 255, 255, 0.03);
        }

        /* Form Inputs Styling */
        .form-control {
            background-color: #121216;
            border: 1px solid var(--border-color);
            color: var(--text-main);
            border-radius: 8px;
            padding: 10px 14px;
        }

        .form-control:focus {
            background-color: #121216;
            border-color: var(--accent-green);
            color: var(--text-main);
            box-shadow: 0 0 0 0.25rem rgba(25, 135, 84, 0.25);
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
                        <a href="products.php" class="nav-link active">
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

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 p-4 p-md-5">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="fw-bold text-white mb-1">Product Catalog & Settings</h2>
                        <p class="text-secondary small mb-0">Manage inventory items and configure store checkout fees.</p>
                    </div>
                    <a href="product-add.php" class="btn btn-success fw-bold shadow-sm px-3">
                        <i class="bi bi-plus-circle me-1"></i> Add New Product
                    </a>
                </div>

                <?php if(!empty($success_msg)): ?>
                    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i> <?php echo $success_msg; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <!-- Creative Gift Wrapping Fee Configuration Card -->
                <div class="dark-card p-4 mb-5">
                    <div class="d-flex align-items-center mb-3">
                        <div class="bg-success bg-opacity-10 text-success p-2 rounded-3 me-3 border border-success border-opacity-25">
                            <i class="bi bi-gift-fill fs-4"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold text-white mb-0">Dynamic Gift Wrapping Fee Configuration</h5>
                            <p class="text-secondary small mb-0">Set the wrap fee charged during customer checkout for both currencies.</p>
                        </div>
                    </div>

                    <form action="" method="POST" class="row g-3 align-items-end mt-2">
                        <div class="col-md-5">
                            <label class="form-label small fw-bold text-light">LKR Wrapping Fee (Rs.)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-dark border-secondary text-success fw-bold">Rs.</span>
                                <input type="number" step="0.01" name="gift_wrap_fee_lkr" class="form-control" value="<?php echo htmlspecialchars($current_lkr); ?>" required>
                            </div>
                        </div>
                        
                        <div class="col-md-5">
                            <label class="form-label small fw-bold text-light">USD Wrapping Fee ($)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-dark border-secondary text-success fw-bold">$</span>
                                <input type="number" step="0.01" name="gift_wrap_fee_usd" class="form-control" value="<?php echo htmlspecialchars($current_usd); ?>" required>
                            </div>
                        </div>
                        
                        <div class="col-md-2">
                            <button type="submit" name="update_gift_fee" class="btn btn-success w-100 fw-bold shadow-sm py-2">
                                <i class="bi bi-save2 me-1"></i> Update Fee
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Product Table Card with Search Bar -->
                <div class="dark-card p-4">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mb-4 gap-3">
                        <h5 class="fw-bold text-white mb-0"><i class="bi bi-box-seam me-2 text-success"></i> Active Products Inventory</h5>
                        
                        <!-- Search Form -->
                        <form action="products.php" method="GET" class="d-flex gap-2" style="width: 100%; max-width: 350px;">
                            <div class="input-group input-group-sm">
                                <input type="text" name="search" class="form-control" placeholder="Search products..." value="<?php echo htmlspecialchars($search_query); ?>">
                                <button class="btn btn-success" type="submit"><i class="bi bi-search"></i></button>
                                <?php if (!empty($search_query)): ?>
                                    <a href="products.php" class="btn btn-outline-secondary" title="Reset Search"><i class="bi bi-x-lg"></i></a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-dark-custom align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Image</th>
                                    <th>Product Name</th>
                                    <th>Category</th>
                                    <th>Price (LKR / USD)</th>
                                    <th>Stock</th>
                                    <th>Badges</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($result && $result->num_rows > 0): ?>
                                    <?php while($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <img src="../<?php echo htmlspecialchars($row['main_image']); ?>" class="rounded border border-secondary shadow-sm" style="width: 50px; height: 50px; object-fit: cover;" alt="Product">
                                        </td>
                                        <td>
                                            <span class="fw-bold text-light"><?php echo htmlspecialchars($row['product_name']); ?></span>
                                        </td>
                                        <td><span class="badge bg-secondary opacity-75"><?php echo htmlspecialchars($row['category_name'] ?? 'Uncategorized'); ?></span></td>
                                        <td>
                                            <span class="text-success fw-bold">Rs. <?php echo number_format($row['price_lkr'], 2); ?></span><br>
                                            <small class="text-secondary">$ <?php echo number_format($row['price_usd'], 2); ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo ($row['stock_quantity'] > 5) ? 'success bg-opacity-25 text-success border border-success' : 'danger'; ?>">
                                                <?php echo $row['stock_quantity']; ?> left
                                            </span>
                                        </td>
                                        <td>
                                            <?php if($row['is_biodegradable']): ?>
                                                <span class="badge bg-success-subtle text-success border border-success me-1" style="font-size: 10px;">Biodegradable</span>
                                            <?php endif; ?>
                                            <?php if($row['is_customizable']): ?>
                                                <span class="badge bg-info-subtle text-info border border-info" style="font-size: 10px;">Customizable</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="products.php?action=delete&id=<?php echo $row['product_id']; ?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('Are you sure you want to delete this product?');" title="Delete Product">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-secondary py-5">No products found matching your search criteria.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>