<?php
require_once 'config/db.php';
include 'includes/header.php';

$currency = isset($_SESSION['currency']) ? $_SESSION['currency'] : 'LKR';
$cart = isset($_SESSION['cart']) ? $_SESSION['cart'] : array();
$subtotal = 0;
?>

<div class="container my-5">
    <h2 class="fw-bold mb-4 text-success"><i class="bi bi-cart3"></i> Your Shopping Cart</h2>

    <?php if(!empty($cart)): ?>
    <form action="cart-actions.php" method="POST">
        <div class="row g-4">
            <!-- Cart Items Table -->
            <div class="col-lg-8">
                <div class="table-responsive border rounded shadow-sm">
                    <table class="table align-middle mb-0 bg-body">
                        <thead class="bg-body-tertiary">
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
                                $price = ($currency == 'USD') ? $item['price_usd'] : $item['price_lkr'];
                                $line_total = $price * $item['quantity'];
                                $subtotal += $line_total;
                            ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <img src="<?php echo htmlspecialchars($item['main_image']); ?>" class="rounded me-3" style="width: 60px; height: 60px; object-fit: cover;">
                                        <div>
                                            <h6 class="mb-1 fw-bold"><?php echo htmlspecialchars($item['product_name']); ?></h6>
                                            <small class="text-muted d-block">Style/Color: <?php echo htmlspecialchars($item['selected_color']); ?></small>
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

                <div class="d-flex justify-content-between mt-3">
                    <a href="shop.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Continue Shopping</a>
                    <button type="submit" name="update_cart" class="btn btn-outline-success"><i class="bi bi-arrow-clockwise"></i> Update Cart</button>
                </div>
            </div>

            <!-- Order Summary & Proceed to Checkout -->
            <div class="col-lg-4">
                <div class="card shadow-sm border p-4">
                    <h5 class="fw-bold mb-3 border-bottom pb-2">Order Summary</h5>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Subtotal</span>
                        <span class="fw-bold"><?php echo ($currency == 'USD') ? '$ ' . number_format($subtotal, 2) : 'Rs. ' . number_format($subtotal, 2); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-3">
                        <span class="text-muted">Estimated Shipping</span>
                        <span class="text-success small">Calculated at Checkout</span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between mb-4 fs-5 fw-bold text-success">
                        <span>Total</span>
                        <span><?php echo ($currency == 'USD') ? '$ ' . number_format($subtotal, 2) : 'Rs. ' . number_format($subtotal, 2); ?></span>
                    </div>

                    <a href="checkout.php" class="btn btn-success btn-lg w-100 fw-bold">
                        Proceed to Checkout <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>
    </form>
    <?php else: ?>
        <div class="text-center py-5 border rounded bg-body-tertiary">
            <i class="bi bi-cart-x text-muted display-1"></i>
            <h4 class="mt-3">Your shopping cart is empty!</h4>
            <p class="text-muted">Looks like you haven't added any authentic Ceylon crafts to your cart yet.</p>
            <a href="shop.php" class="btn btn-success btn-lg mt-2"><i class="bi bi-shop"></i> Start Shopping</a>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>