<?php
session_start();
require_once 'config/db.php';

// Initialize Cart Session if not set
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = array();
}

// 1. ADD TO CART ACTION
if (isset($_POST['add_to_cart'])) {
    $product_id = intval($_POST['product_id']);
    $quantity = intval($_POST['quantity']);
    $custom_text = isset($_POST['custom_text']) ? trim($_POST['custom_text']) : '';
    $selected_color = isset($_POST['selected_color']) ? $_POST['selected_color'] : 'Standard';

    // Unique key for items (so same item with different customization creates a new line)
    $cart_item_key = $product_id . '_' . md5($custom_text . $selected_color);

    if (isset($_SESSION['cart'][$cart_item_key])) {
        // If item already exists, increase quantity
        $_SESSION['cart'][$cart_item_key]['quantity'] += $quantity;
    } else {
        // Fetch product details
        $stmt = $conn->prepare("SELECT product_name, price_lkr, price_usd, main_image FROM products WHERE product_id = ?");
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $row = $result->fetch_assoc()) {
            $_SESSION['cart'][$cart_item_key] = array(
                'product_id' => $product_id,
                'product_name' => $row['product_name'],
                'price_lkr' => $row['price_lkr'],
                'price_usd' => $row['price_usd'],
                'main_image' => $row['main_image'],
                'quantity' => $quantity,
                'custom_text' => $custom_text,
                'selected_color' => $selected_color
            );
        }
    }

    // Cart එකට එකතු වූ පසු cart.php වෙත නොගොස්, පරිශීලකයා සිටි පිටුවටම (index හෝ shop වෙත) නැවත යැවීම
    $redirect_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'index.php';
    header("Location: " . $redirect_url);
    exit();
}

// 2. REMOVE ITEM FROM CART
if (isset($_GET['action']) && $_GET['action'] == 'remove') {
    $key = $_GET['key'];
    if (isset($_SESSION['cart'][$key])) {
        unset($_SESSION['cart'][$key]);
    }
    header("Location: cart.php");
    exit();
}

// 3. UPDATE QUANTITY
if (isset($_POST['update_cart'])) {
    foreach ($_POST['quantity'] as $key => $qty) {
        $qty = intval($qty);
        if ($qty <= 0) {
            unset($_SESSION['cart'][$key]);
        } else {
            $_SESSION['cart'][$key]['quantity'] = $qty;
        }
    }
    header("Location: cart.php");
    exit();
}
?>