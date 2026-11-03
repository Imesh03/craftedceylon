<?php
// Include database connection
require_once 'config/db.php';
session_start();

$currency = isset($_SESSION['currency']) ? $_SESSION['currency'] : 'LKR';
$cart = isset($_SESSION['cart']) ? $_SESSION['cart'] : array();
$subtotal = 0;

include 'includes/header.php';
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

    .form-control {
        background-color: rgba(18, 18, 22, 0.8);
        border: 1px solid rgba(255, 255, 255, 0.1);
        color: #ffffff;
        border-radius: 10px;
    }
    .form-control:focus {
        background-color: rgba(18, 18, 22, 0.95);
        border-color: #198754;
        color: #ffffff;
        box-shadow: 0 0 0 0.25rem rgba(25, 135, 84, 0.25);
    }

    .table {
        color: #ffffff;
        background-color: transparent;
    }
    .table thead {
        background-color: rgba(18, 18, 22, 0.9) !important;
        color: #ffffff;
    }
    .table td, .table th {
        border-color: rgba(255, 255, 255, 0.08);
        background-color: transparent !important;
        color: #ffffff;
    }
</style>

<div class="container my-5">
    <h2 class="fw-bold mb-4 text-success"><i class="bi bi-cart3"></i> Your Shopping Cart</h2>

    <?php if(!empty($cart)): ?>
    <form action="cart-actions.php" method="POST">
        <div class="row g-4">
            <!-- Cart Items Table -->
            <div class="col-lg-8">
                <div class="dark-glass-card p-4">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Unit Price</th>
                                    <th>Quantity</th>
                                    <th>Total</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cart as $key => $item): 
                                    // Use formatPrice if available or fallback to manual formatting
                                    $price = ($currency == 'USD') ? $item['price_usd'] : (isset($item['price_lkr']) ? $item['price_lkr'] : $item['price']);
                                    $line_total = $price * $item['quantity'];
                                    $subtotal += $line_total;
                                ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <img src="<?php echo htmlspecialchars($item['main_image']); ?>" class="rounded me-3 border border-secondary border-opacity-25" style="width: 60px; height: 60px; object-fit: cover;">
                                            <div>
                                                <h6 class="mb-1 fw-bold text-white"><?php echo htmlspecialchars($item['product_name']); ?></h6>
                                                <small class="text-secondary d-block">Style/Color: <?php echo htmlspecialchars($item['selected_color']); ?></small>
                                                <?php if(!empty($item['custom_text'])): ?>
                                                    <small class="text-info d-block"><i class="bi bi-brush"></i> Custom: "<?php echo htmlspecialchars($item['custom_text']); ?>"</small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="fw-bold">
                                        <?php echo ($currency == 'USD') ? '$ ' . number_format($price, 2) : 'Rs. ' . number_format($price, 2); ?>
                                    </td>
                                    <td>
                                        <input type="number" name="quantity[<?php echo $key; ?>]" value="<?php echo $item['quantity']; ?>" min="1" class="form-control form-control-sm text-center" style="width: 70px;">
                                    </td>
                                    <td class="fw-bold text-success">
                                        <?php echo ($currency == 'USD') ? '$ ' . number_format($line_total, 2) : 'Rs. ' . number_format($line_total, 2); ?>
                                    </td>
                                    <td>
                                        <a href="cart-actions.php?action=remove&key=<?php echo $key; ?>" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="d-flex justify-content-between mt-3">
                    <a href="shop.php" class="btn btn-outline-light"><i class="bi bi-arrow-left"></i> Continue Shopping</a>
                    <button type="submit" name="update_cart" class="btn btn-outline-success fw-bold"><i class="bi bi-arrow-clockwise"></i> Update Cart</button>
                </div>
            </div>

            <!-- Order Summary & Proceed to Checkout -->
            <div class="col-lg-4">
                <div class="dark-glass-card p-4">
                    <h5 class="fw-bold mb-3 border-bottom border-secondary border-opacity-25 pb-2 text-white">Order Summary</h5>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-secondary">Subtotal</span>
                        <span class="fw-bold text-white"><?php echo ($currency == 'USD') ? '$ ' . number_format($subtotal, 2) : 'Rs. ' . number_format($subtotal, 2); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-3">
                        <span class="text-secondary">Estimated Shipping</span>
                        <span class="text-success small">Calculated at Checkout</span>
                    </div>
                    <hr class="border-secondary border-opacity-25">
                    <div class="d-flex justify-content-between mb-4 fs-5 fw-bold text-success">
                        <span>Total</span>
                        <span><?php echo ($currency == 'USD') ? '$ ' . number_format($subtotal, 2) : 'Rs. ' . number_format($subtotal, 2); ?></span>
                    </div>

                    <a href="checkout.php" class="btn btn-success btn-lg w-100 fw-bold shadow-sm">
                        Proceed to Checkout <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>
    </form>
    <?php else: ?>
        <div class="dark-glass-card text-center py-5">
            <i class="bi bi-cart-x text-secondary display-1"></i>
            <h4 class="mt-3 text-white">Your shopping cart is empty!</h4>
            <p class="text-secondary">Looks like you haven't added any authentic Ceylon crafts to your cart yet.</p>
            <a href="shop.php" class="btn btn-success btn-lg mt-2 fw-bold px-4"><i class="bi bi-shop"></i> Start Shopping</a>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>