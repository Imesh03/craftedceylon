<?php
session_start();
require_once 'config/db.php';

// පරිශීලකයා Log වී නැත්නම්, ඔහුව Login/Register පිටුවට යොමු කිරීම
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_after_login'] = 'checkout.php'; 
    header("Location: login.php?msg=please_login");
    exit();
}

// Check if cart is empty
if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    header("Location: cart.php");
    exit();
}

$currency = isset($_SESSION['currency']) ? $_SESSION['currency'] : 'LKR';
$cart = $_SESSION['cart'];
$subtotal = 0;

foreach ($cart as $item) {
    $price = ($currency == 'USD') ? $item['price_usd'] : $item['price_lkr'];
    $subtotal += $price * $item['quantity'];
}

// ඩේටාබේස් එකෙන් හෝ Default අගයකින් Gift Wrap ගාස්තුව ලබා ගැනීම
$default_gift_fee = ($currency == 'USD') ? 2.50 : 700.00;

$error_msg = '';

// Handle Order Submission
if (isset($_POST['place_order'])) {
    $shipping_name = trim($_POST['shipping_name']);
    $shipping_email = trim($_POST['shipping_email']);
    $shipping_phone = trim($_POST['shipping_phone']);
    $shipping_address = trim($_POST['shipping_address']);
    $city = trim($_POST['city']);
    $postal_code = trim($_POST['postal_code']);
    $shipping_country = trim($_POST['shipping_country']); 
    
    // Gift options
    $is_gift_wrapped = isset($_POST['is_gift_wrapped']) ? 1 : 0;
    $gift_message = isset($_POST['gift_message']) ? trim($_POST['gift_message']) : '';

    // Calculate dynamic shipping and gift cost
    $shipping_cost = ($shipping_country === 'Sri Lanka') ? ($currency === 'USD' ? 2.00 : 500.00) : ($currency === 'USD' ? 15.00 : 4500.00);
    $gift_wrap_fee = $is_gift_wrapped ? $default_gift_fee : 0.00;
    $final_total = $subtotal + $shipping_cost + $gift_wrap_fee;

    if (empty($shipping_name) || empty($shipping_email) || empty($shipping_phone) || empty($shipping_address) || empty($city) || empty($postal_code) || empty($shipping_country)) {
        $error_msg = "Please fill in all required shipping and contact fields including the country.";
    } else {
        // Generate Unique Order Reference
        $order_reference = 'CC-' . strtoupper(uniqid());
        $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : NULL;

        // Insert into Orders Table
        $stmt = $conn->prepare("INSERT INTO orders (user_id, order_reference, total_amount, currency_code, shipping_name, shipping_email, shipping_phone, shipping_address, city, postal_code, shipping_country, is_gift_wrapped, gift_wrap_fee, gift_message, order_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Processing')");
        $stmt->bind_param("isdssssssssids", $user_id, $order_reference, $final_total, $currency, $shipping_name, $shipping_email, $shipping_phone, $shipping_address, $city, $postal_code, $shipping_country, $is_gift_wrapped, $gift_wrap_fee, $gift_message);
        
        if ($stmt->execute()) {
            $order_id = $stmt->insert_id;

            // Insert each cart item into order_items table and update stock
            foreach ($cart as $item) {
                $product_id = $item['product_id'];
                $qty = $item['quantity'];
                $unit_price = ($currency == 'USD') ? $item['price_usd'] : $item['price_lkr'];
                $line_total = $unit_price * $qty;
                $custom_text = $item['custom_text'] ?? '';
                $selected_color = $item['selected_color'] ?? '';

                $item_stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, unit_price, line_total, custom_text, selected_color) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $item_stmt->bind_param("iiiddss", $order_id, $product_id, $qty, $unit_price, $line_total, $custom_text, $selected_color);
                $item_stmt->execute();

                // Deduct stock quantity
                $stock_stmt = $conn->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE product_id = ?");
                $stock_stmt->bind_param("ii", $qty, $product_id);
                $stock_stmt->execute();
            }

            // Clear Cart Session
            unset($_SESSION['cart']);

            // Redirect to Order Success Page with PDF download trigger
            header("Location: order-success.php?ref=" . $order_reference . "&download_pdf=1");
            exit();
        } else {
            $error_msg = "Error placing order. Please try again.";
        }
    }
}

include 'includes/header.php';
?>

<!-- Select2 CSS CDN for Searchable Country Dropdown -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

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

    /* Select2 custom styling for dark mode compatibility */
    .select2-container--bootstrap-5 .select2-selection {
        background-color: rgba(18, 18, 22, 0.8);
        border: 1px solid rgba(255, 255, 255, 0.1);
        color: #ffffff;
        border-radius: 10px;
        min-height: 38px;
    }
    .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
        color: #ffffff;
        line-height: 1.5;
    }
    .select2-dropdown {
        background-color: #1a1a28 !important;
        border: 1px solid rgba(255, 255, 255, 0.1) !important;
        color: #ffffff !important;
    }
    .select2-search__field {
        background-color: rgba(18, 18, 22, 0.9) !important;
        color: #ffffff !important;
        border: 1px solid rgba(255, 255, 255, 0.1) !important;
    }
    .select2-results__option {
        color: #ffffff !important;
    }
    .select2-results__option--highlighted {
        background-color: #198754 !important;
        color: #ffffff !important;
    }
</style>

<div class="container my-5">
    <h2 class="fw-bold mb-4 text-success"><i class="bi bi-bag-check"></i> Secure Checkout</h2>

    <?php if(!empty($error_msg)): ?>
        <div class="alert alert-danger bg-danger text-white border-0 shadow-sm mb-4"><?php echo $error_msg; ?></div>
    <?php endif; ?>

    <form action="checkout.php" method="POST" id="checkoutForm">
        <div class="row g-5">
            <!-- Shipping & Contact Details -->
            <div class="col-lg-7">
                <div class="dark-glass-card p-4">
                    <h5 class="fw-bold mb-3 text-success"><i class="bi bi-truck"></i> Shipping & Delivery Information</h5>
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Full Name</label>
                        <input type="text" name="shipping_name" class="form-control" required value="<?php echo isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : ''; ?>">
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold text-secondary">Email Address</label>
                            <input type="email" name="shipping_email" class="form-control" required value="<?php echo isset($_SESSION['user_email']) ? htmlspecialchars($_SESSION['user_email']) : ''; ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold text-secondary">Phone Number</label>
                            <input type="text" name="shipping_phone" class="form-control" required placeholder="+94 XXXXXXXXX">
                        </div>
                    </div>

                    <!-- Searchable Country Selection -->
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Country / Region</label>
                        <select name="shipping_country" id="shippingCountry" class="form-select" required onchange="updateSummary()">
                            <option value="" disabled selected>Select or type your country...</option>
                            <option value="Sri Lanka">Sri Lanka</option>
                            <option value="United States">United States</option>
                            <option value="United Kingdom">United Kingdom</option>
                            <option value="Australia">Australia</option>
                            <option value="Canada">Canada</option>
                            <option value="Germany">Germany</option>
                            <option value="France">France</option>
                            <option value="India">India</option>
                            <option value="Japan">Japan</option>
                            <option value="Singapore">Singapore</option>
                            <option value="United Arab Emirates">United Arab Emirates</option>
                            <option value="Other International">Other International</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Street Address</label>
                        <textarea name="shipping_address" class="form-control" rows="2" required placeholder="House no, Street, Area"></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold text-secondary">City</label>
                            <input type="text" name="city" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold text-secondary">Postal Code</label>
                            <input type="text" name="postal_code" class="form-control" required placeholder="e.g. 00100">
                        </div>
                    </div>

                    <!-- Gift Wrapping Option -->
                    <hr class="border-secondary border-opacity-25 my-4">
                    <h5 class="fw-bold mb-3 text-success"><i class="bi bi-gift"></i> Gift Options</h5>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="is_gift_wrapped" id="giftWrapCheck" value="1" onchange="toggleGiftBox(this)">
                        <label class="form-check-label fw-bold text-white" for="giftWrapCheck">
                            Add Special Gift Wrapping & Greeting Note (<?php echo ($currency == 'USD') ? '$ 2.50' : 'Rs. 700.00'; ?>)
                        </label>
                    </div>

                    <div class="mb-3" id="giftMsgBox" style="display: none;">
                        <label class="form-label small fw-bold text-secondary">Personalized Greeting Note Message</label>
                        <textarea name="gift_message" class="form-control" rows="2" placeholder="Write your heartfelt message here..."></textarea>
                    </div>
                </div>
            </div>

            <!-- Order Summary Sidebar -->
            <div class="col-lg-5">
                <div class="dark-glass-card p-4">
                    <h5 class="fw-bold mb-3 border-bottom border-secondary border-opacity-25 pb-2 text-white">Your Order Summary</h5>
                    <ul class="list-group list-group-flush mb-3 bg-transparent">
                        <?php foreach($cart as $item): 
                            $item_price = ($currency == 'USD') ? $item['price_usd'] : $item['price_lkr'];
                        ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent text-white border-secondary border-opacity-25">
                            <div>
                                <h6 class="mb-0 fw-bold text-white"><?php echo htmlspecialchars($item['product_name']); ?></h6>
                                <small class="text-secondary">Qty: <?php echo $item['quantity']; ?></small>
                            </div>
                            <span class="fw-bold"><?php echo ($currency == 'USD') ? '$ ' . number_format($item_price * $item['quantity'], 2) : 'Rs. ' . number_format($item_price * $item['quantity'], 2); ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>

                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-secondary">Subtotal</span>
                        <span class="fw-bold text-white" id="subtotalDisplay" data-subtotal="<?php echo $subtotal; ?>"><?php echo ($currency == 'USD') ? '$ ' . number_format($subtotal, 2) : 'Rs. ' . number_format($subtotal, 2); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-secondary">Shipping Cost</span>
                        <span class="fw-bold text-success" id="shippingDisplay">Select Country</span>
                    </div>
                    <div class="d-flex justify-content-between mb-3" id="giftRow" style="display: none;">
                        <span class="text-secondary">Gift Wrapping Fee</span>
                        <span class="fw-bold text-success" id="giftDisplay" data-gift-fee="<?php echo $default_gift_fee; ?>"><?php echo ($currency == 'USD') ? '$ 2.50' : 'Rs. 700.00'; ?></span>
                    </div>
                    <hr class="border-secondary border-opacity-25">
                    <div class="d-flex justify-content-between mb-4 fs-5 fw-bold text-success">
                        <span>Total Amount</span>
                        <span id="totalDisplay"><?php echo ($currency == 'USD') ? '$ ' . number_format($subtotal, 2) : 'Rs. ' . number_format($subtotal, 2); ?></span>
                    </div>

                    <button type="submit" name="place_order" class="btn btn-success btn-lg w-100 fw-bold shadow-sm">
                        <i class="bi bi-check-circle-fill"></i> Place Order Now
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- jQuery and Select2 JS CDN for searchable dropdown -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$(document).ready(function() {
    $('#shippingCountry').select2({
        theme: 'bootstrap-5',
        placeholder: 'Search or select country...'
    });
    $('#shippingCountry').on('change', function() {
        updateSummary();
    });
});

function toggleGiftBox(checkbox) {
    var box = document.getElementById('giftMsgBox');
    var giftRow = document.getElementById('giftRow');
    if (checkbox.checked) {
        box.style.display = 'block';
        giftRow.style.display = 'flex';
    } else {
        box.style.display = 'none';
        giftRow.style.display = 'none';
    }
    updateSummary();
}

function updateSummary() {
    var currency = '<?php echo $currency; ?>';
    var subtotal = parseFloat(document.getElementById('subtotalDisplay').getAttribute('data-subtotal'));
    var country = document.getElementById('shippingCountry').value;
    
    var shippingCost = 0;
    if (country === '') {
        shippingCost = 0;
    } else if (country === 'Sri Lanka') {
        shippingCost = (currency === 'USD') ? 2.00 : 500.00;
    } else {
        shippingCost = (currency === 'USD') ? 15.00 : 4500.00;
    }

    var giftFee = parseFloat(document.getElementById('giftDisplay').getAttribute('data-gift-fee'));
    var giftCost = document.getElementById('giftWrapCheck').checked ? giftFee : 0;
    var total = subtotal + shippingCost + giftCost;

    var symbol = (currency === 'USD') ? '$ ' : 'Rs. ';
    
    if (country === '') {
        document.getElementById('shippingDisplay').innerText = 'Select Country';
    } else {
        document.getElementById('shippingDisplay').innerText = symbol + shippingCost.toFixed(2);
    }
    
    document.getElementById('totalDisplay').innerText = symbol + total.toFixed(2);
}
</script>

<?php include 'includes/footer.php'; ?>