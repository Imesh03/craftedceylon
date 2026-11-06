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

// Fetch current live exchange rate from site_settings
$rate_res = $conn->query("SELECT setting_value FROM site_settings WHERE setting_key = 'usd_exchange_rate'");
$current_exchange_rate = ($rate_res && $rate_res->num_rows > 0) ? floatval($rate_res->fetch_assoc()['setting_value']) : 333.58;

$success = '';
$error = '';

if (isset($_POST['add_product'])) {
    $product_name = trim($_POST['product_name']);
    $description = trim($_POST['description']);
    $price_lkr = floatval($_POST['price_lkr']);
    
    // Auto calculate or use posted USD based on exchange rate
    $price_usd = ($price_lkr > 0 && $current_exchange_rate > 0) ? round($price_lkr / $current_exchange_rate, 2) : floatval($_POST['price_usd']);
    
    $stock_quantity = intval($_POST['stock_quantity']);
    
    $is_biodegradable = isset($_POST['is_biodegradable']) ? 1 : 0;
    $is_recyclable = isset($_POST['is_recyclable']) ? 1 : 0;
    $is_customizable = isset($_POST['is_customizable']) ? 1 : 0;

    // 1. Handle Category
    $category_name = trim($_POST['category_name']);
    $category_id = 0;

    if (!empty($category_name)) {
        $cat_check = $conn->prepare("SELECT category_id FROM categories WHERE category_name = ?");
        $cat_check->bind_param("s", $category_name);
        $cat_check->execute();
        $cat_res = $cat_check->get_result();
        
        if ($cat_res->num_rows > 0) {
            $category_id = $cat_res->fetch_assoc()['category_id'];
        } else {
            $cat_ins = $conn->prepare("INSERT INTO categories (category_name) VALUES (?)");
            $cat_ins->bind_param("s", $category_name);
            $cat_ins->execute();
            $category_id = $cat_ins->insert_id;
        }
    }

    // 2. Handle Artisan
    $artisan_name = trim($_POST['artisan_name']);
    $artisan_location = trim($_POST['artisan_location']);
    $artisan_id = NULL;

    if (!empty($artisan_name)) {
        $art_check = $conn->prepare("SELECT artisan_id FROM artisans WHERE artisan_name = ?");
        $art_check->bind_param("s", $artisan_name);
        $art_check->execute();
        $art_res = $art_check->get_result();

        if ($art_res->num_rows > 0) {
            $artisan_id = $art_res->fetch_assoc()['artisan_id'];
        } else {
            $art_ins = $conn->prepare("INSERT INTO artisans (artisan_name, location) VALUES (?, ?)");
            $art_ins->bind_param("ss", $artisan_name, $artisan_location);
            $art_ins->execute();
            $artisan_id = $art_ins->insert_id;
        }
    }

    $upload_dir = '../assets/images/products/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    // 3. Handle Main Image Upload
    $db_image_path = "";
    if (isset($_FILES['main_image']) && $_FILES['main_image']['error'] == 0) {
        $image_name = $_FILES['main_image']['name'];
        $image_tmp = $_FILES['main_image']['tmp_name'];
        $new_image_name = time() . '_main_' . basename($image_name);
        $target_path = $upload_dir . $new_image_name;
        $db_image_path = 'assets/images/products/' . $new_image_name;

        if (!move_uploaded_file($image_tmp, $target_path)) {
            $error = "Failed to upload main product image.";
        }
    } else {
        $error = "Please upload a main product image.";
    }

    if (empty($error)) {
        // Insert Product into Database
        $stmt = $conn->prepare("INSERT INTO products (category_id, artisan_id, product_name, description, price_lkr, price_usd, stock_quantity, main_image, is_biodegradable, is_recyclable, is_customizable) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iissddisiii", $category_id, $artisan_id, $product_name, $description, $price_lkr, $price_usd, $stock_quantity, $db_image_path, $is_biodegradable, $is_recyclable, $is_customizable);
        
        if ($stmt->execute()) {
            $product_id = $stmt->insert_id;

            // 4. Handle Multiple Gallery Images (Angle Images) Upload
            if (isset($_FILES['gallery_images'])) {
                $file_count = count($_FILES['gallery_images']['name']);
                for ($i = 0; $i < $file_count; $i++) {
                    if ($_FILES['gallery_images']['error'][$i] == 0) {
                        $gal_name = $_FILES['gallery_images']['name'][$i];
                        $gal_tmp = $_FILES['gallery_images']['tmp_name'][$i];
                        $new_gal_name = time() . '_gal_' . $i . '_' . basename($gal_name);
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

            $success = "New handcrafted product and gallery images added successfully!";
        } else {
            $error = "Database Error: " . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Product - CraftedCeylon Admin</title>
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

        .dark-card {
            background: var(--bg-card);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--border-color);
            border-radius: 18px;
            box-shadow: 0 16px 40px rgba(0, 0, 0, 0.5);
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

        .form-control::placeholder {
            color: #6c757d;
        }

        .form-check-input {
            background-color: rgba(18, 18, 22, 0.8);
            border-color: var(--border-color);
        }

        .form-check-input:checked {
            background-color: var(--accent-green);
            border-color: var(--accent-green);
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

            <!-- Main Content Container with Left Margin Offset -->
            <div class="col-md-9 col-lg-10 ms-auto main-content-wrapper p-4 p-md-5">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="fw-bold text-white mb-1">Add New Handcrafted Product</h2>
                        <p class="text-secondary small mb-0">Publish sustainable Ceylon crafts using the dark management interface.</p>
                    </div>
                    <a href="products.php" class="btn btn-outline-light btn-sm"><i class="bi bi-arrow-left me-1"></i> Back to Products</a>
                </div>

                <?php if(!empty($success)): ?>
                    <div class="alert alert-success bg-success text-white border-0 shadow-sm"><?php echo $success; ?></div>
                <?php endif; ?>
                <?php if(!empty($error)): ?>
                    <div class="alert alert-danger bg-danger text-white border-0 shadow-sm"><?php echo $error; ?></div>
                <?php endif; ?>

                <div class="dark-card p-4 p-md-5">
                    <form action="product-add.php" method="POST" enctype="multipart/form-data">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold small text-light">Product Name</label>
                                <input type="text" name="product_name" class="form-control" required placeholder="e.g. Handcrafted Wooden Elephant">
                            </div>

                            <!-- Category Single Field with Datalist -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold small text-light">Category (Select or Type New)</label>
                                <input class="form-control" list="categoryOptions" name="category_name" required placeholder="Type or select category...">
                                <datalist id="categoryOptions">
                                    <?php
                                    $cat_res = $conn->query("SELECT category_name FROM categories");
                                    while($cat = $cat_res->fetch_assoc()) {
                                        echo '<option value="'.htmlspecialchars($cat['category_name']).'">';
                                    }
                                    ?>
                                </datalist>
                            </div>
                        </div>

                        <div class="row">
                            <!-- Artisan Single Field with Datalist -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold small text-light">Artisan / Creator (Select or Type New)</label>
                                <input class="form-control mb-2" list="artisanOptions" name="artisan_name" placeholder="Type or select artisan name...">
                                <datalist id="artisanOptions">
                                    <?php
                                    $art_res = $conn->query("SELECT artisan_name FROM artisans");
                                    while($art = $art_res->fetch_assoc()) {
                                        echo '<option value="'.htmlspecialchars($art['artisan_name']).'">';
                                    }
                                    ?>
                                </datalist>
                                <input type="text" name="artisan_location" class="form-control form-control-sm" placeholder="Artisan location (e.g. Galle, Sri Lanka)">
                            </div>

                            <!-- Live Price Calculation fields -->
                            <div class="col-md-3 mb-3">
                                <label class="form-label fw-bold small text-light">Price in LKR (Rs.)</label>
                                <input type="number" step="0.01" id="price_lkr" name="price_lkr" class="form-control" required placeholder="0.00" oninput="calculateLiveUSD(<?php echo $current_exchange_rate; ?>)">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label fw-bold small text-light">Price in USD ($) <small class="text-secondary">(Auto rate: <?php echo $current_exchange_rate; ?>)</small></label>
                                <input type="number" step="0.01" id="price_usd" name="price_usd" class="form-control" required placeholder="0.00" readonly>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold small text-light">Stock Quantity</label>
                                <input type="number" name="stock_quantity" class="form-control" required placeholder="Available units">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold small text-light">Main Cover Image</label>
                                <input type="file" name="main_image" class="form-control" required accept="image/*">
                            </div>
                        </div>

                        <!-- Multiple Gallery Images for Angles -->
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-light">Additional Angle Images (Multiple Select)</label>
                            <input type="file" name="gallery_images[]" class="form-control" multiple accept="image/*">
                            <small class="text-secondary">You can select multiple images at once (Ctrl + Click or drag-select) to show different angles of the product.</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-light">Product Description</label>
                            <textarea name="description" class="form-control" rows="4" required placeholder="Describe craftsmanship, materials used, dimensions..."></textarea>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold small text-light d-block">Eco-Friendly & Special Badges</label>
                            <div class="form-check form-check-inline text-light">
                                <input class="form-check-input" type="checkbox" name="is_biodegradable" id="bioCheck" value="1">
                                <label class="form-check-label small" for="bioCheck">100% Biodegradable</label>
                            </div>
                            <div class="form-check form-check-inline text-light">
                                <input class="form-check-input" type="checkbox" name="is_recyclable" id="recCheck" value="1">
                                <label class="form-check-label small" for="recCheck">Recyclable Material</label>
                            </div>
                            <div class="form-check form-check-inline text-light">
                                <input class="form-check-input" type="checkbox" name="is_customizable" id="custCheck" value="1">
                                <label class="form-check-label small" for="custCheck">Customizable (Name/Message)</label>
                            </div>
                        </div>

                        <button type="submit" name="add_product" class="btn btn-success px-4 fw-bold shadow-sm">
                            <i class="bi bi-plus-circle me-1"></i> Save and Publish Product
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Live Exchange Rate JS Calculation Script -->
    <script>
        function calculateLiveUSD(exchangeRate) {
            let lkrInput = document.getElementById('price_lkr');
            let usdInput = document.getElementById('price_usd');
            let lkrVal = parseFloat(lkrInput.value);
            
            if (!isNaN(lkrVal) && exchangeRate > 0) {
                let usdVal = (lkrVal / exchangeRate).toFixed(2);
                usdInput.value = usdVal;
            } else {
                usdInput.value = '';
            }
        }
    </script>
</body>
</html>