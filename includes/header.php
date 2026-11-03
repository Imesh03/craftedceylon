<?php
// Session එක ආරම්භ කිරීම (Currency, Cart සහ User Login සඳහා අවශ්‍ය වේ)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database Connection inclusion (ගිණුමේ තත්ත්වය පරීක්ෂා කිරීමට අත්‍යවශ්‍යයි)
require_once 'config/db.php'; 

// පාරිභෝගිකයා ලොග් වී ඇත්නම්, ඔහුගේ ගිණුම Block කර ඇද්දැයි ක්ෂණිකව පරීක්ෂා කිරීම
if (isset($_SESSION['user_id']) && isset($conn)) {
    $check_user_id = $_SESSION['user_id'];
    $status_check_stmt = $conn->prepare("SELECT account_status FROM users WHERE user_id = ?");
    if ($status_check_stmt) {
        $status_check_stmt->bind_param("i", $check_user_id);
        $status_check_stmt->execute();
        $status_res = $status_check_stmt->get_result()->fetch_assoc();
        
        // ගිණුම Block කර ඇත්නම් සියලුම sessions ඉවත් කර login පිටුවට යැවීම
        if ($status_res && isset($status_res['account_status']) && $status_res['account_status'] === 'Blocked') {
            session_unset();
            session_destroy();
            header("Location: login.php?error=account_blocked");
            exit();
        }
    }
}

// Global Price Formatting Function with Live Exchange Rate Integration
if (!function_exists('formatPrice')) {
    function formatPrice($price_in_lkr) {
        $currency = $_SESSION['currency'] ?? 'LKR';
        
        if ($currency === 'USD') {
            // පරිශීලකයා USD තෝරා ඇති විට, විනිමය අනුපාතය සෙෂන් එකේ ඇතොත් එය ගැනීම, නැතහොත් API එකෙන් ලබාගැනීම
            if (!isset($_SESSION['exchange_rate'])) {
                $api_url = "https://open.er-api.com/v6/latest/USD";
                $response = @file_get_contents($api_url);
                if ($response !== false) {
                    $data = json_decode($response, true);
                    $_SESSION['exchange_rate'] = isset($data['rates']['LKR']) ? $data['rates']['LKR'] : 300;
                } else {
                    $_SESSION['exchange_rate'] = 300; // Fallback rate
                }
            }
            
            $rate = $_SESSION['exchange_rate'];
            $price_in_usd = $price_in_lkr / $rate;
            return '$ ' . number_format($price_in_usd, 2);
        } else {
            return 'LKR ' . number_format($price_in_lkr, 2);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CraftedCeylon - Handmade Crafts & Eco-Friendly Products</title>
    
    <!-- Bootstrap 5 CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- Instant Theme Loader Script (පිටුව Load වීමටත් පෙර Theme එක Apply කර Flash වීම වැළැක්වීමට) -->
    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-bs-theme', savedTheme);
        })();
    </script>

    <!-- Custom CSS for Dark Mode & Styling -->
    <style>
        :root {
            --bg-color: #ffffff;
            --text-color: #212529;
            --nav-bg: #f8f9fa;
        }

        [data-bs-theme="dark"] {
            --bg-color: #212529;
            --text-color: #f8f9fa;
            --nav-bg: #1a1d20;
        }

        body {
            background-color: var(--bg-color);
            color: var(--text-color);
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        .navbar {
            background-color: var(--nav-bg) !important;
        }

        /* Creative Solution for Long User Names */
        .username-truncate {
            display: inline-block;
            max-width: 130px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            vertical-align: bottom;
        }
        @media (min-width: 992px) {
            .username-truncate {
                max-width: 150px;
            }
        }
    </style>
</head>
<body>

    <!-- Top Navigation Bar -->
    <nav class="navbar navbar-expand-lg shadow-sm sticky-top">
        <div class="container">
            <!-- Brand Logo -->
            <a class="navbar-brand fw-bold text-success" href="index.php">
                <i class="bi bi-flower1"></i> CraftedCeylon
            </a>

            <!-- Mobile Toggle Button -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <!-- Navigation Links -->
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-lg-center">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="shop.php">Shop Catalog</a>
                    </li>
                    
                    <!-- Currency Switcher Form -->
                    <li class="nav-item mx-lg-2 my-2 my-lg-0">
                        <form action="change-currency.php" method="GET" class="d-inline">
                            <select name="currency" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="LKR" <?php if(isset($_SESSION['currency']) && $_SESSION['currency']=='LKR') echo 'selected'; ?>>LKR (Rs)</option>
                                <option value="USD" <?php if(isset($_SESSION['currency']) && $_SESSION['currency']=='USD') echo 'selected'; ?>>USD ($)</option>
                            </select>
                        </form>
                    </li>

                    <!-- Cart Link with Badge Count -->
                    <li class="nav-item me-lg-2">
                        <a class="nav-link position-relative" href="cart.php">
                            <i class="bi bi-cart3 fs-5"></i> Cart
                            <?php if(!empty($_SESSION['cart'])): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-success" style="font-size: 0.65rem;">
                                    <?php echo count($_SESSION['cart']); ?>
                                </span>
                            <?php endif; ?>
                        </a>
                    </li>

                    <!-- Dynamic User Account / Login Buttons -->
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <?php $full_user_name = $_SESSION['user_name'] ?? 'Account'; ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle text-success fw-bold d-flex align-items-center" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-person-circle fs-5 me-1"></i> 
                                <span class="username-truncate" title="<?php echo htmlspecialchars($full_user_name); ?>">
                                    <?php echo htmlspecialchars($full_user_name); ?>
                                </span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end shadow border-0" aria-labelledby="userDropdown">
                                <li><a class="dropdown-item" href="account.php"><i class="bi bi-bag-check me-2"></i> My Orders & Profile</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="btn btn-outline-success btn-sm rounded-pill px-3 ms-lg-2 my-1 my-lg-0 fw-semibold" href="login.php">
                                <i class="bi bi-box-arrow-in-right me-1"></i> Login / Register
                            </a>
                        </li>
                    <?php endif; ?>

                    <!-- Dark / Light Mode Toggle Button -->
                    <li class="nav-item ms-lg-3 my-2 my-lg-0">
                        <button class="btn btn-outline-secondary btn-sm rounded-pill px-3" id="darkModeToggle" onclick="toggleDarkMode()">
                            <i class="bi bi-moon-fill" id="themeIcon"></i> <span id="themeText">Dark</span>
                        </button>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Dark Mode Script -->
    <script>
        function toggleDarkMode() {
            const currentTheme = document.documentElement.getAttribute('data-bs-theme');
            const themeIcon = document.getElementById('themeIcon');
            const themeText = document.getElementById('themeText');

            if (currentTheme === 'light') {
                document.documentElement.setAttribute('data-bs-theme', 'dark');
                if (themeIcon) themeIcon.classList.replace('bi-moon-fill', 'bi-sun-fill');
                if (themeText) themeText.innerText = 'Light';
                localStorage.setItem('theme', 'dark');
            } else {
                document.documentElement.setAttribute('data-bs-theme', 'light');
                if (themeIcon) themeIcon.classList.replace('bi-sun-fill', 'bi-moon-fill');
                if (themeText) themeText.innerText = 'Dark';
                localStorage.setItem('theme', 'light');
            }
        }

        // Synchronize button states on page load
        window.addEventListener('DOMContentLoaded', () => {
            const savedTheme = localStorage.getItem('theme') || 'light';
            const themeIcon = document.getElementById('themeIcon');
            const themeText = document.getElementById('themeText');
            
            document.documentElement.setAttribute('data-bs-theme', savedTheme);

            if (savedTheme === 'dark') {
                if (themeIcon) themeIcon.classList.replace('bi-moon-fill', 'bi-sun-fill');
                if (themeText) themeText.innerText = 'Light';
            } else {
                if (themeIcon) themeIcon.classList.replace('bi-sun-fill', 'bi-moon-fill');
                if (themeText) themeText.innerText = 'Dark';
            }
        });
    </script>
</body>
</html>