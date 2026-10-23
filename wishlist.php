<?php
session_start();
require_once 'config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_after_login'] = 'wishlist.php';
    header("Location: login.php?msg=please_login");
    exit();
}

$user_id = $_SESSION['user_id'];
$msg = '';

// Handle Remove from Wishlist
if (isset($_GET['remove'])) {
    $wishlist_id = intval($_GET['remove']);
    $del_stmt = $conn->prepare("DELETE FROM wishlist WHERE wishlist_id = ? AND user_id = ?");
    $del_stmt->bind_param("ii", $wishlist_id, $user_id);
    $del_stmt->execute();
    header("Location: wishlist.php?msg=removed");
    exit();
}

// Fetch wishlist items with product details
$query = "SELECT w.wishlist_id, p.product_id, p.product_name, p.price, p.image 
          FROM wishlist w 
          JOIN products p ON w.product_id = p.product_id 
          WHERE w.user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

include 'includes/header.php';
?>

<div class="container my-5">
    <h2 class="fw-bold mb-4 text-success"><i class="bi bi-heart-fill me-2"></i> My Wishlist</h2>

    <?php if (isset($_GET['msg']) && $_GET['msg'] == 'removed'): ?>
        <div class="alert alert-success alert-dismissible fade show small" role="alert">
            Item removed from your wishlist.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($result->num_rows > 0): ?>
        <div class="row row-cols-1 row-cols-md-3 g-4">
            <?php while ($row = $result->fetch_assoc()): ?>
                <div class="col">
                    <div class="card h-100 shadow-sm border-0 rounded-4">
                        <img src="uploads/<?php echo htmlspecialchars($row['image']); ?>" class="card-img-top rounded-top-4" alt="Product Image" style="height: 200px; object-fit: cover;">
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title fw-bold text-dark"><?php echo htmlspecialchars($row['product_name']); ?></h5>
                            <p class="text-success fw-bold fs-5 mb-3">Rs. <?php echo number_format($row['price'], 2); ?></p>
                            
                            <div class="mt-auto d-flex gap-2">
                                <a href="product-details.php?id=<?php echo $row['product_id']; ?>" class="btn btn-outline-success btn-sm w-50 fw-semibold">View Product</a>
                                <a href="wishlist.php?remove=<?php echo $row['wishlist_id']; ?>" class="btn btn-outline-danger btn-sm w-50 fw-semibold"><i class="bi bi-trash"></i> Remove</a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="text-center py-5">
            <div class="mb-3 text-muted" style="font-size: 3rem;"><i class="bi bi-heart"></i></div>
            <h4 class="fw-bold text-muted">Your Wishlist is Empty</h4>
            <p class="text-muted small mb-4">Explore our products and save your favorite items here.</p>
            <a href="shop.php" class="btn btn-success px-4 fw-bold">Explore Shop</a>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>