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

// Handle Stock Quantity Quick Update
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

// Filter Options & Search handling
$category_filter = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;
$stock_filter = isset($_GET['stock_filter']) ? trim($_GET['stock_filter']) : 'all';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

$where_clauses = [];

if ($category_filter > 0) {
    $where_clauses[] = "p.category_id = $category_filter";
}

if ($stock_filter === 'low') {
    $where_clauses[] = "p.stock_quantity <= 5 AND p.stock_quantity > 0";
} elseif ($stock_filter === 'out') {
    $where_clauses[] = "p.stock_quantity <= 0";
} elseif ($stock_filter === 'healthy') {
    $where_clauses[] = "p.stock_quantity > 5";
}

if (!empty($search_query)) {
    $escaped_search = $conn->real_escape_string($search_query);
    if (is_numeric($search_query)) {
        $where_clauses[] = "(p.product_id = '$escaped_search' OR p.product_name LIKE '%$escaped_search%')";
    } else {
        $where_clauses[] = "p.product_name LIKE '%$escaped_search%'";
    }
}

$where_sql = "";
if (count($where_clauses) > 0) {
    $where_sql = "WHERE " . implode(" AND ", $where_clauses);
}

// Fetch products with categories, search and filters
$sql = "SELECT p.*, c.category_name FROM products p LEFT JOIN categories c ON p.category_id = c.category_id $where_sql ORDER BY p.stock_quantity ASC";
$result = $conn->query($sql);

// Count metrics for quick filter badges
$low_stock_count = $conn->query("SELECT COUNT(*) as count FROM products WHERE stock_quantity <= 5 AND stock_quantity > 0")->fetch_assoc()['count'];
$out_of_stock_count = $conn->query("SELECT COUNT(*) as count FROM products WHERE stock_quantity <= 0")->fetch_assoc()['count'];
$total_inventory_count = $conn->query("SELECT COUNT(*) as count FROM products")->fetch_assoc()['count'];
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
            margin-left: 16.666667%;
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

        /* Glassmorphism Dark Card */
        .dark-card {
            background: var(--bg-card);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--border-color);
            border-radius: 18px;
            box-shadow: 0 16px 40px rgba(0, 0, 0, 0.5);
        }

        /* Creative Filter Pill Buttons */
        .filter-pill {
            background: rgba(18, 18, 24, 0.7);
            border: 1px solid var(--border-color);
            color: var(--text-sub);
            border-radius: 12px;
            padding: 8px 16px;
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .filter-pill:hover {
            background: rgba(255, 255, 255, 0.08);
            color: #ffffff;
            border-color: rgba(255, 255, 255, 0.2);
        }

        .filter-pill.active {
            background: linear-gradient(135deg, #198754 0%, #146c43 100%);
            color: #ffffff;
            border-color: #198754;
            box-shadow: 0 4px 15px rgba(25, 135, 84, 0.3);
        }

        .filter-pill.active-danger {
            background: linear-gradient(135deg, #dc3545 0%, #b02a37 100%);
            color: #ffffff;
            border-color: #dc3545;
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
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

            <!-- Main Content with Left Margin Offset -->
            <div class="col-md-9 col-lg-10 ms-auto main-content-wrapper p-4 p-md-5">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="fw-bold text-white mb-1">Inventory & Stock Management</h2>
                        <p class="text-secondary small mb-0">Monitor real-time product quantities and manage low stock alerts.</p>
                    </div>
                </div>

                <?php if(!empty($message)): ?>
                    <div class="alert alert-success bg-success text-white border-0 py-2 small mb-4 shadow-sm"><?php echo $message; ?></div>
                <?php endif; ?>

                <!-- Creative Search, Filter Tabs & Category Filter Section -->
                <div class="dark-card p-4 mb-4">
                    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-center gap-3">
                        <!-- Quick Stock Status Filter Pills -->
                        <div class="d-flex flex-wrap gap-2">
                            <a href="inventory.php?category_id=<?php echo $category_filter; ?>&stock_filter=all&search=<?php echo urlencode($search_query); ?>" class="filter-pill <?php echo ($stock_filter === 'all') ? 'active' : ''; ?>">
                                <i class="bi bi-grid"></i> All Products <span class="badge bg-secondary bg-opacity-50 ms-1"><?php echo $total_inventory_count; ?></span>
                            </a>
                            <a href="inventory.php?category_id=<?php echo $category_filter; ?>&stock_filter=low&search=<?php echo urlencode($search_query); ?>" class="filter-pill <?php echo ($stock_filter === 'low') ? 'active-danger' : ''; ?>">
                                <i class="bi bi-exclamation-triangle-fill text-warning"></i> Low Stock <span class="badge bg-warning text-dark ms-1"><?php echo $low_stock_count; ?></span>
                            </a>
                            <a href="inventory.php?category_id=<?php echo $category_filter; ?>&stock_filter=out&search=<?php echo urlencode($search_query); ?>" class="filter-pill <?php echo ($stock_filter === 'out') ? 'active-danger' : ''; ?>">
                                <i class="bi bi-x-octagon-fill text-danger"></i> Out of Stock <span class="badge bg-danger ms-1"><?php echo $out_of_stock_count; ?></span>
                            </a>
                        </div>

                        <!-- Product Search Bar & Category Selector -->
                        <form action="inventory.php" method="GET" class="d-flex flex-column flex-sm-row align-items-center gap-2" style="width: 100%; max-width: 540px;">
                            <input type="hidden" name="stock_filter" value="<?php echo htmlspecialchars($stock_filter); ?>">
                            
                            <!-- Search Input (Product ID or Name) -->
                            <div class="input-group input-group-sm">
                                <input type="text" name="search" class="form-control" placeholder="Search Product ID or Name..." value="<?php echo htmlspecialchars($search_query); ?>">
                                <button class="btn btn-success" type="submit"><i class="bi bi-search"></i></button>
                            </div>

                            <!-- Category Select -->
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

                            <?php if($category_filter > 0 || $stock_filter !== 'all' || !empty($search_query)): ?>
                                <a href="inventory.php" class="btn btn-sm btn-outline-light text-nowrap" title="Reset Filters"><i class="bi bi-arrow-counterclockwise"></i> Reset</a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <!-- Inventory Table -->
                <div class="dark-card p-4">
                    <div class="table-responsive">
                        <table class="table table-dark-custom align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Image</th>
                                    <th>Product ID & Name</th>
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
                                            <span class="badge bg-success bg-opacity-25 text-success border border-success px-2 py-1 me-1">#<?php echo $row['product_id']; ?></span>
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
                                            <form action="inventory.php?category_id=<?php echo $category_filter; ?>&stock_filter=<?php echo $stock_filter; ?>&search=<?php echo urlencode($search_query); ?>" method="POST" class="d-flex gap-2">
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
                                        <td colspan="6" class="text-center text-secondary py-5">No products found matching your search or filter criteria.</td>
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