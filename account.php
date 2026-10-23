<?php
session_start();
require_once 'config/db.php';

// User Login වී ඇත්දැයි පරීක්ෂා කිරීම
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success_msg = '';
$error_msg = '';

// Wishlist එකෙන් Item එකක් ඉවත් කිරීම (Remove Logic)
if (isset($_GET['remove_wishlist'])) {
    $wishlist_id = intval($_GET['remove_wishlist']);
    $del_stmt = $conn->prepare("DELETE FROM wishlist WHERE wishlist_id = ? AND user_id = ?");
    if ($del_stmt) {
        $del_stmt->bind_param("ii", $wishlist_id, $user_id);
        if ($del_stmt->execute()) {
            $success_msg = "Item removed from your wishlist.";
        } else {
            $error_msg = "Failed to remove item from wishlist.";
        }
    }
}

// Update Profile Information
if (isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    
    if (!empty($full_name) && !empty($email)) {
        $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ? WHERE user_id = ?");
        if ($stmt) {
            $stmt->bind_param("ssi", $full_name, $email, $user_id);
            if ($stmt->execute()) {
                $_SESSION['user_name'] = $full_name;
                $_SESSION['user_email'] = $email;
                $success_msg = "Profile updated successfully!";
            } else {
                $error_msg = "Failed to update profile details.";
            }
        }
    } else {
        $error_msg = "Please fill in all profile fields.";
    }
}

// Fetch User Profile
$user_stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_data = $user_stmt->get_result()->fetch_assoc();

// Fetch Customer Orders
$orders_stmt = $conn->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY order_date DESC");
$orders_stmt->bind_param("i", $user_id);
$orders_stmt->execute();
$orders_result = $orders_stmt->get_result();

// Fetch Wishlist Items with Correct Database Column Names (price_lkr, main_image)
$wishlist_query = "SELECT w.wishlist_id, p.product_id, p.product_name, p.price_lkr, p.main_image 
                  FROM wishlist w 
                  JOIN products p ON w.product_id = p.product_id 
                  WHERE w.user_id = ? 
                  ORDER BY w.created_at DESC";
$wishlist_stmt = $conn->prepare($wishlist_query);
if ($wishlist_stmt) {
    $wishlist_stmt->bind_param("i", $user_id);
    $wishlist_stmt->execute();
    $wishlist_result = $wishlist_stmt->get_result();
} else {
    die("Database Query Error: " . $conn->error);
}

include 'includes/header.php';
?>

<div class="container my-5">
    <div class="row">
        <!-- Sidebar Navigation -->
        <div class="col-md-4 col-lg-3 mb-4">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body text-center p-4">
                    <div class="bg-success text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 70px; height: 70px; font-size: 1.8rem;">
                        <i class="bi bi-person"></i>
                    </div>
                    <h5 class="fw-bold mb-1"><?php echo htmlspecialchars($user_data['full_name']); ?></h5>
                    <p class="text-muted small mb-3"><?php echo htmlspecialchars($user_data['email']); ?></p>
                    <hr class="my-3">
                    <div class="nav flex-column nav-pills text-start" id="v-pills-tab" role="tablist">
                        <button class="nav-link active fw-semibold text-start py-2.5 mb-1" id="v-pills-orders-tab" data-bs-toggle="pill" data-bs-target="#v-pills-orders" type="button" role="tab">
                            <i class="bi bi-bag-check me-2"></i> My Orders
                        </button>
                        
                        <!-- Wishlist Option Added Below My Orders -->
                        <button class="nav-link fw-semibold text-start py-2.5 mb-1" id="v-pills-wishlist-tab" data-bs-toggle="pill" data-bs-target="#v-pills-wishlist" type="button" role="tab">
                            <i class="bi bi-heart me-2"></i> My Wishlist
                        </button>

                        <button class="nav-link fw-semibold text-start py-2.5 mb-1" id="v-pills-profile-tab" data-bs-toggle="pill" data-bs-target="#v-pills-profile" type="button" role="tab">
                            <i class="bi bi-person-gear me-2"></i> Account Settings
                        </button>
                        
                        <a href="logout.php" class="nav-link fw-semibold text-danger text-start py-2.5 mt-2">
                            <i class="bi bi-box-arrow-right me-2"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Area -->
        <div class="col-md-8 col-lg-9">
            <?php if(!empty($success_msg)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle me-1"></i> <?php echo $success_msg; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if(!empty($error_msg)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle me-1"></i> <?php echo $error_msg; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="tab-content" id="v-pills-tabContent">
                <!-- Orders Tab -->
                <div class="tab-pane fade show active" id="v-pills-orders" role="tabpanel">
                    <div class="card border-0 shadow-sm rounded-3">
                        <div class="card-body p-4">
                            <h4 class="fw-bold text-success mb-4"><i class="bi bi-box-seam me-2"></i>Order History & Tracking</h4>

                            <?php if ($orders_result && $orders_result->num_rows > 0): ?>
                                <div class="table-responsive">
                                    <table class="table align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Order Ref</th>
                                                <th>Date</th>
                                                <th>Total</th>
                                                <th>Status</th>
                                                <th>Tracking Info</th>
                                                <th>Ordered Items</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while($order = $orders_result->fetch_assoc()): 
                                                $order_id = $order['order_id'];
                                                // Fetch items belonging to this order
                                                $items_query = "SELECT oi.*, p.product_name, p.main_image FROM order_items oi JOIN products p ON oi.product_id = p.product_id WHERE oi.order_id = ?";
                                                $items_stmt = $conn->prepare($items_query);
                                                $items_result = null;
                                                if ($items_stmt) {
                                                    $items_stmt->bind_param("i", $order_id);
                                                    $items_stmt->execute();
                                                    $items_result = $items_stmt->get_result();
                                                }
                                            ?>
                                                <tr>
                                                    <td class="fw-bold text-success">
                                                        <?php echo htmlspecialchars($order['order_reference']); ?>
                                                    </td>
                                                    <td class="small text-muted">
                                                        <?php echo date('Y-m-d', strtotime($order['order_date'])); ?>
                                                    </td>
                                                    <td class="fw-semibold">
                                                        <?php echo htmlspecialchars($order['currency_code']) . ' ' . number_format($order['total_amount'], 2); ?>
                                                    </td>
                                                    <td>
                                                        <?php 
                                                            $st = $order['order_status'];
                                                            $badge = 'warning';
                                                            if($st == 'Delivered') $badge = 'success';
                                                            elseif($st == 'Shipped') $badge = 'info';
                                                            elseif($st == 'Cancelled') $badge = 'danger';
                                                        ?>
                                                        <span class="badge bg-<?php echo $badge; ?>"><?php echo htmlspecialchars($st); ?></span>
                                                    </td>
                                                    <td>
                                                        <?php if(!empty($order['tracking_number'])): ?>
                                                            <small class="fw-bold text-dark"><i class="bi bi-truck me-1"></i><?php echo htmlspecialchars($order['tracking_number']); ?></small>
                                                        <?php else: ?>
                                                            <span class="text-muted small">Pending dispatch</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <!-- Ordered Items Images & Names Column -->
                                                    <td>
                                                        <div class="d-flex flex-wrap gap-2 align-items-center" style="max-width: 180px;">
                                                            <?php if ($items_result && $items_result->num_rows > 0): ?>
                                                                <?php while($item = $items_result->fetch_assoc()): ?>
                                                                    <div class="position-relative" title="<?php echo htmlspecialchars($item['product_name']) . ' (Qty: ' . $item['quantity'] . ')'; ?>">
                                                                        <img src="<?php echo htmlspecialchars($item['main_image']); ?>" class="rounded border shadow-sm" style="width: 45px; height: 45px; object-fit: cover;" alt="Product">
                                                                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-success" style="font-size: 9px;">
                                                                            <?php echo $item['quantity']; ?>
                                                                        </span>
                                                                    </div>
                                                                <?php endwhile; ?>
                                                            <?php else: ?>
                                                                <span class="text-muted small">No items found</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5 text-muted">
                                    <i class="bi bi-cart-x display-4 d-block mb-3"></i>
                                    <h5>No orders found yet</h5>
                                    <p class="small mb-3">Explore our authentic Ceylon handcrafted items today!</p>
                                    <a href="shop.php" class="btn btn-success fw-semibold"><i class="bi bi-shop me-1"></i> Go to Store</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Wishlist Tab -->
                <div class="tab-pane fade" id="v-pills-wishlist" role="tabpanel">
                    <div class="card border-0 shadow-sm rounded-3">
                        <div class="card-body p-4">
                            <h4 class="fw-bold text-success mb-4"><i class="bi bi-heart-fill me-2"></i>My Saved Wishlist</h4>

                            <?php if ($wishlist_result && $wishlist_result->num_rows > 0): ?>
                                <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3">
                                    <?php while($wish = $wishlist_result->fetch_assoc()): ?>
                                        <div class="col">
                                            <div class="card h-100 border-0 shadow-sm rounded-3">
                                                <img src="<?php echo htmlspecialchars($wish['main_image']); ?>" class="card-img-top rounded-top-3" alt="Product Image" style="height: 160px; object-fit: cover;">
                                                <div class="card-body d-flex flex-column p-3">
                                                    <h6 class="fw-bold mb-1 text-truncate"><?php echo htmlspecialchars($wish['product_name']); ?></h6>
                                                    <p class="text-success fw-bold small mb-3">
                                                        <?php echo function_exists('formatPrice') ? formatPrice($wish['price_lkr']) : 'Rs. ' . number_format($wish['price_lkr'], 2); ?>
                                                    </p>
                                                    <div class="mt-auto d-flex gap-2">
                                                        <a href="product-detail.php?id=<?php echo $wish['product_id']; ?>" class="btn btn-outline-success btn-sm w-100 fw-semibold">View</a>
                                                        <a href="account.php?remove_wishlist=<?php echo $wish['wishlist_id']; ?>" class="btn btn-outline-danger btn-sm px-2" title="Remove"><i class="bi bi-trash"></i></a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5 text-muted">
                                    <i class="bi bi-heart display-4 d-block mb-3 text-muted"></i>
                                    <h5>Your Wishlist is Empty</h5>
                                    <p class="small mb-3">Save items you love to view or purchase them later!</p>
                                    <a href="shop.php" class="btn btn-success fw-semibold"><i class="bi bi-shop me-1"></i> Explore Shop</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Profile Settings Tab -->
                <div class="tab-pane fade" id="v-pills-profile" role="tabpanel">
                    <div class="card border-0 shadow-sm rounded-3">
                        <div class="card-body p-4">
                            <h4 class="fw-bold text-success mb-4"><i class="bi bi-person-gear me-2"></i>Account Details</h4>
                            
                            <form action="account.php" method="POST">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold small">Full Name</label>
                                    <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($user_data['full_name']); ?>" required>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label fw-semibold small">Email Address</label>
                                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user_data['email']); ?>" required>
                                </div>

                                <button type="submit" name="update_profile" class="btn btn-success fw-bold px-4">
                                    <i class="bi bi-save me-1"></i> Update Profile
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>