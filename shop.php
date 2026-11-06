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

<!-- Creative Blurred Background & Responsive Dynamic Light/Dark Theme Support (Matched with index.php) -->
<style>
    body {
        position: relative;
        background-color: var(--bg-dark, #0d0d12);
        color: var(--text-main, #ffffff);
        font-family: 'Plus Jakarta Sans', sans-serif;
    }

    /* cover.jpg Background restored with Blur & Brightness */
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
        border-radius: 16px;
        box-shadow: 0 16px 40px rgba(0, 0, 0, 0.4);
        color: var(--text-main, #ffffff);
        transition: all 0.3s ease;
    }

    .form-control, .form-select {
        background-color: var(--bg-card, rgba(18, 18, 22, 0.85));
        border: 1px solid var(--border-color, rgba(255, 255, 255, 0.12));
        color: var(--text-main, #ffffff);
        border-radius: 10px;
        font-size: 13px;
    }
    .form-control:focus, .form-select:focus {
        background-color: var(--bg-card, rgba(18, 18, 22, 0.95));
        border-color: #198754;
        color: var(--text-main, #ffffff);
        box-shadow: 0 0 0 0.25rem rgba(25, 135, 84, 0.25);
    }

    .btn-action-custom {
        font-size: 12px !important;
        font-weight: 600 !important;
        padding: 7px 8px !important;
        border-radius: 8px !important;
    }

    /* Creative Details Button Styling (Matching index.php) */
    .btn-details-creative {
        background: linear-gradient(135deg, rgba(13, 202, 240, 0.15), rgba(13, 110, 253, 0.15));
        color: #0dcaf0;
        border: 1px solid rgba(13, 202, 240, 0.4);
        transition: all 0.3s ease;
    }
    .btn-details-creative:hover {
        background: linear-gradient(135deg, #0dcaf0, #0d6efd);
        color: #ffffff !important;
        border-color: #0dcaf0;
        box-shadow: 0 4px 15px rgba(13, 202, 240, 0.4);
        transform: translateY(-2px);
    }

    /* Creative Cart Button Styling (Matching index.php) */
    .btn-cart-creative {
        background: linear-gradient(135deg, rgba(25, 135, 84, 0.15), rgba(20, 108, 67, 0.15));
        color: #20c997;
        border: 1px solid rgba(25, 135, 84, 0.4);
        transition: all 0.3s ease;
    }
    .btn-cart-creative:hover {
        background: linear-gradient(135deg, #198754, #146c43);
        color: #ffffff !important;
        border-color: #198754;
        box-shadow: 0 4px 15px rgba(25, 135, 84, 0.4);
        transform: translateY(-2px);
    }
</style>

<div class="container my-5">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-md-12 text-center">
            <h2 class="fw-bold text-success"><i class="bi bi-shop"></i> CraftedCeylon Shop Catalog</h2>
            <p class="text-secondary small">Explore our curated collection of authentic handmade masterpieces.</p>
        </div>
    </div>

    <!-- Top Control Bar: Search Bar, Category Dropdown & Sort By Dropdown -->
    <div class="dark-glass-card p-3 mb-4">
        <form action="shop.php" method="GET" class="row g-2 align-items-center">
            <!-- Search Bar -->
            <div class="col-lg-4 col-md-4">
                <div class="input-group input-group-sm">
                    <input type="text" name="search" class="form-control" placeholder="Search crafts..." value="<?php echo htmlspecialchars($search_query); ?>">
                    <button class="btn btn-success" type="submit"><i class="bi bi-search"></i></button>
                    <?php if(!empty($search_query) || !empty($category_id) || ($sort_by != 'newest')): ?>
                        <a href="shop.php" class="btn btn-outline-secondary" title="Reset Filters"><i class="bi bi-x-lg"></i></a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Category Dropdown -->
            <div class="col-lg-4 col-md-4">
                <div class="d-flex align-items-center">
                    <label class="me-2 small fw-bold text-nowrap text-secondary">Category:</label>
                    <select name="category" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Products</option>
                        <?php
                        $cat_sql = "SELECT * FROM categories";
                        $cat_result = $conn->query($cat_sql);
                        if ($cat_result && $cat_result->num_rows > 0) {
                            while ($cat = $cat_result->fetch_assoc()) {
                                $selected = ($category_id == $cat['category_id']) ? 'selected' : '';
                                echo '<option value="' . $cat['category_id'] . '" ' . $selected . '>' . htmlspecialchars($cat['category_name']) . '</option>';
                            }
                        }
                        ?>
                    </select>
                </div>
            </div>

            <!-- Sort By Dropdown -->
            <div class="col-lg-4 col-md-4">
                <div class="d-flex align-items-center">
                    <label class="me-2 small fw-bold text-nowrap text-secondary">Sort By:</label>
                    <select name="sort" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="newest" <?php if($sort_by == 'newest') echo 'selected'; ?>>Newest Arrivals</option>
                        <option value="price_low" <?php if($sort_by == 'price_low') echo 'selected'; ?>>Price: Low to High</option>
                        <option value="price_high" <?php if($sort_by == 'price_high') echo 'selected'; ?>>Price: High to Low</option>
                    </select>
                </div>
            </div>

            <!-- Preserve Search Query -->
            <?php if(!empty($search_query)): ?>
                <input type="hidden" name="search" value="<?php echo htmlspecialchars($search_query); ?>">
            <?php endif; ?>
        </form>
    </div>

    <!-- Product Cards Grid: Exactly 4 Products per Row (Matching index.php structure) -->
    <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-4">
        <?php
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $base_price_lkr = isset($row['price_lkr']) ? $row['price_lkr'] : ($row['price'] ?? 0);
                $is_wishlisted = in_array($row['product_id'], $user_wishlist);
                
                $current_params = $_GET;
                $current_params['toggle_wishlist'] = $row['product_id'];
                $wishlist_toggle_url = 'shop.php?' . http_build_query($current_params);
        ?>
                <div class="col">
                    <div class="dark-glass-card h-100 d-flex flex-column position-relative overflow-hidden p-3">
                        <!-- Wishlist Heart Icon Button -->
                        <a href="<?php echo $user_id ? $wishlist_toggle_url : 'login.php'; ?>" class="position-absolute top-0 end-0 m-3 bg-dark bg-opacity-75 rounded-circle p-2 shadow text-decoration-none d-flex align-items-center justify-content-center border border-secondary border-opacity-25" style="width: 36px; height: 36px; z-index: 10;" title="<?php echo $is_wishlisted ? 'Remove from Wishlist' : 'Add to Wishlist'; ?>">
                            <i class="bi <?php echo $is_wishlisted ? 'bi-heart-fill text-danger' : 'bi-heart text-white'; ?>" style="font-size: 14px;"></i>
                        </a>

                        <a href="product-detail.php?id=<?php echo $row['product_id']; ?>" class="rounded overflow-hidden mb-3 d-block text-decoration-none">
                            <img src="<?php echo htmlspecialchars($row['main_image']); ?>" class="w-100" alt="Product Image" style="height: 190px; object-fit: cover;">
                        </a>

                        <div class="card-body d-flex flex-column p-0 flex-grow-1">
                            <h6 class="card-title fw-bold mb-2 text-truncate" title="<?php echo htmlspecialchars($row['product_name']); ?>">
                                <a href="product-detail.php?id=<?php echo $row['product_id']; ?>" class="text-decoration-none text-reset"><?php echo htmlspecialchars($row['product_name']); ?></a>
                            </h6>
                            
                            <!-- Badges -->
                            <div class="mb-2">
                                <?php if(isset($row['is_biodegradable']) && $row['is_biodegradable']): ?>
                                    <span class="badge bg-success bg-opacity-25 text-success border border-success me-1" style="font-size: 9px;">Biodegradable</span>
                                <?php endif; ?>
                                <?php if(isset($row['is_customizable']) && $row['is_customizable']): ?>
                                    <span class="badge bg-info bg-opacity-25 text-info border border-info" style="font-size: 9px;">Customizable</span>
                                <?php endif; ?>
                            </div>

                            <div class="mt-auto pt-2 border-top border-secondary border-opacity-25">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="fw-bold text-success fs-6">
                                        <?php 
                                        $currency = isset($_SESSION['currency']) ? $_SESSION['currency'] : 'LKR';
                                        echo function_exists('formatPrice') ? formatPrice($base_price_lkr) : (($currency == 'USD') ? '$ ' . number_format($row['price_usd'], 2) : 'Rs. ' . number_format($base_price_lkr, 2)); 
                                        ?>
                                    </span>
                                </div>

                                <!-- Action Buttons: Details Button on Left, Cart / Out of Stock on Right -->
                                <div class="d-flex gap-2 align-items-center">
                                    <a href="product-detail.php?id=<?php echo $row['product_id']; ?>" class="btn btn-details-creative flex-grow-1 btn-action-custom d-flex align-items-center justify-content-center shadow-sm text-decoration-none">
                                        <i class="bi bi-info-circle me-1"></i> Details
                                    </a>
                                    
                                    <?php if(isset($row['stock_quantity']) && $row['stock_quantity'] > 0): ?>
                                        <form action="cart-actions.php" method="POST" style="width: 42%;">
                                            <input type="hidden" name="product_id" value="<?php echo $row['product_id']; ?>">
                                            <input type="hidden" name="quantity" value="1">
                                            <input type="hidden" name="selected_color" value="Standard / Natural">
                                            <button type="submit" name="add_to_cart" class="btn btn-cart-creative w-100 btn-action-custom d-flex align-items-center justify-content-center shadow-sm">
                                                <i class="bi bi-cart-plus me-1"></i> Cart
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-secondary btn-action-custom text-nowrap" disabled style="width: 42%; font-size: 10px !important;">Out of Stock</button>
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

<?php
// Include footer
include 'includes/footer.php';
?>