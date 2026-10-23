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

<div class="container my-5">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-md-12 text-center">
            <h2 class="fw-bold text-success"><i class="bi bi-shop"></i> CraftedCeylon Shop Catalog</h2>
            <p class="text-muted">Browse our collection of authentic handmade crafts and eco-friendly products.</p>
        </div>
    </div>

    <div class="row">
        <!-- Sidebar Filters -->
        <div class="col-lg-3 mb-4">
            <div class="card shadow-sm border p-3">
                <h5 class="fw-bold mb-3"><i class="bi bi-filter"></i> Categories</h5>
                <div class="list-group list-group-flush mb-4">
                    <a href="shop.php" class="list-group-item list-group-item-action <?php echo empty($category_id) ? 'active bg-success border-success' : ''; ?>">
                        All Products
                    </a>
                    <?php
                    // Fetch categories for sidebar
                    $cat_sql = "SELECT * FROM categories";
                    $cat_result = $conn->query($cat_sql);
                    if ($cat_result && $cat_result->num_rows > 0) {
                        while ($cat = $cat_result->fetch_assoc()) {
                            $active = ($category_id == $cat['category_id']) ? 'active bg-success border-success' : '';
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
            <div class="d-flex justify-content-between align-items-center bg-body-tertiary p-3 rounded mb-4 border">
                <span class="text-muted small">Showing items from Ceylon artisans</span>
                <form action="shop.php" method="GET" class="d-flex align-items-center">
                    <?php if(!empty($category_id)): ?>
                        <input type="hidden" name="category" value="<?php echo $category_id; ?>">
                    <?php endif; ?>
                    <?php if(!empty($search_query)): ?>
                        <input type="hidden" name="search" value="<?php echo htmlspecialchars($search_query); ?>">
                    <?php endif; ?>
                    <label class="me-2 small fw-bold text-nowrap">Sort By:</label>
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
                        // Base price in LKR (fallback to price column if price_lkr is not set)
                        $base_price_lkr = isset($row['price_lkr']) ? $row['price_lkr'] : ($row['price'] ?? 0);
                        $is_wishlisted = in_array($row['product_id'], $user_wishlist);
                        
                        // Build current URL params for wishlist toggle redirect
                        $current_params = $_GET;
                        $current_params['toggle_wishlist'] = $row['product_id'];
                        $wishlist_toggle_url = 'shop.php?' . http_build_query($current_params);
                ?>
                        <div class="col-md-4 col-sm-6">
                            <div class="card h-100 shadow-sm border position-relative">
                                <!-- Wishlist Heart Icon Button -->
                                <a href="<?php echo $user_id ? $wishlist_toggle_url : 'login.php'; ?>" class="position-absolute top-0 end-0 m-2 bg-white rounded-circle p-2 shadow-sm text-decoration-none d-flex align-items-center justify-content-center" style="width: 35px; height: 35px; z-index: 10;" title="<?php echo $is_wishlisted ? 'Remove from Wishlist' : 'Add to Wishlist'; ?>">
                                    <i class="bi <?php echo $is_wishlisted ? 'bi-heart-fill text-danger' : 'bi-heart text-secondary'; ?>"></i>
                                </a>

                                <img src="<?php echo htmlspecialchars($row['main_image']); ?>" class="card-img-top" alt="Product Image" style="height: 190px; object-fit: cover;">
                                <div class="card-body d-flex flex-column">
                                    <h6 class="card-title fw-bold"><?php echo htmlspecialchars($row['product_name']); ?></h6>
                                    <p class="card-text text-muted small"><?php echo substr(htmlspecialchars($row['description']), 0, 50); ?>...</p>
                                    
                                    <!-- Badges -->
                                    <div class="mb-2">
                                        <?php if(isset($row['is_biodegradable']) && $row['is_biodegradable']): ?>
                                            <span class="badge bg-success-subtle text-success border border-success" style="font-size: 10px;">Biodegradable</span>
                                        <?php endif; ?>
                                        <?php if(isset($row['is_customizable']) && $row['is_customizable']): ?>
                                            <span class="badge bg-info-subtle text-info border border-info" style="font-size: 10px;">Customizable</span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="d-flex justify-content-between align-items-center mt-auto">
                                        <!-- Automated Dynamic Price Formatting (LKR / Live USD conversion) -->
                                        <span class="fw-bold text-success"><?php echo formatPrice($base_price_lkr); ?></span>
                                        <a href="product-detail.php?id=<?php echo $row['product_id']; ?>" class="btn btn-outline-success btn-sm">View Details</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                <?php
                    }
                } else {
                    echo '<div class="col-12 text-center text-muted py-5"><h5>No products found matching your criteria.</h5></div>';
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