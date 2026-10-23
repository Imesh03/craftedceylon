<?php
session_start();

if (isset($_GET['currency'])) {
    $selected_currency = strtoupper($_GET['currency']);
    
    if (in_array($selected_currency, ['LKR', 'USD'])) {
        $_SESSION['currency'] = $selected_currency;

        // USD තෝරාගත්තේ නම්, වර්තමාන විනිময় අනුපාතය (Live Exchange Rate) ලබා ගැනීම
        if ($selected_currency === 'USD') {
            // නොමිලේ ලබාදෙන API එකක් හරහා USD වලට අදාළ අනුපාතය ලබා ගැනීම (LKR සිට USD වෙත)
            $api_url = "https://open.er-api.com/v6/latest/USD";
            
            $response = @file_get_contents($api_url);
            if ($response !== false) {
                $data = json_decode($response, true);
                // USD සිට LKR අනුපාතය ලබාගෙන, LKR සිට USD වලට හැරවීමට අදාළ අගය ගණනය කර ගැනීම
                if (isset($data['rates']['LKR'])) {
                    $_SESSION['exchange_rate'] = $data['rates']['LKR']; // 1 USD = කොපමණ LKR ද යන්න
                } else {
                    $_SESSION['exchange_rate'] = 300; // API එක වැඩ කරේ නැත්නම් සාමාන්‍ය අගයක් (Fallback rate)
                }
            } else {
                $_SESSION['exchange_rate'] = 300;
            }
        }
    }
}

// පරිශීලකයා සිටි පිටුවටම නැවත යොමු කිරීම
$redirect_to = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'index.php';
header("Location: " . $redirect_to);
exit();
?>