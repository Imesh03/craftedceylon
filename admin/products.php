<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
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

$success_msg = '';
$error_msg = '';

// Handle Exchange Rate & Gift Wrapping Fee Updates
if (isset($_POST['update_settings'])) {
    $new_rate = floatval($_POST['usd_exchange_rate']);
    $new_fee_lkr = floatval($_POST['gift_wrap_fee_lkr']);
    $new_fee_usd = floatval($_POST['gift_wrap_fee_usd']);
    
    $check_rate = $conn->query("SELECT * FROM site_settings WHERE setting_key = 'usd_exchange_rate'");
    if ($check_rate->num_rows > 0) {
        $conn->query("UPDATE site_settings SET setting_value = '$new_rate' WHERE setting_key = 'usd_exchange_rate'");
    } else {
        $conn->query("INSERT INTO site_settings (setting_key, setting_value) VALUES ('usd_exchange_rate', '$new_rate')");
    }

    $conn->query("UPDATE site_settings SET setting_value = '$new_fee_lkr' WHERE setting_key = 'gift_wrap_fee_lkr'");
    $conn->query("UPDATE site_settings SET setting_value = '$new_fee_usd' WHERE setting_key = 'gift_wrap_fee_usd'");
    
    if ($new_rate > 0) {
        $conn->query("UPDATE products SET price_usd = ROUND(price_lkr / $new_rate, 2)");
    }
    
    $success_msg = "Store settings and live exchange rate updated successfully! All product USD prices have been synced.";
}

// Fetch current exchange rate and gift wrap fees
$rate_res = $conn->query("SELECT setting_value FROM site_settings WHERE setting_key = 'usd_exchange_rate'");
$current_exchange_rate = ($rate_res && $rate_res->num_rows > 0) ? floatval($rate_res->fetch_assoc()['setting_value']) : 333.58;

$res_lkr = $conn->query("SELECT setting_value FROM site_settings WHERE setting_key = 'gift_wrap_fee_lkr'");
$current_lkr = ($res_lkr && $res_lkr->num_rows > 0) ? $res_lkr->fetch_assoc()['setting_value'] : 700.00;

$res_usd = $conn->query("SELECT setting_value FROM site_settings WHERE setting_key = 'gift_wrap_fee_usd'");
$current_usd = ($res_usd && $res_usd->num_rows > 0) ? $res_usd->fetch_assoc()['setting_value'] : 2.50;

// Handle Product Update (Edit Details, Main Image & Add/Delete Gallery Images)
if (isset($_POST['update_product'])) {
    $product_id = intval($_POST['product_id']);
    $product_name = trim($_POST['product_name']);
    $category_id = intval($_POST['category_id']);
    $price_lkr = floatval($_POST['price_lkr']);
    
    $price_usd = ($price_lkr > 0 && $current_exchange_rate > 0) ? round($price_lkr / $current_exchange_rate, 2) : 0.00;
    
    $stock_quantity = intval($_POST['stock_quantity']);
    $description = trim($_POST['description']);
    $is_biodegradable = isset($_POST['is_biodegradable']) ? 1 : 0;
    $is_recyclable = isset($_POST['is_recyclable']) ? 1 : 0;
    $is_customizable = isset($_POST['is_customizable']) ? 1 : 0;

    $image_sql = "";
    $db_image_path = "";
    $upload_dir = '../assets/images/products/';

    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    // Handle New Main Image Upload if provided
    if (isset($_FILES['main_image']) && $_FILES['main_image']['error'] == 0) {
        $image_name = $_FILES['main_image']['name'];
        $image_tmp = $_FILES['main_image']['tmp_name'];
        $new_image_name = time() . '_main_' . basename($image_name);
        $target_path = $upload_dir . $new_image_name;
        $db_image_path = 'assets/images/products/' . $new_image_name;

        if (move_uploaded_file($image_tmp, $target_path)) {
            $old_img_q = $conn->prepare("SELECT main_image FROM products WHERE product_id = ?");
            $old_img_q->bind_param("i", $product_id);
            $old_img_q->execute();
            $old_res = $old_img_q->get_result()->fetch_assoc();
            if ($old_res && !empty($old_res['main_image']) && file_exists("../" . $old_res['main_image'])) {
                @unlink("../" . $old_res['main_image']);
            }
            $image_sql = ", main_image = ?";
        }
    }

    // Update Product Details Query
    if (!empty($image_sql)) {
        $up_stmt = $conn->prepare("UPDATE products SET product_name = ?, category_id = ?, price_lkr = ?, price_usd = ?, stock_quantity = ?, description = ?, is_biodegradable = ?, is_recyclable = ?, is_customizable = ? $image_sql WHERE product_id = ?");
        $up_stmt->bind_param("siddisiiisi", $product_name, $category_id, $price_lkr, $price_usd, $stock_quantity, $description, $is_biodegradable, $is_recyclable, $is_customizable, $db_image_path, $product_id);
    } else {
        $up_stmt = $conn->prepare("UPDATE products SET product_name = ?, category_id = ?, price_lkr = ?, price_usd = ?, stock_quantity = ?, description = ?, is_biodegradable = ?, is_recyclable = ?, is_customizable = ? WHERE product_id = ?");
        $up_stmt->bind_param("siddisiiii", $product_name, $category_id, $price_lkr, $price_usd, $stock_quantity, $description, $is_biodegradable, $is_recyclable, $is_customizable, $product_id);
    }

    if ($up_stmt->execute()) {
        // 1. Handle Deletion of Selected Gallery Images
        if (isset($_POST['delete_images']) && is_array($_POST['delete_images'])) {
            foreach ($_POST['delete_images'] as $img_id) {
                $img_id = intval($img_id);
                $path_q = $conn->prepare("SELECT image_path FROM product_images WHERE image_id = ? AND product_id = ?");
                $path_q->bind_param("ii", $img_id, $product_id);
                $path_q->execute();
                $path_res = $path_q->get_result()->fetch_assoc();
                
                if ($path_res) {
                    if (!empty($path_res['image_path']) && file_exists("../" . $path_res['image_path'])) {
                        @unlink("../" . $path_res['image_path']);
                    }
                    $del_q = $conn->prepare("DELETE FROM product_images WHERE image_id = ?");
                    $del_q->bind_param("i", $img_id);
                    $del_q->execute();
                }
            }
        }

        // 2. Handle Addition of New Gallery Images during Edit
        if (isset($_FILES['new_gallery_images'])) {
            $file_count = count($_FILES['new_gallery_images']['name']);
            for ($i = 0; $i < $file_count; $i++) {
                if ($_FILES['new_gallery_images']['error'][$i] == 0) {
                    $gal_name = $_FILES['new_gallery_images']['name'][$i];
                    $gal_tmp = $_FILES['new_gallery_images']['tmp_name'][$i];
                    $new_gal_name = time() . '_editgal_' . $i . '_' . basename($gal_name);
                    $gal_target = $upload_dir . $new_gal_name;
                    $db_gal_path = 'assets/images/products/' . $new_gal_name;

                    if (move_uploaded_file($gal_tmp, $gal_target)) {
                        $img_stmt = $conn->prepare("INSERT INTO product_images (product_id, image_path) VALUES (?, ?)");
                        $img_stmt->bind_param("is", $product_id, $db_gal_path);
                        $img_stmt->execute();
                    }
                }
            }
        }

        $success_msg = "Product and gallery images updated successfully!";
    } else {
        $error_msg = "Error updating product: " . $conn->error;
    }
}

// Handle Product Deletion (SUPER ADMIN ONLY)
if (isset($_GET['action']) && $_GET['action'] == 'delete') {
    if (!$is_super_admin) {
        header("Location: products.php?error=unauthorized");
        exit();
    }
    $product_id = intval($_GET['id']);
    
    // Delete main image
    $img_q = $conn->prepare("SELECT main_image FROM products WHERE product_id = ?");
    $img_q->bind_param("i", $product_id);
    $img_q->execute();
    $img_res = $img_q->get_result()->fetch_assoc();
    if ($img_res && !empty($img_res['main_image']) && file_exists("../" . $img_res['main_image'])) {
        @unlink("../" . $img_res['main_image']);
    }

    // Delete gallery images from folder and DB
    $gal_del_q = $conn->prepare("SELECT image_path FROM product_images WHERE product_id = ?");
    $gal_del_q->bind_param("i", $product_id);
    $gal_del_q->execute();
    $gal_del_res = $gal_del_q->get_result();
    while($g_img = $gal_del_res->fetch_assoc()) {
        if (!empty($g_img['image_path']) && file_exists("../" . $g_img['image_path'])) {
            @unlink("../" . $g_img['image_path']);
        }
    }
    $conn->query("DELETE FROM product_images WHERE product_id = $product_id");

    $stmt = $conn->prepare("DELETE FROM products WHERE product_id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    
    header("Location: products.php?msg=deleted");
    exit();
}

// Search and Filter Handling
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$sql = "SELECT p.*, c.category_name FROM products p LEFT JOIN categories c ON p.category_id = c.category_id WHERE 1=1";

if (!empty($search_query)) {
    $escaped_search = $conn->real_escape_string($search_query);
    $sql .= " AND (p.product_name LIKE '%$escaped_search%' OR p.description LIKE '%$escaped_search%')";
}

$sql .= " ORDER BY p.product_id DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Products & Settings - CraftedCeylon Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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

        .form-control, .form-select {
            background-color: rgba(18, 18, 22, 0.8);
            border: 1px solid var(--border-color);
            color: var(--text-main);
            border-radius: 10px;
            padding: 10px 14px;
        }

        .form-control:focus, .form-select:focus {
            background-color: rgba(18, 18, 22, 0.95);
            border-color: var(--accent-green);
            color: var(--text-main);
            box-shadow: 0 0 0 0.25rem rgba(25, 135, 84, 0.25);
        }

        .form-control[readonly] {
            background-color: rgba(40, 40, 55, 0.6);
            cursor: not-allowed;
            color: #198754;
            font-weight: bold;
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
                    <li class="nav-item"><a href="products.php" class="nav-link active"><i class="bi bi-box-seam me-2"></i> Products</a></li>
                    <li class="nav-item"><a href="orders.php" class="nav-link"><i class="bi bi-cart-check me-2"></i> Orders & Tracking</a></li>
                    <li class="nav-item"><a href="inventory.php" class="nav-link"><i class="bi bi-stack me-2"></i> Inventory Stock</a></li>
                    <li class="nav-item"><a href="customers.php" class="nav-link"><i class="bi bi-people me-2"></i> Customers</a></li>
                    <li class="nav-item"><a href="reviews.php" class="nav-link"><i class="bi bi-star-half me-2"></i> Customer Reviews</a></li>
                    <?php if ($is_super_admin): ?>
                    <li class="nav-item"><a href="manage-admins.php" class="nav-link text-warning"><i class="bi bi-shield-lock-fill me-2"></i> Manage Admins</a></li>
                    <?php endif; ?>
                    <li class="nav-item mt-5"><a href="logout.php" class="nav-link text-danger"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
                </ul>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 p-4 p-md-5">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="fw-bold text-white mb-1">Product Catalog & Settings</h2>
                        <p class="text-secondary small mb-0">Manage live exchange rates, store fees, and inventory items.</p>
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

                <?php if(!empty($error_msg)): ?>
                    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error_msg; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET['error']) && $_GET['error'] == 'unauthorized'): ?>
                    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
                        <i class="bi bi-shield-lock-fill me-2"></i> Access Denied! Only Super Admins can delete products.
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <!-- Store Settings & Live Exchange Rate Configuration Card -->
                <div class="dark-card p-4 mb-5">
                    <div class="d-flex align-items-center mb-3">
                        <div class="bg-success bg-opacity-10 text-success p-2 rounded-3 me-3 border border-success border-opacity-25">
                            <i class="bi bi-currency-exchange fs-4"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold text-white mb-0">Live USD Exchange Rate & Checkout Fees</h5>
                            <p class="text-secondary small mb-0">Updating the exchange rate will automatically re-calculate all product USD prices based on LKR.</p>
                        </div>
                    </div>

                    <form action="" method="POST" class="row g-3 align-items-end mt-2">
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-light">1 USD = LKR Rate (e.g. 333.58)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-dark border-secondary text-success fw-bold">Rs.</span>
                                <input type="number" step="0.01" name="usd_exchange_rate" class="form-control" value="<?php echo htmlspecialchars($current_exchange_rate); ?>" required>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-light">LKR Wrapping Fee</label>
                            <div class="input-group">
                                <span class="input-group-text bg-dark border-secondary text-success fw-bold">Rs.</span>
                                <input type="number" step="0.01" name="gift_wrap_fee_lkr" class="form-control" value="<?php echo htmlspecialchars($current_lkr); ?>" required>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-light">USD Wrapping Fee</label>
                            <div class="input-group">
                                <span class="input-group-text bg-dark border-secondary text-success fw-bold">$</span>
                                <input type="number" step="0.01" name="gift_wrap_fee_usd" class="form-control" value="<?php echo htmlspecialchars($current_usd); ?>" required>
                            </div>
                        </div>
                        
                        <div class="col-md-2">
                            <button type="submit" name="update_settings" class="btn btn-success w-100 fw-bold shadow-sm py-2">
                                <i class="bi bi-save2 me-1"></i> Save All
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Product Table Card with Search Bar -->
                <div class="dark-card p-4">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mb-4 gap-3">
                        <h5 class="fw-bold text-white mb-0"><i class="bi bi-box-seam me-2 text-success"></i> Active Products Inventory</h5>
                        
                        <form action="products.php" method="GET" class="d-flex gap-2" style="width: 100%; max-width: 350px;">
                            <div class="input-group input-group-sm">
                                <input type="text" name="search" class="form-control" placeholder="Search by product name..." value="<?php echo htmlspecialchars($search_query); ?>">
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
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($result && $result->num_rows > 0): ?>
                                    <?php 
                                    $products_array = [];
                                    while($row = $result->fetch_assoc()) {
                                        $products_array[] = $row;
                                    }
                                    foreach($products_array as $row): 
                                    ?>
                                    <tr>
                                        <td>
                                            <?php if(!empty($row['main_image'])): ?>
                                                <img src="../<?php echo htmlspecialchars($row['main_image']); ?>" class="rounded border border-secondary shadow-sm" style="width: 50px; height: 50px; object-fit: cover;" alt="Product">
                                            <?php else: ?>
                                                <div class="bg-secondary rounded text-white d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;"><i class="bi bi-image"></i></div>
                                            <?php endif; ?>
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
                                        <td class="text-center">
                                            <button type="button" class="btn btn-outline-warning btn-sm me-1" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $row['product_id']; ?>" title="Edit Product">
                                                <i class="bi bi-pencil-square"></i>
                                            </button>

                                            <?php if ($is_super_admin): ?>
                                                <a href="products.php?action=delete&id=<?php echo $row['product_id']; ?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('Are you sure you want to delete this product?');" title="Delete Product">
                                                    <i class="bi bi-trash"></i>
                                                </a>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-outline-secondary btn-sm" title="Super Admin Only" disabled>
                                                    <i class="bi bi-shield-lock"></i>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-secondary py-5">No products found matching your search criteria.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Edit Product Modals Loop with Live Exchange Rate Auto USD Calculation & Gallery Management -->
                <?php if (isset($products_array)): foreach($products_array as $row): ?>
                <div class="modal fade" id="editModal<?php echo $row['product_id']; ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered modal-lg">
                        <div class="modal-content p-3">
                            <form action="products.php" method="POST" enctype="multipart/form-data">
                                <div class="modal-header border-bottom border-secondary border-opacity-25">
                                    <h5 class="modal-title fw-bold text-success">
                                        <i class="bi bi-pencil-square me-2"></i> Edit Product: <?php echo htmlspecialchars($row['product_name']); ?>
                                    </h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body py-4">
                                    <input type="hidden" name="product_id" value="<?php echo $row['product_id']; ?>">
                                    
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label small text-secondary fw-bold">Product Name</label>
                                            <input type="text" name="product_name" class="form-control" value="<?php echo htmlspecialchars($row['product_name']); ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small text-secondary fw-bold">Category</label>
                                            <select name="category_id" class="form-select" required>
                                                <?php
                                                $cat_q = $conn->query("SELECT * FROM categories");
                                                while($cat = $cat_q->fetch_assoc()) {
                                                    $sel = ($cat['category_id'] == $row['category_id']) ? 'selected' : '';
                                                    echo '<option value="'.$cat['category_id'].'" '.$sel.'>'.htmlspecialchars($cat['category_name']).'</option>';
                                                }
                                                ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small text-secondary fw-bold">Price (LKR)</label>
                                            <input type="number" step="0.01" id="price_lkr_<?php echo $row['product_id']; ?>" name="price_lkr" class="form-control" value="<?php echo $row['price_lkr']; ?>" required oninput="calculateLiveUSD(<?php echo $row['product_id']; ?>, <?php echo $current_exchange_rate; ?>)">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small text-secondary fw-bold">Price (USD) <small class="text-secondary">(Auto calculated via rate: <?php echo $current_exchange_rate; ?>)</small></label>
                                            <input type="number" step="0.01" id="price_usd_<?php echo $row['product_id']; ?>" name="price_usd" class="form-control" value="<?php echo $row['price_usd']; ?>" readonly>
                                        </div>
                                        <div class="col-md-12">
                                            <label class="form-label small text-secondary fw-bold">Stock Quantity</label>
                                            <input type="number" name="stock_quantity" class="form-control" value="<?php echo $row['stock_quantity']; ?>" required>
                                        </div>
                                        <div class="col-md-12">
                                            <label class="form-label small text-secondary fw-bold">Description</label>
                                            <textarea name="description" class="form-control" rows="3"><?php echo htmlspecialchars($row['description'] ?? ''); ?></textarea>
                                        </div>
                                        
                                        <!-- Main Image Edit -->
                                        <div class="col-md-12">
                                            <label class="form-label small text-secondary fw-bold">Product Main Image (Upload new to replace)</label>
                                            <div class="d-flex align-items-center gap-3">
                                                <?php if(!empty($row['main_image'])): ?>
                                                    <img src="../<?php echo htmlspecialchars($row['main_image']); ?>" class="rounded border border-secondary" style="width: 60px; height: 60px; object-fit: cover;" alt="Current">
                                                <?php endif; ?>
                                                <input type="file" name="main_image" class="form-control" accept="image/*">
                                            </div>
                                        </div>

                                        <!-- Gallery Images Management (Delete existing & Upload new) -->
                                        <div class="col-md-12 mt-2">
                                            <label class="form-label small text-secondary fw-bold">Current Angle / Gallery Images (Check to delete)</label>
                                            <div class="d-flex flex-wrap gap-2 mb-2 p-2 bg-dark rounded border border-secondary">
                                                <?php
                                                $curr_p_id = $row['product_id'];
                                                $gal_query = $conn->query("SELECT * FROM product_images WHERE product_id = $curr_p_id");
                                                if ($gal_query && $gal_query->num_rows > 0) {
                                                    while($g_img = $gal_query->fetch_assoc()) {
                                                        echo '<div class="position-relative text-center border border-secondary rounded p-1" style="width: 70px;">';
                                                        echo '<img src="../' . htmlspecialchars($g_img['image_path']) . '" class="rounded" style="width: 60px; height: 60px; object-fit: cover;">';
                                                        echo '<div class="form-check mt-1 d-flex justify-content-center">';
                                                        echo '<input class="form-check-input" type="checkbox" name="delete_images[]" value="' . $g_img['image_id'] . '" title="Check to delete">';
                                                        echo '</div>';
                                                        echo '</div>';
                                                    }
                                                } else {
                                                    echo '<span class="text-secondary small fst-italic">No extra gallery images uploaded.</span>';
                                                }
                                                ?>
                                            </div>
                                            
                                            <label class="form-label small text-secondary fw-bold mt-2">Add New Angle Images</label>
                                            <input type="file" name="new_gallery_images[]" class="form-control" multiple accept="image/*">
                                        </div>

                                        <div class="col-md-12 mt-3">
                                            <div class="form-check form-check-inline text-light">
                                                <input class="form-check-input" type="checkbox" name="is_biodegradable" id="bio_<?php echo $row['product_id']; ?>" value="1" <?php echo $row['is_biodegradable'] ? 'checked' : ''; ?>>
                                                <label class="form-check-label small" for="bio_<?php echo $row['product_id']; ?>">100% Biodegradable</label>
                                            </div>
                                            <div class="form-check form-check-inline text-light">
                                                <input class="form-check-input" type="checkbox" name="is_recyclable" id="rec_<?php echo $row['product_id']; ?>" value="1" <?php echo $row['is_recyclable'] ? 'checked' : ''; ?>>
                                                <label class="form-check-label small" for="rec_<?php echo $row['product_id']; ?>">Recyclable Material</label>
                                            </div>
                                            <div class="form-check form-check-inline text-light">
                                                <input class="form-check-input" type="checkbox" name="is_customizable" id="cust_<?php echo $row['product_id']; ?>" value="1" <?php echo $row['is_customizable'] ? 'checked' : ''; ?>>
                                                <label class="form-check-label small" for="cust_<?php echo $row['product_id']; ?>">Customizable</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer border-top border-secondary border-opacity-25">
                                    <button type="button" class="btn btn-secondary btn-sm px-4" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" name="update_product" class="btn btn-success btn-sm px-4 fw-bold">Save Changes</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; endif; ?>

            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function calculateLiveUSD(productId, exchangeRate) {
            let lkrInput = document.getElementById('price_lkr_' + productId).value;
            let usdInput = document.getElementById('price_usd_' + productId);

            if (lkrInput > 0 && exchangeRate > 0) {
                let calculatedUSD = (lkrInput / exchangeRate).toFixed(2);
                usdInput.value = calculatedUSD;
            } else {
                usdInput.value = '';
            }
        }
    </script>
</body>
</html>