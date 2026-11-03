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

$message = '';

// Handle Stock Quantity Quick Update (Available for Admin & Super Admin)
if (isset($_POST['update_stock'])) {
    $product_id = intval($_POST['product_id']);
    $new_stock = intval($_POST['stock_quantity']);

    $stmt = $conn->prepare("UPDATE products SET stock_quantity = ? WHERE product_id = ?");
    $stmt->bind_param("ii", $new_stock, $product_id);
    
    if ($stmt->execute()) {
        $message = "Stock updated successfully!";
    } else {
        $message = "Error updating stock: " . $conn->error;
    }
}

// Filter by Category if selected
$category_filter = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;
$where_clause = "";
if ($category_filter > 0) {
    $where_clause = "WHERE p.category_id = $category_filter";
}

// Fetch products with categories
$sql = "SELECT p.*, c.category_name FROM products p LEFT JOIN categories c ON p.category_id = c.category_id $where_clause ORDER BY p.stock_quantity ASC";
$result = $conn->query($sql);

// Count low stock items (less than 5 units)
$low_stock_count = $conn->query("SELECT COUNT(*) as count FROM products WHERE stock_quantity <= 5")->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Stock - CraftedCeylon Admin</title>
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
            /* Creative Background Pattern with Subtle Gradient Overlay */
            background-image: linear-gradient(135deg, rgba(13, 13, 18, 0.95), rgba(20, 30, 25, 0.9)), 
                              radial-gradient(circle at 10% 20%, rgba(25, 135, 84, 0.15) 0%, transparent 40%),
                              radial-gradient(circle at 90% 80%, rgba(13, 202, 240, 0.08) 0%, transparent 40%);
            background-attachment: fixed;
            color: var(--text-main);
            font-family: 'Plus Jakarta Sans', sans-serif;
            min-height: 100vh;
        }

        /* Sidebar Styling */
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

        /* Glassmorphism Dark Card */
        .dark-card {
            background: var(--bg-card);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--border-color);
            border-radius: 18px;
            box-shadow: 0 16px 40px rgba(0, 0, 0, 0.5);
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
            background-color: rgba(19, 19, 29, 0.7);
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
            background-color: rgba(255, 255, 255, 0.04);
        }

        /* Form Inputs Styling */
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

        .glow-success {
            box-shadow: 0 0 20px rgba(25, 135, 84, 0.3);
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
                        <a href="inventory.php" class="nav-link active">
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
                    <?php if ($is_super_admin): ?>
                    <li class="nav-item">
                        <a href="manage-admins.php" class="nav-link text-warning">
                            <i class="bi bi-shield-lock-fill me-2"></i> Manage Admins
                        </a>
                    </li>
                    <?php endif; ?>
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
                        <h2 class="fw-bold text-white mb-1">Inventory & Stock Management</h2>
                        <p class="text-secondary small mb-0">Monitor real-time product quantities and manage low stock alerts.</p>
                    </div>
                    <?php if($low_stock_count > 0): ?>
                        <div class="badge bg-danger p-2 px-3 fs-6 shadow-sm">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i> <?php echo $low_stock_count; ?> Low Stock Items
                        </div>
                    <?php endif; ?>
                </div>

                <?php if(!empty($message)): ?>
                    <div class="alert alert-success bg-success text-white border-0 py-2 small mb-4 shadow-sm"><?php echo $message; ?></div>
                <?php endif; ?>

                <!-- Category Filter Form -->
                <div class="dark-card p-3 mb-4">
                    <form action="inventory.php" method="GET" class="row align-items-center g-3">
                        <div class="col-md-4">
                            <label class="form-label small text-secondary mb-1 fw-bold">Filter by Category</label>
                            <select name="category_id" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="0">All Categories</option>
                                <?php
                                $cat_res = $conn->query("SELECT * FROM categories");
                                while($cat = $cat_res->fetch_assoc()) {
                                    $selected = ($category_filter == $cat['category_id']) ? 'selected' : '';
                                    echo '<option value="'.$cat['category_id'].'" '.$selected.'>'.htmlspecialchars($cat['category_name']).'</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <?php if($category_filter > 0): ?>
                            <div class="col-md-2 d-flex align-items-end" style="padding-top: 22px;">
                                <a href="inventory.php" class="btn btn-sm btn-outline-light"><i class="bi bi-x-circle me-1"></i> Clear Filter</a>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Inventory Table -->
                <div class="dark-card p-4">
                    <div class="table-responsive">
                        <table class="table table-dark-custom align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Image</th>
                                    <th>Product Name</th>
                                    <th>Category</th>
                                    <th>Price (LKR)</th>
                                    <th>Current Stock Status</th>
                                    <th>Quick Stock Update</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($result && $result->num_rows > 0): ?>
                                    <?php while($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <img src="../<?php echo htmlspecialchars($row['main_image']); ?>" class="rounded border border-secondary shadow-sm" style="width: 45px; height: 45px; object-fit: cover;" alt="Product">
                                        </td>
                                        <td>
                                            <span class="fw-semibold text-white"><?php echo htmlspecialchars($row['product_name']); ?></span>
                                        </td>
                                        <td><span class="badge bg-secondary opacity-75"><?php echo htmlspecialchars($row['category_name'] ?? 'Uncategorized'); ?></span></td>
                                        <td class="fw-bold text-success">Rs. <?php echo number_format($row['price_lkr'], 2); ?></td>
                                        <td>
                                            <?php if($row['stock_quantity'] <= 0): ?>
                                                <span class="badge bg-danger"><i class="bi bi-x-octagon me-1"></i> Out of Stock</span>
                                            <?php elseif($row['stock_quantity'] <= 5): ?>
                                                <span class="badge bg-warning text-dark"><i class="bi bi-exclamation-triangle me-1"></i> Low Stock (<?php echo $row['stock_quantity']; ?> left)</span>
                                            <?php else: ?>
                                                <span class="badge bg-success bg-opacity-25 text-success border border-success"><i class="bi bi-check-circle me-1"></i> In Stock (<?php echo $row['stock_quantity']; ?>)</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <form action="inventory.php<?php echo ($category_filter > 0) ? '?category_id='.$category_filter : ''; ?>" method="POST" class="d-flex gap-2">
                                                <input type="hidden" name="product_id" value="<?php echo $row['product_id']; ?>">
                                                <input type="number" name="stock_quantity" value="<?php echo $row['stock_quantity']; ?>" min="0" class="form-control form-control-sm text-center fw-bold" style="width: 90px;" required>
                                                <button type="submit" name="update_stock" class="btn btn-sm btn-success fw-bold px-3">
                                                    <i class="bi bi-arrow-repeat me-1"></i> Update
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-secondary py-5">No products found in inventory.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>