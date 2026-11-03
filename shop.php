<?php
// Include database connection
require_once 'config/db.php';
session_start();

// Check currency session and user login
$currency = isset($_SESSION['currency']) ? $_SESSION['currency'] : 'LKR';
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

// Handle Add/Remove Wishlist request before any HTML output
if (isset($_GET['toggle_wishlist']) && $user_id) {
    $prod_id = intval($_GET['toggle_wishlist']);
    
    // Check if already in wishlist
    $check_w = $conn->prepare("SELECT wishlist_id FROM wishlist WHERE user_id = ? AND product_id = ?");
    $check_w->bind_param("ii", $user_id, $prod_id);
    $check_w->execute();
    $res_w = $check_w->get_result();
    
    if ($res_w->num_rows > 0) {
        // Remove from wishlist
        $del_w = $conn->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?");
        $del_w->bind_param("ii", $user_id, $prod_id);
        $del_w->execute();
    } else {
        // Add to wishlist
        $add_w = $conn->prepare("INSERT INTO wishlist (user_id, product_id) VALUES (?, ?)");
        $add_w->bind_param("ii", $user_id, $prod_id);
        $add_w->execute();
    }
    
    // Keep current query params in redirect
    $query_params = $_GET;
    unset($query_params['toggle_wishlist']);
    $redirect_url = 'shop.php' . (!empty($query_params) ? '?' . http_build_query($query_params) : '');
    header("Location: " . $redirect_url);
    exit();
}

// Include header after redirects are handled
include 'includes/header.php';

// Get filter and search parameters from URL if set
$category_id = isset($_GET['category']) ? $_GET['category'] : '';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

// Fetch user's current wishlist product IDs for highlighting heart icon
$user_wishlist = [];
if ($user_id) {
    $uw_stmt = $conn->prepare("SELECT product_id FROM wishlist WHERE user_id = ?");
    $uw_stmt->bind_param("i", $user_id);
    $uw_stmt->execute();
    $uw_res = $uw_stmt->get_result();
    while ($uw_row = $uw_res->fetch_assoc()) {
        $user_wishlist[] = $uw_row['product_id'];
    }
}

// Base SQL query
$sql = "SELECT p.*, c.category_name FROM products p LEFT JOIN categories c ON p.category_id = c.category_id WHERE 1=1";

// Apply category filter
if (!empty($category_id)) {
    $sql .= " AND p.category_id = " . intval($category_id);
}

// Apply search query filter
if (!empty($search_query)) {
    $escaped_search = $conn->real_escape_string($search_query);
    $sql .= " AND (p.product_name LIKE '%$escaped_search%' OR p.description LIKE '%$escaped_search%')";
}

// Apply sorting (Using price_lkr as base for sorting)
if ($sort_by == 'price_low') {
    $sql .= " ORDER BY price_lkr ASC";
} elseif ($sort_by == 'price_high') {
    $sql .= " ORDER BY price_lkr DESC";
} else {
    $sql .= " ORDER BY p.created_at DESC";
}

$result = $conn->query($sql);
?>

<!-- Creative Blurred Background (cover.jpg) & Dynamic Light/Dark Theme Support -->
<style>
    body {
        position: relative;
        background-color: var(--bg-dark, #0d0d12);
        color: var(--text-main, #ffffff);
        font-family: 'Plus Jakarta Sans', sans-serif;
    }

    /* cover.jpg Background Restored with Blur & Brightness */
    body::before {
        content: "";
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-image: url('assets/images/cover.jpg');
        background-size: cover;
        background-position: center;
        filter: blur(20px) brightness(0.35);
        z-index: -1;
    }

    .dark-glass-card {
        background: var(--bg-card, rgba(26, 26, 40, 0.85));
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid var(--border-color, rgba(255, 255, 255, 0.08));
        border-radius: 18px;
        box-shadow: 0 16px 40px rgba(0, 0, 0, 0.4);
        color: var(--text-main, #ffffff);
        transition: all 0.3s ease;
    }

    .form-control, .form-select {
        background-color: var(--bg-card, rgba(18, 18, 22, 0.8));
        border: 1px solid var(--border-color, rgba(255, 255, 255, 0.1));
        color: var(--text-main, #ffffff);
        border-radius: 10px;
    }
    .form-control:focus, .form-select:focus {
        background-color: var(--bg-card, rgba(18, 18, 22, 0.95));
        border-color: #198754;
        color: var(--text-main, #ffffff);
        box-shadow: 0 0 0 0.25rem rgba(25, 135, 84, 0.25);
    }
    
    .list-group-item {
        background-color: transparent;
        color: var(--text-secondary, #adb5bd);
        border-color: var(--border-color, rgba(255, 255, 255, 0.08));
    }
    .list-group-item:hover {
        background-color: rgba(25, 135, 84, 0.15);
        color: var(--text-main, #ffffff);
    }
    .list-group-item.active {
        background-color: #198754 !important;
        border-color: #198754 !important;
        color: #ffffff !important;
    }

    .btn-action-custom {
        font-size: 13px !important;
        font-weight: 600 !important;
        padding: 8px 10px !important;
        border-radius: 8px !important;
    }

    /* Details Button Creative Styling (Hover කළ විට පමණක් කොළ පැහැ වීම) */
    .btn-details-creative {
        background: rgba(13, 202, 240, 0.12);
        color: #0dcaf0;
        border: 1px solid rgba(13, 202, 240, 0.4);
        transition: all 0.3s ease;
    }

    .btn-details-creative:hover {
        background-color: #198754 !important;
        color: #ffffff !important;
        border-color: #198754 !important;
        box-shadow: 0 4px 15px rgba(25, 135, 84, 0.4);
        transform: translateY(-2px);
    }
</style>

<div class="container my-5">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-md-12 text-center">
            <h2 class="fw-bold text-success"><i class="bi bi-shop"></i> CraftedCeylon Shop Catalog</h2>
            <p class="text-secondary">Browse our collection of authentic handmade crafts and eco-friendly products.</p>
        </div>
    </div>

    <div class="row">
        <!-- Sidebar Filters -->
        <div class="col-lg-3 mb-4">
            <div class="dark-glass-card p-4">
                <h5 class="fw-bold mb-3"><i class="bi bi-filter"></i> Categories</h5>
                <div class="list-group list-group-flush mb-4 rounded overflow-hidden border border-secondary border-opacity-25">
                    <a href="shop.php" class="list-group-item list-group-item-action <?php echo empty($category_id) ? 'active' : ''; ?>">
                        All Products
                    </a>
                    <?php
                    // Fetch categories for sidebar
                    $cat_sql = "SELECT * FROM categories";
                    $cat_result = $conn->query($cat_sql);
                    if ($cat_result && $cat_result->num_rows > 0) {
                        while ($cat = $cat_result->fetch_assoc()) {
                            $active = ($category_id == $cat['category_id']) ? 'active' : '';
                            echo '<a href="shop.php?category=' . $cat['category_id'] . '" class="list-group-item list-group-item-action ' . $active . '">' . htmlspecialchars($cat['category_name']) . '</a>';
                        }
                    }
                    ?>
                </div>

                <!-- Search Box -->
                <h5 class="fw-bold mb-3"><i class="bi bi-search"></i> Search</h5>
                <form action="shop.php" method="GET" class="mb-3">
                    <?php if(!empty($category_id)): ?>
                        <input type="hidden" name="category" value="<?php echo $category_id; ?>">
                    <?php endif; ?>
                    <div class="input-group">
                        <input type="text" name="search" class="form-control form-control-sm" placeholder="Search crafts..." value="<?php echo htmlspecialchars($search_query); ?>">
                        <button class="btn btn-outline-success btn-sm" type="submit"><i class="bi bi-search"></i></button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Main Product Catalog Area -->
        <div class="col-lg-9">
            <!-- Sorting Bar -->
            <div class="dark-glass-card p-3 mb-4 d-flex justify-content-between align-items-center">
                <span class="text-secondary small">Showing items from Ceylon artisans</span>
                <form action="shop.php" method="GET" class="d-flex align-items-center">
                    <?php if(!empty($category_id)): ?>
                        <input type="hidden" name="category" value="<?php echo $category_id; ?>">
                    <?php endif; ?>
                    <?php if(!empty($search_query)): ?>
                        <input type="hidden" name="search" value="<?php echo htmlspecialchars($search_query); ?>">
                    <?php endif; ?>
                    <label class="me-2 small fw-bold text-nowrap text-secondary">Sort By:</label>
                    <select name="sort" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="newest" <?php if($sort_by == 'newest') echo 'selected'; ?>>Newest Arrivals</option>
                        <option value="price_low" <?php if($sort_by == 'price_low') echo 'selected'; ?>>Price: Low to High</option>
                        <option value="price_high" <?php if($sort_by == 'price_high') echo 'selected'; ?>>Price: High to Low</option>
                    </select>
                </form>
            </div>

            <!-- Product Cards Grid -->
            <div class="row g-4">
                <?php
                if ($result && $result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        $base_price_lkr = isset($row['price_lkr']) ? $row['price_lkr'] : ($row['price'] ?? 0);
                        $is_wishlisted = in_array($row['product_id'], $user_wishlist);
                        
                        $current_params = $_GET;
                        $current_params['toggle_wishlist'] = $row['product_id'];
                        $wishlist_toggle_url = 'shop.php?' . http_build_query($current_params);
                ?>
                        <div class="col-md-4 col-sm-6">
                            <div class="dark-glass-card h-100 d-flex flex-column position-relative overflow-hidden p-3">
                                <!-- Wishlist Heart Icon Button -->
                                <a href="<?php echo $user_id ? $wishlist_toggle_url : 'login.php'; ?>" class="position-absolute top-0 end-0 m-3 bg-dark bg-opacity-75 rounded-circle p-2 shadow text-decoration-none d-flex align-items-center justify-content-center border border-secondary border-opacity-25" style="width: 38px; height: 38px; z-index: 10;" title="<?php echo $is_wishlisted ? 'Remove from Wishlist' : 'Add to Wishlist'; ?>">
                                    <i class="bi <?php echo $is_wishlisted ? 'bi-heart-fill text-danger' : 'bi-heart text-white'; ?>"></i>
                                </a>

                                <a href="product-detail.php?id=<?php echo $row['product_id']; ?>" class="rounded overflow-hidden mb-3 d-block text-decoration-none">
                                    <img src="<?php echo htmlspecialchars($row['main_image']); ?>" class="w-100" alt="Product Image" style="height: 190px; object-fit: cover;">
                                </a>

                                <div class="card-body d-flex flex-column p-0 flex-grow-1">
                                    <h6 class="card-title fw-bold mb-2">
                                        <a href="product-detail.php?id=<?php echo $row['product_id']; ?>" class="text-decoration-none text-reset"><?php echo htmlspecialchars($row['product_name']); ?></a>
                                    </h6>
                                    
                                    
                                    <!-- Badges -->
                                    <div class="mb-3">
                                        <?php if(isset($row['is_biodegradable']) && $row['is_biodegradable']): ?>
                                            <span class="badge bg-success bg-opacity-25 text-success border border-success me-1" style="font-size: 10px;">Biodegradable</span>
                                        <?php endif; ?>
                                        <?php if(isset($row['is_customizable']) && $row['is_customizable']): ?>
                                            <span class="badge bg-info bg-opacity-25 text-info border border-info" style="font-size: 10px;">Customizable</span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="mt-auto pt-2 border-top border-secondary border-opacity-25">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <span class="fw-bold text-success fs-6">
                                                <?php 
                                                $currency = isset($_SESSION['currency']) ? $_SESSION['currency'] : 'LKR';
                                                echo function_exists('formatPrice') ? formatPrice($base_price_lkr) : (($currency == 'USD') ? '$ ' . number_format($row['price_usd'], 2) : 'Rs. ' . number_format($base_price_lkr, 2)); 
                                                ?>
                                            </span>
                                        </div>

                                        <!-- Action Buttons: Details Button on Left (Hover effect), Compact Creative Red Cart Button on Right -->
                                        <div class="d-flex gap-2 align-items-center">
                                            <!-- Details Button (Left Side - Creative Hover Effect) -->
                                            <a href="product-detail.php?id=<?php echo $row['product_id']; ?>" class="btn btn-details-creative flex-grow-1 btn-action-custom d-flex align-items-center justify-content-center shadow-sm text-decoration-none">
                                                <i class="bi bi-info-circle me-1 fs-6"></i> Details
                                            </a>
                                            
                                            <?php if(isset($row['stock_quantity']) && $row['stock_quantity'] > 0): ?>
                                                <!-- Add to Cart Form (Right Side - Compact & Creative Red Style) -->
                                                <form action="cart-actions.php" method="POST" style="width: 38%;">
                                                    <input type="hidden" name="product_id" value="<?php echo $row['product_id']; ?>">
                                                    <input type="hidden" name="quantity" value="1">
                                                    <input type="hidden" name="selected_color" value="Standard / Natural">
                                                    <button type="submit" name="add_to_cart" class="btn btn-outline-danger w-100 btn-action-custom d-flex align-items-center justify-content-center shadow-sm text-danger border-danger" style="background: rgba(220, 53, 69, 0.12);">
                                                        <i class="bi bi-cart-plus me-1 fs-6"></i> Cart
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-secondary btn-action-custom" disabled style="width: 38%;">Out of Stock</button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                <?php
                    }
                } else {
                    echo '<div class="col-12 text-center text-secondary py-5"><p class="dark-glass-card p-4">No products found matching your criteria.</p></div>';
                }
                ?>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include 'includes/footer.php';
?>