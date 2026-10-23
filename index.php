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

<!-- Hero Banner Section -->
<div class="p-5 mb-4 bg-body-tertiary rounded-3 border mt-3">
    <div class="container-fluid py-5 text-center">
        <h1 class="display-5 fw-bold text-success">Crafted with Love from Ceylon</h1>
        <p class="col-md-8 fs-4 mx-auto text-muted">
            Discover authentic local handmade crafts, eco-friendly products, and traditional creations directly from Sri Lankan artisans.
        </p>
        <a href="shop.php" class="btn btn-success btn-lg px-4 gap-3 mt-2">
            <i class="bi bi-bag-fill"></i> Explore Shop Catalog
        </a>
    </div>
</div>

<!-- Features Highlights -->
<div class="container my-5">
    <div class="row g-4 text-center">
        <div class="col-md-4">
            <div class="p-3 border bg-body rounded shadow-sm h-100">
                <i class="bi bi-shield-check text-success fs-1 mb-2"></i>
                <h5 class="fw-bold">100% Handcrafted</h5>
                <p class="text-muted small">Created by skilled local artisans using traditional and sustainable methods.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="p-3 border bg-body rounded shadow-sm h-100">
                <i class="bi bi-recycle text-success fs-1 mb-2"></i>
                <h5 class="fw-bold">Eco-Friendly & Sustainable</h5>
                <p class="text-muted small">Biodegradable and recyclable packaging supporting a greener planet.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="p-3 border bg-body rounded shadow-sm h-100">
                <i class="bi bi-currency-exchange text-success fs-1 mb-2"></i>
                <h5 class="fw-bold">LKR & USD Pricing</h5>
                <p class="text-muted small">Seamless currency toggling with live exchange rates for international patrons.</p>
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
                    <div class="card h-100 shadow-sm border position-relative">
                        <!-- Wishlist Heart Icon Button -->
                        <a href="<?php echo $wishlist_toggle_url; ?>" class="position-absolute top-0 end-0 m-2 bg-white rounded-circle p-2 shadow-sm text-decoration-none d-flex align-items-center justify-content-center" style="width: 35px; height: 35px; z-index: 10;" title="<?php echo $is_wishlisted ? 'Remove from Wishlist' : 'Add to Wishlist'; ?>">
                            <i class="bi <?php echo $is_wishlisted ? 'bi-heart-fill text-danger' : 'bi-heart text-secondary'; ?>"></i>
                        </a>

                        <img src="<?php echo htmlspecialchars($row['main_image']); ?>" class="card-img-top" alt="Product Image" style="height: 200px; object-fit: cover;">
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title fw-bold"><?php echo htmlspecialchars($row['product_name']); ?></h5>
                            <p class="card-text text-muted small"><?php echo substr(htmlspecialchars($row['description']), 0, 60); ?>...</p>
                            
                            <!-- Badges -->
                            <div class="mb-2">
                                <?php if(isset($row['is_biodegradable']) && $row['is_biodegradable']): ?>
                                    <span class="badge bg-success-subtle text-success border border-success">Biodegradable</span>
                                <?php endif; ?>
                                <?php if(isset($row['is_customizable']) && $row['is_customizable']): ?>
                                    <span class="badge bg-info-subtle text-info border border-info">Customizable</span>
                                <?php endif; ?>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mt-auto">
                                <span class="fw-bold text-success fs-5"><?php echo formatPrice($base_price_lkr); ?></span>
                                <a href="product-detail.php?id=<?php echo $row['product_id']; ?>" class="btn btn-outline-success btn-sm">View Details</a>
                            </div>
                        </div>
                    </div>
                </div>
        <?php 
            }
        } else {
            echo '<div class="col-12 text-center text-muted"><p>No products available at the moment. Please check back later or add products via Admin Panel.</p></div>';
        }
        ?>
    </div>
</div>

<?php
// Include footer
include 'includes/footer.php';
?>