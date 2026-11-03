<?php
// Include database connection
require_once 'config/db.php';
session_start();

// Check currency session & user ID before any HTML output
$currency = isset($_SESSION['currency']) ? $_SESSION['currency'] : 'LKR';
$user_id = isset($_SESSION['user_id'] ) ? $_SESSION['user_id'] : null;

// Get product ID from URL
$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$success_msg = '';
$error_msg = '';

// Handle Add/Remove Wishlist request
if (isset($_GET['toggle_wishlist']) && $user_id && $product_id > 0) {
    $check_w = $conn->prepare("SELECT wishlist_id FROM wishlist WHERE user_id = ? AND product_id = ?");
    $check_w->bind_param("ii", $user_id, $product_id);
    $check_w->execute();
    $res_w = $check_w->get_result();
    
    if ($res_w->num_rows > 0) {
        $del_w = $conn->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?");
        $del_w->bind_param("ii", $user_id, $product_id);
        $del_w->execute();
    } else {
        $add_w = $conn->prepare("INSERT INTO wishlist (user_id, product_id) VALUES (?, ?)");
        $add_w->bind_param("ii", $user_id, $product_id);
        $add_w->execute();
    }
    header("Location: product-detail.php?id=" . $product_id);
    exit();
}

// -------------------------------------------------------------------------
// REVIEW ACTIONS HANDLING (Only for Logged-in Users)
// -------------------------------------------------------------------------
if ($user_id && $_SERVER['REQUEST_METHOD'] == 'POST') {
    // 1. Add Review
    if (isset($_POST['add_review'])) {
        $rating = intval($_POST['rating']);
        $comment = trim($_POST['comment']);
        $review_image = NULL;

        if (isset($_FILES['review_image']) && $_FILES['review_image']['error'] == 0) {
            $img_name = $_FILES['review_image']['name'];
            $img_tmp = $_FILES['review_image']['tmp_name'];
            $upload_dir = 'assets/images/reviews/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $new_img_name = time() . '_' . basename($img_name);
            $target_path = $upload_dir . $new_img_name;
            if (move_uploaded_file($img_tmp, $target_path)) {
                $review_image = $target_path;
            }
        }

        if ($rating >= 1 && $rating <= 5 && !empty($comment)) {
            $rev_stmt = $conn->prepare("INSERT INTO product_reviews (product_id, user_id, rating, comment, review_image, status, is_approved, created_at) VALUES (?, ?, ?, ?, ?, 'Approved', 1, NOW())");
            
            if ($rev_stmt) {
                $rev_stmt->bind_param("iiiss", $product_id, $user_id, $rating, $comment, $review_image);
                if ($rev_stmt->execute()) {
                    $success_msg = "Your review has been published successfully!";
                } else {
                    $error_msg = "Error posting review: " . $conn->error;
                }
            } else {
                $error_msg = "Database Prepare Error: " . $conn->error;
            }
        } else {
            $error_msg = "Please provide a valid rating (1-5) and a comment.";
        }
    }

    // 2. Delete Review
    if (isset($_POST['delete_review'])) {
        $review_id = intval($_POST['review_id']);
        $del_check = $conn->prepare("DELETE FROM product_reviews WHERE review_id = ? AND user_id = ?");
        if ($del_check) {
            $del_check->bind_param("ii", $review_id, $user_id);
            if ($del_check->execute()) {
                $success_msg = "Review deleted successfully.";
            }
        }
    }

    // 3. Edit Review
    if (isset($_POST['edit_review'])) {
        $review_id = intval($_POST['review_id']);
        $rating = intval($_POST['rating']);
        $comment = trim($_POST['comment']);

        $edit_stmt = $conn->prepare("UPDATE product_reviews SET rating = ?, comment = ? WHERE review_id = ? AND user_id = ?");
        if ($edit_stmt) {
            $edit_stmt->bind_param("isii", $rating, $comment, $review_id, $user_id);
            if ($edit_stmt->execute()) {
                $success_msg = "Review updated successfully.";
            }
        }
    }

    // 4. React to Review (Like)
    if (isset($_POST['react_review'])) {
        $review_id = intval($_POST['review_id']);
        $rc_check = $conn->prepare("SELECT reaction_id FROM review_reactions WHERE review_id = ? AND user_id = ?");
        if ($rc_check) {
            $rc_check->bind_param("ii", $review_id, $user_id);
            $rc_check->execute();
            $rc_res = $rc_check->get_result();

            if ($rc_res->num_rows > 0) {
                $rc_del = $conn->prepare("DELETE FROM review_reactions WHERE review_id = ? AND user_id = ?");
                $rc_del->bind_param("ii", $review_id, $user_id);
                $rc_del->execute();
            } else {
                $rc_add = $conn->prepare("INSERT INTO review_reactions (review_id, user_id) VALUES (?, ?)");
                $rc_add->bind_param("ii", $review_id, $user_id);
                $rc_add->execute();
            }
        }
        header("Location: product-detail.php?id=" . $product_id . "#reviews-section");
        exit();
    }
}

// Include header (HTML output starts here)
include 'includes/header.php';

// Check if current product is in user's wishlist
$is_wishlisted = false;
if ($user_id && $product_id > 0) {
    $uw_stmt = $conn->prepare("SELECT wishlist_id FROM wishlist WHERE user_id = ? AND product_id = ?");
    if ($uw_stmt) {
        $uw_stmt->bind_param("ii", $user_id, $product_id);
        $uw_stmt->execute();
        if ($uw_stmt->get_result()->num_rows > 0) {
            $is_wishlisted = true;
        }
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

    // Collect all product images (Main + Gallery) for JS Slider Array
    $all_product_images = [];
    if (!empty($product['main_image'])) {
        $all_product_images[] = $product['main_image'];
    }
    $img_sql = "SELECT image_path FROM product_images WHERE product_id = $product_id";
    $img_result = $conn->query($img_sql);
    if ($img_result && $img_result->num_rows > 0) {
        while ($img = $img_result->fetch_assoc()) {
            $all_product_images[] = $img['image_path'];
        }
    }
?>

<!-- Creative Blurred Background & Dark Styling -->
<style>
    body {
        position: relative;
        background-color: #0d0d12;
        color: #ffffff;
        font-family: 'Plus Jakarta Sans', sans-serif;
    }
    body::before {
        content: "";
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-image: url('assets/images/products/<?php echo basename($product['main_image']); ?>');
        background-size: cover;
        background-position: center;
        filter: blur(40px) brightness(0.2);
        z-index: -1;
    }

    .dark-glass-card {
        background: rgba(26, 26, 40, 0.85);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 18px;
        box-shadow: 0 16px 40px rgba(0, 0, 0, 0.6);
        color: #ffffff;
    }

    .form-control, .form-select {
        background-color: rgba(18, 18, 22, 0.8);
        border: 1px solid rgba(255, 255, 255, 0.1);
        color: #ffffff;
        border-radius: 10px;
    }
    .form-control:focus, .form-select:focus {
        background-color: rgba(18, 18, 22, 0.95);
        border-color: #198754;
        color: #ffffff;
        box-shadow: 0 0 0 0.25rem rgba(25, 135, 84, 0.25);
    }
    
    .reviews-preview-box {
        max-height: 280px;
        overflow-y: auto;
        padding-right: 5px;
    }
    .reviews-preview-box::-webkit-scrollbar {
        width: 6px;
    }
    .reviews-preview-box::-webkit-scrollbar-thumb {
        background: rgba(25, 135, 84, 0.5);
        border-radius: 10px;
    }
    .cursor-pointer {
        cursor: pointer;
        transition: transform 0.2s ease, border-color 0.2s ease;
    }
    .cursor-pointer:hover {
        transform: scale(1.03);
        border-color: #198754 !important;
    }

    /* 7x5 Inches Aspect Ratio / Styled Lightbox Viewer */
    .lightbox-container {
        width: 100%;
        max-width: 700px;
        height: 500px;
        margin: 0 auto;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #000000;
        border-radius: 12px;
        overflow: hidden;
        position: relative;
    }
    .lightbox-container img {
        width: 100%;
        height: 100%;
        object-fit: contain;
    }
    .lightbox-nav-btn {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        background: rgba(0, 0, 0, 0.6);
        color: #ffffff;
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 50%;
        width: 45px;
        height: 45px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        cursor: pointer;
        transition: all 0.3s ease;
        z-index: 10;
    }
    .lightbox-nav-btn:hover {
        background: #198754;
        border-color: #198754;
        color: #ffffff;
    }
    .lightbox-prev {
        left: 15px;
    }
    .lightbox-next {
        right: 15px;
    }

    /* Enhanced Clear Close Button Fix */
    .modal .btn-close-white, 
    .modal .btn-close {
        background-color: rgba(0, 0, 0, 0.8) !important;
        padding: 12px !important;
        border-radius: 50% !important;
        opacity: 1 !important;
        border: 2px solid rgba(255, 255, 255, 0.6);
        transition: all 0.3s ease;
    }
    .modal .btn-close-white:hover, 
    .modal .btn-close:hover {
        background-color: #198754 !important;
        border-color: #198754;
    }
</style>

<div class="container my-5">
    <?php if(!empty($success_msg)): ?>
        <div class="alert alert-success bg-success text-white border-0 shadow-sm mb-4"><?php echo $success_msg; ?></div>
    <?php endif; ?>
    <?php if(!empty($error_msg)): ?>
        <div class="alert alert-danger bg-danger text-white border-0 shadow-sm mb-4"><?php echo $error_msg; ?></div>
    <?php endif; ?>

    <div class="row g-5">
        <!-- Product Images & Gallery -->
        <div class="col-md-6">
            <div class="dark-glass-card p-3 mb-3">
                <img src="<?php echo htmlspecialchars($product['main_image']); ?>" id="mainProductImg" class="card-img-top rounded cursor-pointer" alt="Product Image" style="height: 380px; object-fit: cover;" onclick="openProductImageModal(0)">
                <div class="text-center mt-2">
                    <small class="text-muted" style="font-size: 11px;"><i class="bi bi-zoom-in me-1"></i> Click image to view in 7x5 inches standard preview</small>
                </div>
            </div>
            
            <div class="row g-2">
                <div class="col-3">
                    <img src="<?php echo htmlspecialchars($product['main_image']); ?>" class="img-thumbnail thumbnail-img border-0 cursor-pointer rounded" onclick="changeImage(this.src, 0)" style="height: 80px; object-fit: cover; width: 100%; background: rgba(0,0,0,0.3);">
                </div>
                <?php 
                $img_index = 1;
                if ($img_result && $img_result->num_rows > 0) {
                    $img_result->data_seek(0);
                    while ($img = $img_result->fetch_assoc()) {
                        echo '<div class="col-3"><img src="' . htmlspecialchars($img['image_path']) . '" class="img-thumbnail thumbnail-img border-0 cursor-pointer rounded" onclick="changeImage(\'' . htmlspecialchars($img['image_path']) . '\', ' . $img_index . ')" style="height: 80px; object-fit: cover; width: 100%; background: rgba(0,0,0,0.3);"></div>';
                        $img_index++;
                    }
                }
                ?>
            </div>
        </div>

        <!-- Product Details & Customization Form -->
        <div class="col-md-6">
            <div class="dark-glass-card p-4 h-100 d-flex flex-column justify-content-between">
                <div>
                    <span class="badge bg-success bg-opacity-25 text-success border border-success mb-2"><?php echo htmlspecialchars($product['category_name']); ?></span>
                    <h1 class="fw-bold mb-3 text-white"><?php echo htmlspecialchars($product['product_name']); ?></h1>
                    <h3 class="text-success fw-bold mb-3"><?php echo $price; ?></h3>
                    
                    <p class="text-secondary mb-4"><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>

                    <div class="mb-4">
                        <?php if(!empty($product['is_biodegradable'])): ?>
                            <span class="badge bg-success-subtle text-success border border-success p-2 me-2"><i class="bi bi-recycle"></i> 100% Biodegradable</span>
                        <?php endif; ?>
                        <?php if(!empty($product['is_recyclable'])): ?>
                            <span class="badge bg-primary-subtle text-primary border border-primary p-2 me-2"><i class="bi bi-arrow-repeat"></i> Recyclable Material</span>
                        <?php endif; ?>
                        <span class="badge bg-warning-subtle text-warning border border-warning p-2"><i class="bi bi-patch-check"></i> Authentic Ceylon Craft</span>
                    </div>
                </div>

                <form action="cart-actions.php" method="POST" class="border border-secondary border-opacity-25 p-3 rounded bg-dark bg-opacity-50">
                    <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                    
                    <?php if(!empty($product['is_customizable'])): ?>
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-info"><i class="bi bi-brush"></i> Customization (Name / Message)</label>
                            <input type="text" name="custom_text" class="form-control" placeholder="Enter name or short message to engrave/print">
                        </div>
                    <?php endif; ?>

                    <div class="row g-2 mb-3">
                        <div class="col-7">
                            <label class="form-label fw-bold small text-secondary">Finish Style</label>
                            <select name="selected_color" class="form-select form-select-sm">
                                <option value="Standard / Natural">Standard / Natural Finish</option>
                                <option value="Dark Polish">Dark Polish</option>
                                <option value="Light Polish">Light Polish</option>
                            </select>
                        </div>
                        <div class="col-5">
                            <label class="form-label fw-bold small text-secondary">Qty (Stock: <?php echo $product['stock_quantity']; ?>)</label>
                            <input type="number" name="quantity" value="1" min="1" max="<?php echo $product['stock_quantity']; ?>" class="form-control form-control-sm">
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <?php if($product['stock_quantity'] > 0): ?>
                            <button type="submit" name="add_to_cart" class="btn btn-success w-100 fw-bold shadow-sm">
                                <i class="bi bi-cart-plus me-1"></i> Add to Cart
                            </button>
                        <?php else: ?>
                            <button type="button" class="btn btn-secondary w-100 fw-bold" disabled>Out of Stock</button>
                        <?php endif; ?>

                        <?php $wish_url = $user_id ? "product-detail.php?id={$product_id}&toggle_wishlist={$product_id}" : "login.php"; ?>
                        <a href="<?php echo $wish_url; ?>" class="btn <?php echo $is_wishlisted ? 'btn-danger' : 'btn-outline-danger'; ?> px-3 d-flex align-items-center justify-content-center" title="Wishlist">
                            <i class="bi <?php echo $is_wishlisted ? 'bi-heart-fill' : 'bi-heart'; ?>"></i>
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- REVIEWS & RATINGS SECTION -->
    <div class="row mt-5" id="reviews-section">
        <div class="col-md-12">
            <div class="dark-glass-card p-4">
                <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom border-secondary border-opacity-25">
                    <h4 class="fw-bold text-white mb-0"><i class="bi bi-star-fill text-warning me-2"></i> Customer Reviews & Ratings</h4>
                    <button class="btn btn-outline-success btn-sm fw-bold px-3" data-bs-toggle="modal" data-bs-target="#allReviewsModal">
                        <i class="bi bi-chat-square-text me-1"></i> View All Reviews
                    </button>
                </div>

                <div class="row g-4">
                    <div class="col-md-7">
                        <div class="reviews-preview-box d-flex flex-column gap-3">
                            <?php
                            $rev_query = "SELECT r.*, u.full_name FROM product_reviews r JOIN users u ON r.user_id = u.user_id WHERE r.product_id = $product_id AND r.status = 'Approved' AND r.is_approved = 1 ORDER BY r.created_at DESC";
                            $rev_res = $conn->query($rev_query);

                            if ($rev_res && $rev_res->num_rows > 0) {
                                while ($rev = $rev_res->fetch_assoc()) {
                                    $rev_id_val = $rev['review_id'];
                                    $rc_count_res = $conn->query("SELECT COUNT(*) as cnt FROM review_reactions WHERE review_id = $rev_id_val");
                                    $rc_count = ($rc_count_res) ? $rc_count_res->fetch_assoc()['cnt'] : 0;

                                    $user_reacted = false;
                                    if ($user_id) {
                                        $ur_chk = $conn->query("SELECT reaction_id FROM review_reactions WHERE review_id = $rev_id_val AND user_id = $user_id");
                                        if ($ur_chk && $ur_chk->num_rows > 0) $user_reacted = true;
                                    }
                            ?>
                                <div class="p-3 rounded bg-dark bg-opacity-60 border border-secondary border-opacity-25">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="fw-bold text-white small"><?php echo htmlspecialchars($rev['full_name']); ?></span>
                                        <span class="text-warning small">
                                            <?php for($i=1; $i<=5; $i++): ?>
                                                <i class="bi bi-star<?php echo ($i <= $rev['rating']) ? '-fill' : ''; ?>"></i>
                                            <?php endfor; ?>
                                        </span>
                                    </div>
                                    <p class="text-secondary small mb-2"><?php echo nl2br(htmlspecialchars($rev['comment'])); ?></p>
                                    
                                    <?php if(!empty($rev['review_image'])): ?>
                                        <div class="mb-2">
                                            <img src="<?php echo htmlspecialchars($rev['review_image']); ?>" class="rounded border border-secondary cursor-pointer" style="width: 60px; height: 60px; object-fit: cover;" alt="Review Image" onclick="openSingleImageModal(this.src)" title="Click to enlarge">
                                        </div>
                                    <?php endif; ?>

                                    <div class="d-flex justify-content-between align-items-center pt-2 border-top border-secondary border-opacity-15">
                                        <small class="text-muted" style="font-size: 11px;"><?php echo date('M d, Y', strtotime($rev['created_at'])); ?></small>
                                        
                                        <div class="d-flex align-items-center gap-2">
                                            <?php if($user_id): ?>
                                                <form action="" method="POST">
                                                    <input type="hidden" name="review_id" value="<?php echo $rev['review_id']; ?>">
                                                    <button type="submit" name="react_review" class="btn btn-sm py-0 px-2 <?php echo $user_reacted ? 'btn-danger' : 'btn-outline-secondary'; ?>" style="font-size: 11px;">
                                                        <i class="bi bi-heart-fill me-1"></i> <?php echo $rc_count; ?>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <a href="login.php" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size: 11px;">
                                                    <i class="bi bi-heart me-1"></i> <?php echo $rc_count; ?>
                                                </a>
                                            <?php endif; ?>

                                            <?php if($user_id && $rev['user_id'] == $user_id): ?>
                                                <button class="btn btn-sm btn-outline-info py-0 px-2" style="font-size: 11px;" data-bs-toggle="modal" data-bs-target="#editReviewModal<?php echo $rev['review_id']; ?>">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <form action="" method="POST" onsubmit="return confirm('Delete this review?');" class="d-inline">
                                                    <input type="hidden" name="review_id" value="<?php echo $rev['review_id']; ?>">
                                                    <button type="submit" name="delete_review" class="btn btn-sm btn-outline-danger py-0 px-2" style="font-size: 11px;">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <?php if($user_id && $rev['user_id'] == $user_id): ?>
                                <div class="modal fade text-dark" id="editReviewModal<?php echo $rev['review_id']; ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content bg-dark text-white border border-secondary">
                                            <div class="modal-header border-secondary">
                                                <h5 class="modal-title text-success fw-bold">Edit Your Review</h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <form action="" method="POST">
                                                <div class="modal-body">
                                                    <input type="hidden" name="review_id" value="<?php echo $rev['review_id']; ?>">
                                                    <div class="mb-3">
                                                        <label class="form-label small">Rating (1 to 5 Stars)</label>
                                                        <select name="rating" class="form-select">
                                                            <option value="5" <?php if($rev['rating']==5) echo 'selected'; ?>>5 - Excellent</option>
                                                            <option value="4" <?php if($rev['rating']==4) echo 'selected'; ?>>4 - Very Good</option>
                                                            <option value="3" <?php if($rev['rating']==3) echo 'selected'; ?>>3 - Good</option>
                                                            <option value="2" <?php if($rev['rating']==2) echo 'selected'; ?>>2 - Fair</option>
                                                            <option value="1" <?php if($rev['rating']==1) echo 'selected'; ?>>1 - Poor</option>
                                                        </select>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label small">Comment</label>
                                                        <textarea name="comment" class="form-control" rows="3" required><?php echo htmlspecialchars($rev['comment']); ?></textarea>
                                                    </div>
                                                </div>
                                                <div class="modal-footer border-secondary">
                                                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" name="edit_review" class="btn btn-success btn-sm fw-bold">Save Changes</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>

                            <?php 
                                }
                            } else {
                                echo '<p class="text-secondary small fst-italic">No reviews yet. Be the first to review this handcrafted product!</p>';
                            }
                            ?>
                        </div>
                    </div>

                    <div class="col-md-5 border-start border-secondary border-opacity-25">
                        <h6 class="fw-bold text-success mb-3"><i class="bi bi-pencil-square me-1"></i> Write a Review</h6>
                        <?php if($user_id): ?>
                            <form action="" method="POST" enctype="multipart/form-data">
                                <div class="mb-2">
                                    <label class="form-label small text-secondary">Rating</label>
                                    <select name="rating" class="form-select form-select-sm" required>
                                        <option value="5">5 Stars - Outstanding</option>
                                        <option value="4">4 Stars - Very Good</option>
                                        <option value="3">3 Stars - Average</option>
                                        <option value="2">2 Stars - Poor</option>
                                        <option value="1">1 Star - Terrible</option>
                                    </select>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label small text-secondary">Your Feedback</label>
                                    <textarea name="comment" class="form-control form-control-sm" rows="3" required placeholder="Write about craftsmanship, quality..."></textarea>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small text-secondary">Attach Photo (Optional)</label>
                                    <input type="file" name="review_image" class="form-control form-control-sm" accept="image/*">
                                </div>
                                <button type="submit" name="add_review" class="btn btn-success btn-sm w-100 fw-bold shadow-sm">
                                    <i class="bi bi-send me-1"></i> Submit Review
                                </button>
                            </form>
                        <?php else: ?>
                            <div class="p-3 rounded bg-dark text-center border border-secondary border-opacity-25">
                                <p class="text-secondary small mb-2">You must be logged in as a customer to post a review or react to reviews.</p>
                                <a href="login.php" class="btn btn-success btn-sm fw-bold px-4">Login to Review</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Artisan Profile Section -->
    <?php if(!empty($product['artisan_name'])): ?>
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="dark-glass-card p-4">
                <div class="row align-items-center">
                    <div class="col-md-2 text-center">
                        <?php if(!empty($product['profile_img'])): ?>
                            <img src="<?php echo htmlspecialchars($product['profile_img']); ?>" class="rounded-circle border border-success shadow-sm" style="width: 90px; height: 90px; object-fit: cover;" alt="Artisan">
                        <?php else: ?>
                            <i class="bi bi-person-circle text-secondary display-3"></i>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-10">
                        <h5 class="fw-bold text-success mb-1">Crafted by: <?php echo htmlspecialchars($product['artisan_name']); ?></h5>
                        <p class="text-secondary small mb-2"><i class="bi bi-geo-alt-fill text-danger"></i> Location: <?php echo htmlspecialchars($product['location']); ?></p>
                        <p class="small text-secondary mb-0"><?php echo htmlspecialchars($product['bio']); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Modal to View All Reviews -->
<div class="modal fade" id="allReviewsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content bg-dark text-white border border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title fw-bold text-success"><i class="bi bi-chat-square-text me-2"></i> All Reviews for <?php echo htmlspecialchars($product['product_name']); ?></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="d-flex flex-column gap-3">
                    <?php
                    $modal_rev_query = "SELECT r.*, u.full_name FROM product_reviews r JOIN users u ON r.user_id = u.user_id WHERE r.product_id = $product_id AND r.status = 'Approved' AND r.is_approved = 1 ORDER BY r.created_at DESC";
                    $modal_rev_res = $conn->query($modal_rev_query);

                    if ($modal_rev_res && $modal_rev_res->num_rows > 0) {
                        while ($m_rev = $modal_rev_res->fetch_assoc()) {
                    ?>
                        <div class="p-3 rounded bg-secondary bg-opacity-10 border border-secondary border-opacity-25">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="fw-bold text-white"><?php echo htmlspecialchars($m_rev['full_name']); ?></span>
                                <span class="text-warning">
                                    <?php for($i=1; $i<=5; $i++): ?>
                                        <i class="bi bi-star<?php echo ($i <= $m_rev['rating']) ? '-fill' : ''; ?>"></i>
                                    <?php endfor; ?>
                                </span>
                            </div>
                            <p class="text-light mb-2"><?php echo nl2br(htmlspecialchars($m_rev['comment'])); ?></p>
                            <?php if(!empty($m_rev['review_image'])): ?>
                                <div class="mb-2">
                                    <img src="<?php echo htmlspecialchars($m_rev['review_image']); ?>" class="rounded border border-secondary cursor-pointer" style="width: 80px; height: 80px; object-fit: cover;" alt="Review Image" onclick="openSingleImageModal(this.src)" title="Click to enlarge">
                                </div>
                            <?php endif; ?>
                            <small class="text-muted" style="font-size: 11px;"><?php echo date('M d, Y', strtotime($m_rev['created_at'])); ?></small>
                        </div>
                    <?php 
                        }
                    } else {
                        echo '<p class="text-secondary small fst-italic">No reviews found.</p>';
                    }
                    ?>
                </div>
            </div>
            <div class="modal-header border-secondary">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- PRODUCT GALLERY LIGHTBOX MODAL WITH ARROWS (7x5 Inch Scale & Clear Close Icon) -->
<div class="modal fade" id="productImageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content bg-transparent border-0 text-center">
            <div class="position-relative d-inline-block mx-auto">
                <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3 shadow" data-bs-dismiss="modal" aria-label="Close" style="z-index: 20;"></button>
                
                <div class="lightbox-container shadow-lg border border-secondary">
                    <button class="lightbox-nav-btn lightbox-prev" onclick="prevProductImage()"><i class="bi bi-chevron-left"></i></button>
                    <img id="modalProductGalleryImg" src="" alt="Full View">
                    <button class="lightbox-nav-btn lightbox-next" onclick="nextProductImage()"><i class="bi bi-chevron-right"></i></button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- SINGLE IMAGE LIGHTBOX (FOR REVIEWS - 7x5 Inch Scale & Clear Close Icon) -->
<div class="modal fade" id="singleImageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content bg-transparent border-0 text-center">
            <div class="position-relative d-inline-block mx-auto">
                <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3 shadow" data-bs-dismiss="modal" aria-label="Close" style="z-index: 20;"></button>
                
                <div class="lightbox-container shadow-lg border border-secondary">
                    <img id="modalSingleImg" src="" alt="Review Photo">
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Product Images Array for Slider/Arrows
    const productImagesArray = <?php echo json_encode($all_product_images); ?>;
    let currentImageIndex = 0;

    // Change main product image from thumbnail
    function changeImage(src, index) {
        document.getElementById('mainProductImg').src = src;
        currentImageIndex = index;
    }

    // Open Product Image Modal with Slider capability
    function openProductImageModal(index) {
        currentImageIndex = index;
        updateModalImage();
        var productModal = new bootstrap.Modal(document.getElementById('productImageModal'));
        productModal.show();
    }

    function updateModalImage() {
        if (productImagesArray.length > 0) {
            document.getElementById('modalProductGalleryImg').src = productImagesArray[currentImageIndex];
        }
    }

    function nextProductImage() {
        currentImageIndex = (currentImageIndex + 1) % productImagesArray.length;
        updateModalImage();
    }

    function prevProductImage() {
        currentImageIndex = (currentImageIndex - 1 + productImagesArray.length) % productImagesArray.length;
        updateModalImage();
    }

    // Open Single Review Image Modal
    function openSingleImageModal(src) {
        document.getElementById('modalSingleImg').src = src;
        var singleModal = new bootstrap.Modal(document.getElementById('singleImageModal'));
        singleModal.show();
    }
</script>

<?php
} else {
    echo '<div class="container my-5 text-center"><h3 class="text-white">Product not found.</h3><a href="index.php" class="btn btn-success mt-3">Back to Home</a></div>';
}

// Include footer
include 'includes/footer.php';
?>