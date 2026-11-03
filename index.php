<?php
// Include database connection
require_once 'config/db.php';
session_start();

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
    
    header("Location: index.php");
    exit();
}

// Include header after header redirects are handled
include 'includes/header.php';

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
?>

<!-- Creative Blurred Background (cover.jpg) & Responsive Dynamic Light/Dark Theme Support -->
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
        border: 1px solid var(--border-color, rgba(255, 255, 255, 0.1));
        border-radius: 18px;
        box-shadow: 0 16px 40px rgba(0, 0, 0, 0.4);
        color: var(--text-main, #ffffff);
        transition: all 0.3s ease;
    }

    .dark-glass-banner {
        background: var(--bg-card, rgba(26, 26, 40, 0.8));
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid var(--border-color, rgba(255, 255, 255, 0.1));
        border-radius: 20px;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5);
        color: var(--text-main, #ffffff);
    }

    .btn-action-custom {
        font-size: 13px !important;
        font-weight: 600 !important;
        padding: 8px 10px !important;
        border-radius: 8px !important;
    }

    /* Details Button Creative Styling (Hover කර විට පමණක් කොළ පැහැ වීම) */
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

<!-- Hero Banner Section -->
<div class="container my-4">
    <div class="dark-glass-banner p-5 text-center">
        <div class="container-fluid py-4">
            <h1 class="display-5 fw-bold text-success mb-3">Crafted with Love from Ceylon</h1>
            <p class="col-md-8 fs-5 mx-auto text-secondary mb-4">
                Discover authentic local handmade crafts, eco-friendly products, and traditional creations directly from Sri Lankan artisans.
            </p>
            <a href="shop.php" class="btn btn-success btn-lg px-4 gap-3 shadow-sm fw-bold">
                <i class="bi bi-bag-fill me-1"></i> Explore Shop Catalog
            </a>
        </div>
    </div>
</div>

<!-- Features Highlights -->
<div class="container my-5">
    <div class="row g-4 text-center">
        <div class="col-md-4">
            <div class="dark-glass-card p-4 h-100 d-flex flex-column align-items-center justify-content-center">
                <i class="bi bi-shield-check text-success fs-1 mb-2"></i>
                <h5 class="fw-bold">100% Handcrafted</h5>
                <p class="text-secondary small mb-0">Created by skilled local artisans using traditional and sustainable methods.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="dark-glass-card p-4 h-100 d-flex flex-column align-items-center justify-content-center">
                <i class="bi bi-recycle text-success fs-1 mb-2"></i>
                <h5 class="fw-bold">Eco-Friendly & Sustainable</h5>
                <p class="text-secondary small mb-0">Biodegradable and recyclable packaging supporting a greener planet.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="dark-glass-card p-4 h-100 d-flex flex-column align-items-center justify-content-center">
                <i class="bi bi-currency-exchange text-success fs-1 mb-2"></i>
                <h5 class="fw-bold">LKR & USD Pricing</h5>
                <p class="text-secondary small mb-0">Seamless currency toggling with live exchange rates for international patrons.</p>
            </div>
        </div>
    </div>
</div>

<!-- Featured Products Section -->
<div class="container my-5">
    <h2 class="fw-bold mb-4 text-center"><i class="bi bi-stars text-warning"></i> Featured Handcrafted Creations</h2>
    
    <div class="row g-4">
        <?php
        // Fetch 4 latest products from database
        $sql = "SELECT * FROM products ORDER BY created_at DESC LIMIT 4";
        $result = $conn->query($sql);

        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $base_price_lkr = isset($row['price_lkr']) ? $row['price_lkr'] : ($row['price'] ?? 0);
                $is_wishlisted = in_array($row['product_id'], $user_wishlist);
                $wishlist_toggle_url = $user_id ? "index.php?toggle_wishlist={$row['product_id']}" : "login.php";
        ?>
                <div class="col-lg-3 col-md-6">
                    <div class="dark-glass-card h-100 d-flex flex-column position-relative overflow-hidden p-3">
                        <!-- Wishlist Heart Icon Button -->
                        <a href="<?php echo $wishlist_toggle_url; ?>" class="position-absolute top-0 end-0 m-3 bg-dark bg-opacity-75 rounded-circle p-2 shadow text-decoration-none d-flex align-items-center justify-content-center border border-secondary border-opacity-25" style="width: 38px; height: 38px; z-index: 10;" title="<?php echo $is_wishlisted ? 'Remove from Wishlist' : 'Add to Wishlist'; ?>">
                            <i class="bi <?php echo $is_wishlisted ? 'bi-heart-fill text-danger' : 'bi-heart text-white'; ?>"></i>
                        </a>

                        <a href="product-detail.php?id=<?php echo $row['product_id']; ?>" class="rounded overflow-hidden mb-3 d-block text-decoration-none">
                            <img src="<?php echo htmlspecialchars($row['main_image']); ?>" class="w-100" alt="Product Image" style="height: 200px; object-fit: cover;">
                        </a>

                        <div class="card-body d-flex flex-column p-0 flex-grow-1">
                            <h5 class="card-title fw-bold mb-2">
                                <a href="product-detail.php?id=<?php echo $row['product_id']; ?>" class="text-decoration-none text-reset"><?php echo htmlspecialchars($row['product_name']); ?></a>
                            </h5>
                            
                            <!-- Badges -->
                            <div class="mb-3">
                                <?php if(isset($row['is_biodegradable']) && $row['is_biodegradable']): ?>
                                    <span class="badge bg-success bg-opacity-25 text-success border border-success me-1">Biodegradable</span>
                                <?php endif; ?>
                                <?php if(isset($row['is_customizable']) && $row['is_customizable']): ?>
                                    <span class="badge bg-info bg-opacity-25 text-info border border-info">Customizable</span>
                                <?php endif; ?>
                            </div>

                            <div class="mt-auto pt-2 border-top border-secondary border-opacity-25">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span class="fw-bold text-success fs-5">
                                        <?php 
                                        $currency = isset($_SESSION['currency']) ? $_SESSION['currency'] : 'LKR';
                                        echo function_exists('formatPrice') ? formatPrice($base_price_lkr) : (($currency == 'USD') ? '$ ' . number_format($row['price_usd'], 2) : 'Rs. ' . number_format($base_price_lkr, 2)); 
                                        ?>
                                    </span>
                                </div>

                                <!-- Action Buttons: Details Button on Left (Hover කළ විට පමණක් කොළ පැහැ වන creative style), Compact Creative Red Cart Button on Right -->
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
            echo '<div class="col-12 text-center text-secondary py-5"><p class="dark-glass-card p-4">No products available at the moment. Please check back later or add products via Admin Panel.</p></div>';
        }
        ?>
    </div>
</div>

<?php
// Include footer
include 'includes/footer.php';
?>