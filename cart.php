<?php
// Include database connection
require_once 'config/db.php';
session_start();

$currency = isset($_SESSION['currency']) ? $_SESSION['currency'] : 'LKR';
$cart = isset($_SESSION['cart']) ? $_SESSION['cart'] : array();
$subtotal = 0;

// Fetch latest stock quantities from database for all items in cart to validate
foreach ($cart as $key => $item) {
    $p_id = $item['product_id'];
    $st_q = $conn->prepare("SELECT stock_quantity FROM products WHERE product_id = ?");
    $st_q->bind_param("i", $p_id);
    $st_q->execute();
    $st_res = $st_q->get_result()->fetch_assoc();
    if ($st_res) {
        $cart[$key]['max_stock'] = intval($st_res['stock_quantity']);
        // If current cart quantity exceeds available stock, cap it creatively and flag it
        if ($cart[$key]['quantity'] > $cart[$key]['max_stock']) {
            $cart[$key]['quantity'] = max(1, $cart[$key]['max_stock']);
            $cart[$key]['stock_exceeded'] = true;
        } else {
            $cart[$key]['stock_exceeded'] = false;
        }
    }
}
$_SESSION['cart'] = $cart;

include 'includes/header.php';
?>

<!-- Creative Blurred Background & Dark Styling -->
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
        border: 1px solid var(--border-color, rgba(255, 255, 255, 0.08));
        border-radius: 16px;
        box-shadow: 0 16px 40px rgba(0, 0, 0, 0.4);
        color: var(--text-main, #ffffff);
        transition: all 0.3s ease;
    }

    .form-control, .form-select {
        background-color: var(--bg-card, rgba(18, 18, 22, 0.85));
        border: 1px solid var(--border-color, rgba(255, 255, 255, 0.12));
        color: var(--text-main, #ffffff);
        border-radius: 10px;
        font-size: 13px;
    }
    .form-control:focus, .form-select:focus {
        background-color: var(--bg-card, rgba(18, 18, 22, 0.95));
        border-color: #198754;
        color: var(--text-main, #ffffff);
        box-shadow: 0 0 0 0.25rem rgba(25, 135, 84, 0.25);
    }

    .btn-action-custom {
        font-size: 12px !important;
        font-weight: 600 !important;
        padding: 7px 8px !important;
        border-radius: 8px !important;
    }

    /* Creative Details Button Styling (Matching index.php) */
    .btn-details-creative {
        background: linear-gradient(135deg, rgba(13, 202, 240, 0.15), rgba(13, 110, 253, 0.15));
        color: #0dcaf0;
        border: 1px solid rgba(13, 202, 240, 0.4);
        transition: all 0.3s ease;
    }
    .btn-details-creative:hover {
        background: linear-gradient(135deg, #0dcaf0, #0d6efd);
        color: #ffffff !important;
        border-color: #0dcaf0;
        box-shadow: 0 4px 15px rgba(13, 202, 240, 0.4);
        transform: translateY(-2px);
    }

    /* Creative Cart Button Styling (Matching index.php) */
    .btn-cart-creative {
        background: linear-gradient(135deg, rgba(25, 135, 84, 0.15), rgba(20, 108, 67, 0.15));
        color: #20c997;
        border: 1px solid rgba(25, 135, 84, 0.4);
        transition: all 0.3s ease;
    }
    .btn-cart-creative:hover {
        background: linear-gradient(135deg, #198754, #146c43);
        color: #ffffff !important;
        border-color: #198754;
        box-shadow: 0 4px 15px rgba(25, 135, 84, 0.4);
        transform: translateY(-2px);
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
    
    .stock-badge-creative {
        background: rgba(255, 193, 7, 0.15);
        color: #ffc107;
        border: 1px dashed rgba(255, 193, 7, 0.4);
        font-size: 11px;
        padding: 3px 8px;
        border-radius: 6px;
    }
</style>

<div class="container my-5">
    <h2 class="fw-bold mb-4 text-success"><i class="bi bi-cart3"></i> Your Shopping Cart</h2>

    <?php 
    // Check if any item exceeded stock to show a creative warning notice
    $has_exceeded = false;
    foreach ($cart as $item) {
        if (isset($item['stock_exceeded']) && $item['stock_exceeded']) {
            $has_exceeded = true;
            break;
        }
    }
    if ($has_exceeded): 
    ?>
        <div class="alert alert-warning bg-warning bg-opacity-10 border border-warning text-warning mb-4 rounded-4 shadow-sm d-flex align-items-center" role="alert">
            <i class="bi bi-exclamation-triangle-fill fs-4 me-3"></i>
            <div>
                <strong>Stock Limit Notice:</strong> Some item quantities in your cart were automatically adjusted to match our maximum available workshop inventory limits. You cannot checkout beyond available stock!
            </div>
        </div>
    <?php endif; ?>

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
                                    $price = ($currency == 'USD') ? $item['price_usd'] : (isset($item['price_lkr']) ? $item['price_lkr'] : $item['price']);
                                    $line_total = $price * $item['quantity'];
                                    $subtotal += $line_total;
                                    $max_stk = isset($item['max_stock']) ? $item['max_stock'] : 10;
                                ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <img src="<?php echo htmlspecialchars($item['main_image']); ?>" class="rounded me-3 border border-secondary border-opacity-25" style="width: 60px; height: 60px; object-fit: cover;">
                                            <div>
                                                <h6 class="mb-1 fw-bold text-white"><?php echo htmlspecialchars($item['product_name']); ?></h6>
                                                <small class="text-secondary d-block">Style/Color: <?php echo htmlspecialchars($item['selected_color']); ?></small>
                                                <!-- Creative Stock Display Badge -->
                                                <span class="stock-badge-creative mt-1 d-inline-block">
                                                    <i class="bi bi-box-seam"></i> Max Available Stock: <strong><?php echo $max_stk; ?></strong>
                                                </span>
                                                <?php if(!empty($item['custom_text'])): ?>
                                                    <small class="text-info d-block mt-1"><i class="bi bi-brush"></i> Custom: "<?php echo htmlspecialchars($item['custom_text']); ?>"</small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="fw-bold">
                                        <?php echo ($currency == 'USD') ? '$ ' . number_format($price, 2) : 'Rs. ' . number_format($price, 2); ?>
                                    </td>
                                    <td>
                                        <!-- Quantity Input with max attribute set dynamically to stock limit -->
                                        <input type="number" name="quantity[<?php echo $key; ?>]" value="<?php echo $item['quantity']; ?>" min="1" max="<?php echo $max_stk; ?>" class="form-control form-control-sm text-center" style="width: 75px;" title="Maximum allowed: <?php echo $max_stk; ?>" onchange="if(this.value > <?php echo $max_stk; ?>){ alert('Only <?php echo $max_stk; ?> items available in stock!'); this.value = <?php echo $max_stk; ?>; }">
                                        <small class="text-muted" style="font-size: 10px;">Limit: <?php echo $max_stk; ?></small>
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