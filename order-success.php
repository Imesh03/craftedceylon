<?php
require_once 'config/db.php';
include 'includes/header.php';

$ref = isset($_GET['ref']) ? $_GET['ref'] : '';
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
        background-image: url('assets/images/cover.jpg');
        background-size: cover;
        background-position: center;
        filter: blur(20px) brightness(0.35);
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
</style>

<div class="container my-5 text-center">
    <div class="dark-glass-card p-5 col-md-8 mx-auto">
        <div class="card-body">
            <i class="bi bi-check-circle-fill text-success display-1 mb-3"></i>
            <h1 class="fw-bold text-success mb-3">Thank You for Your Order!</h1>
            <p class="text-secondary fs-5 mb-4">Your order has been successfully placed and recorded in our system.</p>
            
            <?php if(!empty($ref)): ?>
                <div class="alert alert-success bg-success bg-opacity-25 text-success border border-success py-3 my-4 rounded-3">
                    <h5 class="mb-1 fw-bold">Your Unique Order Reference ID:</h5>
                    <span class="font-monospace fs-4 fw-bold text-white"><?php echo htmlspecialchars($ref); ?></span>
                </div>
            <?php endif; ?>

            <p class="text-secondary small mb-4">We will process your handcrafted items with care. You can track your fulfillment status from your account dashboard.</p>
            
            <a href="shop.php" class="btn btn-success btn-lg px-4 fw-bold shadow-sm"><i class="bi bi-shop"></i> Continue Shopping</a>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>