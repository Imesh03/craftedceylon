<?php
require_once 'config/db.php';
include 'includes/header.php';

$ref = isset($_GET['ref']) ? $_GET['ref'] : '';
?>

<div class="container my-5 text-center">
    <div class="card border shadow-sm p-5 col-md-8 mx-auto bg-body-tertiary">
        <div class="card-body">
            <i class="bi bi-check-circle-fill text-success display-1 mb-3"></i>
            <h1 class="fw-bold text-success">Thank You for Your Order!</h1>
            <p class="text-muted fs-5">Your order has been successfully placed and recorded in our system.</p>
            
            <?php if(!empty($ref)): ?>
                <div class="alert alert-success py-3 my-4">
                    <h5 class="mb-1">Your Unique Order Reference ID:</h5>
                    <span class="font-monospace fs-4 fw-bold"><?php echo htmlspecialchars($ref); ?></span>
                </div>
            <?php endif; ?>

            <p class="text-muted small mb-4">We will process your handcrafted items with care. You can track your fulfillment status from your account dashboard.</p>
            
            <a href="shop.php" class="btn btn-success btn-lg px-4"><i class="bi bi-shop"></i> Continue Shopping</a>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>