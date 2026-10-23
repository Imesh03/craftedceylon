<?php
// Include database connection
require_once 'config/db.php';
session_start();

// Check currency session & user ID before any HTML output
$currency = isset($_SESSION['currency']) ? $_SESSION['currency'] : 'LKR';
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

// Get product ID from URL
$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Handle Add/Remove Wishlist request BEFORE header includes HTML output
if (isset($_GET['toggle_wishlist']) && $user_id && $product_id > 0) {
    // Check if already in wishlist
    $check_w = $conn->prepare("SELECT wishlist_id FROM wishlist WHERE user_id = ? AND product_id = ?");
    $check_w->bind_param("ii", $user_id, $product_id);
    $check_w->execute();
    $res_w = $check_w->get_result();
    
    if ($res_w->num_rows > 0) {
        // Remove from wishlist
        $del_w = $conn->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?");
        $del_w->bind_param("ii", $user_id, $product_id);
        $del_w->execute();
    } else {
        // Add to wishlist
        $add_w = $conn->prepare("INSERT INTO wishlist (user_id, product_id) VALUES (?, ?)");
        $add_w->bind_param("ii", $user_id, $product_id);
        $add_w->execute();
    }
    
    header("Location: product-detail.php?id=" . $product_id);
    exit();
}

// Include header (HTML output starts here)
include 'includes/header.php';

// Check if current product is in user's wishlist
$is_wishlisted = false;
if ($user_id && $product_id > 0) {
    $uw_stmt = $conn->prepare("SELECT wishlist_id FROM wishlist WHERE user_id = ? AND product_id = ?");
    $uw_stmt->bind_param("ii", $user_id, $product_id);
    $uw_stmt->execute();
    if ($uw_stmt->get_result()->num_rows > 0) {
        $is_wishlisted = true;
    }
}

// Fetch product details along with artisan info
$sql = "SELECT p.*, c.category_name, a.artisan_name, a.profile_img, a.bio, a.location 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.category_id 
        LEFT JOIN artisans a ON p.artisan_id = a.artisan_id 
        WHERE p.product_id = $product_id";

$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    $product = $result->fetch_assoc();
    $base_price_lkr = isset($product['price_lkr']) ? $product['price_lkr'] : ($product['price'] ?? 0);
    $price = function_exists('formatPrice') ? formatPrice($base_price_lkr) : (($currency == 'USD') ? '$ ' . number_format($product['price_usd'], 2) : 'Rs. ' . number_format($product['price_lkr'], 2));
?>

<div class="container my-5">
    <div class="row g-5">
        <!-- Product Images & Gallery -->
        <div class="col-md-6">
            <div class="card shadow-sm border mb-3">
                <img src="<?php echo htmlspecialchars($product['main_image']); ?>" id="mainProductImg" class="card-img-top" alt="Product Image" style="height: 380px; object-fit: cover;">
            </div>
            
            <!-- Additional Gallery Images -->
            <div class="row g-2">
                <div class="col-3">
                    <img src="<?php echo htmlspecialchars($product['main_image']); ?>" class="img-thumbnail thumbnail-img border cursor-pointer" onclick="changeImage(this.src)" style="height: 80px; object-fit: cover; width: 100%;">
                </div>
                <?php
                // Fetch extra images from product_images table
                $img_sql = "SELECT * FROM product_images WHERE product_id = $product_id";
                $img_result = $conn->query($img_sql);
                if ($img_result && $img_result->num_rows > 0) {
                    while ($img = $img_result->fetch_assoc()) {
                        echo '<div class="col-3"><img src="' . htmlspecialchars($img['image_path']) . '" class="img-thumbnail thumbnail-img border cursor-pointer" onclick="changeImage(this.src)" style="height: 80px; object-fit: cover; width: 100%;"></div>';
                    }
                }
                ?>
            </div>
        </div>

        <!-- Product Details & Customization Form -->
        <div class="col-md-6">
            <span class="badge bg-secondary mb-2"><?php echo htmlspecialchars($product['category_name']); ?></span>
            <h1 class="fw-bold mb-3"><?php echo htmlspecialchars($product['product_name']); ?></h1>
            <h3 class="text-success fw-bold mb-3"><?php echo $price; ?></h3>
            
            <p class="text-muted mb-4"><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>

            <!-- Eco Badges -->
            <div class="mb-4">
                <?php if(!empty($product['is_biodegradable'])): ?>
                    <span class="badge bg-success-subtle text-success border border-success p-2 me-2"><i class="bi bi-recycle"></i> 100% Biodegradable</span>
                <?php endif; ?>
                <?php if(!empty($product['is_recyclable'])): ?>
                    <span class="badge bg-primary-subtle text-primary border border-primary p-2 me-2"><i class="bi bi-arrow-repeat"></i> Recyclable Material</span>
                <?php endif; ?>
                <span class="badge bg-warning-subtle text-dark border border-warning p-2"><i class="bi bi-patch-check"></i> Authentic Ceylon Craft</span>
            </div>

            <!-- Add to Cart Form with Customization Options -->
            <form action="cart-actions.php" method="POST" class="border p-4 rounded bg-body-tertiary">
                <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                
                <?php if(!empty($product['is_customizable'])): ?>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-info"><i class="bi bi-brush"></i> Customization (Name / Message)</label>
                        <input type="text" name="custom_text" class="form-control" placeholder="Enter name or short message to engrave/print">
                    </div>
                <?php endif; ?>

                <div class="mb-3">
                    <label class="form-label fw-bold small">Select Color / Style</label>
                    <select name="selected_color" class="form-select">
                        <option value="Standard / Natural">Standard / Natural Finish</option>
                        <option value="Dark Polish">Dark Polish</option>
                        <option value="Light Polish">Light Polish</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold small">Quantity (Available Stock: <?php echo $product['stock_quantity']; ?>)</label>
                    <input type="number" name="quantity" value="1" min="1" max="<?php echo $product['stock_quantity']; ?>" class="form-control w-25">
                </div>

                <div class="d-flex gap-2 mb-2">
                    <?php if($product['stock_quantity'] > 0): ?>
                        <button type="submit" name="add_to_cart" class="btn btn-success btn-lg w-100 fw-bold">
                            <i class="bi bi-cart-plus me-1"></i> Add to Shopping Cart
                        </button>
                    <?php else: ?>
                        <button type="button" class="btn btn-secondary btn-lg w-100 fw-bold" disabled>
                            Out of Stock
                        </button>
                    <?php endif; ?>

                    <!-- Wishlist Button Beside Cart Button -->
                    <?php 
                        $wish_url = $user_id ? "product-detail.php?id={$product_id}&toggle_wishlist={$product_id}" : "login.php";
                    ?>
                    <a href="<?php echo $wish_url; ?>" class="btn <?php echo $is_wishlisted ? 'btn-danger' : 'btn-outline-danger'; ?> btn-lg d-flex align-items-center justify-content-center px-3" title="<?php echo $is_wishlisted ? 'Remove from Wishlist' : 'Save to Wishlist'; ?>">
                        <i class="bi <?php echo $is_wishlisted ? 'bi-heart-fill' : 'bi-heart'; ?> fs-5"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Artisan Profile Section -->
    <?php if(!empty($product['artisan_name'])): ?>
    <div class="row mt-5">
        <div class="col-md-12">
            <div class="card border shadow-sm p-4 bg-body-tertiary">
                <div class="row align-items-center">
                    <div class="col-md-2 text-center">
                        <?php if(!empty($product['profile_img'])): ?>
                            <img src="<?php echo htmlspecialchars($product['profile_img']); ?>" class="rounded-circle border shadow-sm" style="width: 100px; height: 100px; object-fit: cover;" alt="Artisan">
                        <?php else: ?>
                            <i class="bi bi-person-circle text-secondary display-3"></i>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-10">
                        <h5 class="fw-bold text-success mb-1">Crafted by: <?php echo htmlspecialchars($product['artisan_name']); ?></h5>
                        <p class="text-muted small mb-2"><i class="bi bi-geo-alt-fill"></i> Location: <?php echo htmlspecialchars($product['location']); ?></p>
                        <p class="small text-muted mb-0"><?php echo htmlspecialchars($product['bio']); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Simple JavaScript for Gallery Thumbnail Switch -->
<script>
function changeImage(src) {
    document.getElementById('mainProductImg').src = src;
}
</script>

<?php
} else {
    echo '<div class="container my-5 text-center"><h3>Product not found!</h3><a href="shop.php" class="btn btn-success mt-3">Back to Shop</a></div>';
}

// Include footer
include 'includes/footer.php';
?>