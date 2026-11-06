<?php
session_start();
require_once '../config/db.php';
date_default_timezone_set('Asia/Colombo');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../includes/PHPMailer/Exception.php';
require '../includes/PHPMailer/PHPMailer.php';
require '../includes/PHPMailer/SMTP.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged'])) {
    header("Location: login.php");
    exit();
}

$current_admin_id = $_SESSION['admin_id'];

// Check if Super Admin
$role_stmt = $conn->prepare("SELECT role, username FROM admins WHERE admin_id = ?");
$role_stmt->bind_param("i", $current_admin_id);
$role_stmt->execute();
$current_admin = $role_stmt->get_result()->fetch_assoc();
$is_super_admin = ($current_admin && $current_admin['role'] === 'Super Admin');

// Handle Date & Status Filters
$from_date = isset($_GET['from_date']) ? trim($_GET['from_date']) : '';
$to_date = isset($_GET['to_date']) ? trim($_GET['to_date']) : '';
$status_filter = isset($_GET['status_filter']) ? trim($_GET['status_filter']) : '';
$admin_search = isset($_GET['admin_search']) ? trim($_GET['admin_search']) : '';

// Main Report Query
$sql = "SELECT o.*, u.email AS account_email 
        FROM orders o 
        LEFT JOIN users u ON o.user_id = u.user_id 
        WHERE 1=1";

if (!empty($from_date)) {
    $sql .= " AND o.order_date >= '" . $conn->real_escape_string($from_date . " 00:00:00") . "'";
}
if (!empty($to_date)) {
    $sql .= " AND o.order_date <= '" . $conn->real_escape_string($to_date . " 23:59:59") . "'";
}
if (!empty($status_filter)) {
    $sql .= " AND o.order_status = '" . $conn->real_escape_string($status_filter) . "'";
}
$sql .= " ORDER BY o.order_date DESC";
$orders_result = $conn->query($sql);

if (!$orders_result) {
    $sql = "SELECT * FROM orders WHERE 1=1";
    if (!empty($from_date)) $sql .= " AND order_date >= '" . $conn->real_escape_string($from_date . " 00:00:00") . "'";
    if (!empty($to_date)) $sql .= " AND order_date <= '" . $conn->real_escape_string($to_date . " 23:59:59") . "'";
    if (!empty($status_filter)) $sql .= " AND order_status = '" . $conn->real_escape_string($status_filter) . "'";
    $sql .= " ORDER BY order_date DESC";
    $orders_result = $conn->query($sql);
}

// Calculate Summary Metrics & Status Counts
$total_orders_count = 0;
$total_revenue_lkr = 0.00;
$status_counts = [
    'Processing' => 0,
    'Shipped' => 0,
    'Delivered' => 0,
    'Cancelled' => 0
];
$orders_array = [];

if ($orders_result && $orders_result->num_rows > 0) {
    while ($row = $orders_result->fetch_assoc()) {
        $resolved_email = 'N/A';
        if (!empty($row['shipping_email'])) {
            $resolved_email = $row['shipping_email'];
        } elseif (!empty($row['email'])) {
            $resolved_email = $row['email'];
        } elseif (!empty($row['account_email'])) {
            $resolved_email = $row['account_email'];
        } elseif (!empty($row['customer_email'])) {
            $resolved_email = $row['customer_email'];
        }
        $row['resolved_email'] = $resolved_email;

        $orders_array[] = $row;
        $total_orders_count++;
        
        $curr = strtoupper(trim($row['currency_code'] ?? 'LKR'));
        if ($curr === 'LKR' || $curr === '') {
            $total_revenue_lkr += floatval($row['total_amount']);
        }
        
        $st = trim($row['order_status']);
        if (isset($status_counts[$st])) {
            $status_counts[$st]++;
        } else {
            $status_counts[$st] = 1;
        }
    }
}

$success_msg = '';
$error_msg = '';

// Helper: Generate Excel (CSV) Content
function generateExcelCSV($orders_array) {
    $output = fopen('php://temp', 'r+');
    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, array('Order Ref', 'Date', 'Time', 'Customer Email', 'Order State', 'Tracking No', 'Total Price'));
    foreach ($orders_array as $row) {
        $dt = strtotime($row['order_date']);
        $price_str = ($row['currency_code'] ?? 'LKR') . ' ' . number_format((float)$row['total_amount'], 2, '.', '');
        fputcsv($output, array(
            $row['order_reference'],
            date('Y-m-d', $dt),
            date('H:i:s', $dt),
            $row['resolved_email'],
            $row['order_status'],
            !empty($row['tracking_number']) ? $row['tracking_number'] : '-',
            $price_str
        ));
    }
    rewind($output);
    $csv_data = stream_get_contents($output);
    fclose($output);
    return $csv_data;
}

// Helper: Pure PHP Real PDF Generator
function generatePurePDFReport($orders_array, $total_orders, $total_income, $status_counts, $period) {
    $escapePDF = function($str) {
        $str = preg_replace('/[^\x20-\x7E]/', '', (string)$str);
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $str);
    };

    $pages_content = [];
    $page_stream = "";

    // Header matching navbar style (Logo box + Brand Name)
    $page_stream .= "0.098 0.529 0.329 rg 40 745 515 55 re f\n"; 
    $page_stream .= "1 1 1 rg 65 772 14 14 re f\n";
    $page_stream .= "0.098 0.529 0.329 rg 68 775 8 8 re f\n";
    $page_stream .= "1 1 1 rg BT /F2 18 Tf 92 775 Td (CraftedCeylon) Tj ET\n";
    $page_stream .= "0.9 0.96 0.92 rg BT /F1 9 Tf 92 760 Td (ORDERS & REVENUE REPORT) Tj ET\n";
    $page_stream .= "1 1 1 rg BT /F2 9 Tf 380 777 Td (Period: " . $escapePDF($period) . ") Tj ET\n";
    $page_stream .= "1 1 1 rg BT /F1 8 Tf 380 763 Td (Generated: " . date('Y-m-d H:i') . ") Tj ET\n";

    // Compact Summary Grid
    $y = 695;
    $page_stream .= "0.95 0.96 0.97 rg 40 $y 250 38 re f 0.8 0.82 0.85 RG 40 $y 250 38 re S\n";
    $page_stream .= "0.35 0.35 0.35 rg BT /F1 8 Tf 50 " . ($y + 24) . " Td (TOTAL ORDERS) Tj ET\n";
    $page_stream .= "0.1 0.1 0.1 rg BT /F2 13 Tf 50 " . ($y + 8) . " Td (" . intval($total_orders) . ") Tj ET\n";

    $page_stream .= "0.95 0.96 0.97 rg 305 $y 250 38 re f 0.8 0.82 0.85 RG 305 $y 250 38 re S\n";
    $page_stream .= "0.35 0.35 0.35 rg BT /F1 8 Tf 315 " . ($y + 24) . " Td (TOTAL INCOME (LKR)) Tj ET\n";
    $page_stream .= "0.098 0.529 0.329 rg BT /F2 13 Tf 315 " . ($y + 8) . " Td (Rs. " . number_format($total_income, 2) . ") Tj ET\n";

    $y -= 46;
    $page_stream .= "0.95 0.96 0.97 rg 40 $y 250 38 re f 0.8 0.82 0.85 RG 40 $y 250 38 re S\n";
    $page_stream .= "0.35 0.35 0.35 rg BT /F1 8 Tf 50 " . ($y + 24) . " Td (DELIVERED / CANCELLED) Tj ET\n";
    $page_stream .= "0.1 0.1 0.1 rg BT /F2 11 Tf 50 " . ($y + 8) . " Td (Delivered: " . $status_counts['Delivered'] . "  |  Cancelled: " . $status_counts['Cancelled'] . ") Tj ET\n";

    $page_stream .= "0.95 0.96 0.97 rg 305 $y 250 38 re f 0.8 0.82 0.85 RG 305 $y 250 38 re S\n";
    $page_stream .= "0.35 0.35 0.35 rg BT /F1 8 Tf 315 " . ($y + 24) . " Td (PROCESSING / SHIPPED) Tj ET\n";
    $page_stream .= "0.1 0.1 0.1 rg BT /F2 11 Tf 315 " . ($y + 8) . " Td (Processing: " . $status_counts['Processing'] . "  |  Shipped: " . $status_counts['Shipped'] . ") Tj ET\n";

    // Table Header
    $y -= 32;
    $drawTableHeader = function($currY) {
        $s = "0.098 0.529 0.329 rg 40 $currY 515 22 re f\n";
        $s .= "1 1 1 rg BT /F2 8.5 Tf 48 " . ($currY + 7) . " Td (Ref ID) Tj ET\n";
        $s .= "1 1 1 rg BT /F2 8.5 Tf 125 " . ($currY + 7) . " Td (Date & Time) Tj ET\n";
        $s .= "1 1 1 rg BT /F2 8.5 Tf 225 " . ($currY + 7) . " Td (Customer Email) Tj ET\n";
        $s .= "1 1 1 rg BT /F2 8.5 Tf 390 " . ($currY + 7) . " Td (Order State) Tj ET\n";
        $s .= "1 1 1 rg BT /F2 8.5 Tf 475 " . ($currY + 7) . " Td (Total Price) Tj ET\n";
        return $s;
    };

    $page_stream .= $drawTableHeader($y);
    $y -= 20;

    foreach ($orders_array as $idx => $order) {
        if ($y < 60) {
            $pages_content[] = $page_stream;
            $page_stream = "";
            $y = 770;
            $page_stream .= $drawTableHeader($y);
            $y -= 20;
        }
        if ($idx % 2 == 1) {
            $page_stream .= "0.97 0.97 0.98 rg 40 " . ($y - 4) . " 515 18 re f\n";
        }
        $page_stream .= "0.88 0.88 0.88 RG 40 " . ($y - 4) . " 515 0 re S\n";

        $ref = $escapePDF($order['order_reference']);
        $dt = $escapePDF(date('Y-m-d H:i', strtotime($order['order_date'])));
        $email = $escapePDF(substr($order['resolved_email'], 0, 32));
        $state = $escapePDF($order['order_status']);
        $price = $escapePDF(($order['currency_code'] ?? 'LKR') . ' ' . number_format((float)$order['total_amount'], 2));

        $page_stream .= "0.1 0.1 0.1 rg BT /F2 8 Tf 48 $y Td ($ref) Tj ET\n";
        $page_stream .= "0.3 0.3 0.3 rg BT /F1 8 Tf 125 $y Td ($dt) Tj ET\n";
        $page_stream .= "0.1 0.1 0.1 rg BT /F1 8 Tf 225 $y Td ($email) Tj ET\n";
        $page_stream .= "0.1 0.1 0.1 rg BT /F2 8 Tf 390 $y Td ($state) Tj ET\n";
        $page_stream .= "0.098 0.529 0.329 rg BT /F2 8 Tf 475 $y Td ($price) Tj ET\n";

        $y -= 19;
    }

    if (empty($orders_array)) {
        $page_stream .= "0.4 0.4 0.4 rg BT /F1 9 Tf 200 $y Td (No orders found for the selected period.) Tj ET\n";
    }

    $pages_content[] = $page_stream;

    $objects = [];
    $objects[] = "1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n";
    
    $page_ids = [];
    $font1_id = 3;
    $font2_id = 4;
    $next_id = 5;

    $objects[] = "$font1_id 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj\n";
    $objects[] = "$font2_id 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >> endobj\n";

    $page_objects = [];
    foreach ($pages_content as $stream) {
        $page_id = $next_id++;
        $content_id = $next_id++;
        $page_ids[] = "$page_id 0 R";
        $page_objects[] = "$page_id 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 $font1_id 0 R /F2 $font2_id 0 R >> >> /Contents $content_id 0 R >> endobj\n";
        $page_objects[] = "$content_id 0 obj << /Length " . strlen($stream) . " >> stream\n" . $stream . "endstream\nendobj\n";
    }

    $pages_obj = "2 0 obj << /Type /Pages /Kids [" . implode(' ', $page_ids) . "] /Count " . count($page_ids) . " >> endobj\n";
    
    $all_objs = [
        1 => $objects[0],
        2 => $pages_obj,
        3 => $objects[1],
        4 => $objects[2]
    ];
    foreach ($page_objects as $po) {
        preg_match('/^(\d+) 0 obj/', $po, $m);
        $all_objs[intval($m[1])] = $po;
    }
    ksort($all_objs);

    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    foreach ($all_objs as $id => $obj_str) {
        $offsets[$id] = strlen($pdf);
        $pdf .= $obj_str;
    }

    $xref_pos = strlen($pdf);
    $pdf .= "xref\n0 " . (count($all_objs) + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";
    for ($i = 1; $i <= count($all_objs); $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
    }
    $pdf .= "trailer << /Size " . (count($all_objs) + 1) . " /Root 1 0 R >>\nstartxref\n$xref_pos\n%%EOF";

    return $pdf;
}

// Handle Direct Excel (CSV) Download
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=CraftedCeylon_Orders_Report_' . date('Y-m-d') . '.csv');
    echo generateExcelCSV($orders_array);
    exit();
}

// Handle Direct PDF Download
if (isset($_GET['export']) && $_GET['export'] == 'pdf') {
    $period_label = (!empty($from_date) && !empty($to_date)) ? "$from_date to $to_date" : "All Time";
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename=CraftedCeylon_Orders_Report_' . date('Y-m-d') . '.pdf');
    echo generatePurePDFReport($orders_array, $total_orders_count, $total_revenue_lkr, $status_counts, $period_label);
    exit();
}

// Handle Email Sending to Selected Admins
if (isset($_POST['send_report_email'])) {
    $selected_admins = isset($_POST['selected_admins']) ? $_POST['selected_admins'] : [];
    $attachment_format = isset($_POST['attachment_format']) ? $_POST['attachment_format'] : 'pdf';
    
    if (!empty($selected_admins)) {
        $report_period = (!empty($from_date) && !empty($to_date)) ? "$from_date to $to_date" : "All Time Records";
        
        $placeholders = implode(',', array_fill(0, count($selected_admins), '?'));
        $types = str_repeat('i', count($selected_admins));
        $admin_query = $conn->prepare("SELECT email, username FROM admins WHERE admin_id IN ($placeholders)");
        $admin_query->bind_param($types, ...$selected_admins);
        $admin_query->execute();
        $admin_res = $admin_query->get_result();
        
        $date_stamp = date('Y-m-d');
        if ($attachment_format === 'excel') {
            $attachment_data = generateExcelCSV($orders_array);
            $attachment_filename = "CraftedCeylon_Orders_Report_{$date_stamp}.csv";
            $attachment_mime = "text/csv";
            $format_label = "Excel Spreadsheet (.csv)";
        } else {
            $attachment_data = generatePurePDFReport($orders_array, $total_orders_count, $total_revenue_lkr, $status_counts, $report_period);
            $attachment_filename = "CraftedCeylon_Orders_Report_{$date_stamp}.pdf";
            $attachment_mime = "application/pdf";
            $format_label = "PDF Document (.pdf)";
        }

        $sent_count = 0;
        while ($adm = $admin_res->fetch_assoc()) {
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'lasadaimesh@gmail.com'; 
                $mail->Password   = 'dvmx ibup fbgc iaes';     
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;

                $mail->setFrom('lasadaimesh@gmail.com', 'CraftedCeylon Management');
                $mail->addAddress($adm['email'], $adm['username']);

                $mail->addStringAttachment($attachment_data, $attachment_filename, 'base64', $attachment_mime);

                $mail->isHTML(true);
                $mail->Subject = 'CraftedCeylon - Orders Report (' . $report_period . ')';
                $mail->Body = "
                    <div style='font-family: Arial, sans-serif; padding: 20px; background-color: #f4f4f4;'>
                        <div style='max-width: 520px; margin: 0 auto; background: #ffffff; padding: 24px; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); border-top: 5px solid #198754;'>
                            <h2 style='color: #198754; text-align: center; margin-bottom: 4px;'>CraftedCeylon</h2>
                            <p style='text-align: center; color: #777; font-size: 12px; margin-top: 0;'>Official Orders & Revenue Report</p>
                            <hr style='border: none; border-top: 1px solid #eee;'>
                            <p>Hello <b>" . htmlspecialchars($adm['username']) . "</b>,</p>
                            <p>Please find attached the requested Orders Report in <b>{$format_label}</b> format.</p>
                            <table style='width: 100%; background: #f8f9fa; padding: 12px; border-radius: 6px; font-size: 13px; margin: 15px 0; border: 1px solid #e9ecef;'>
                                <tr><td style='padding: 4px;'><b>Report Period:</b></td><td style='padding: 4px;'>{$report_period}</td></tr>
                                <tr><td style='padding: 4px;'><b>Total Orders:</b></td><td style='padding: 4px;'>{$total_orders_count}</td></tr>
                                <tr><td style='padding: 4px;'><b>Total Income (LKR):</b></td><td style='padding: 4px; color: #198754; font-weight: bold;'>Rs. " . number_format($total_revenue_lkr, 2) . "</td></tr>
                            </table>
                            <p style='color: #777; font-size: 11px; margin-top: 20px; text-align: center;'>&copy; CraftedCeylon Administration</p>
                        </div>
                    </div>
                ";

                $mail->send();
                $sent_count++;
            } catch (Exception $e) {
                // Continue
            }
        }
        if ($sent_count > 0) {
            $success_msg = "Report ($format_label) successfully emailed to $sent_count selected admin(s).";
        } else {
            $error_msg = "Failed to send email. Please check your SMTP settings.";
        }
    } else {
        $error_msg = "Please select at least one admin to send the report.";
    }
}

// Fetch Admins for Email Sharing Checkbox List
$admin_sql = "SELECT admin_id, username, email, role FROM admins WHERE 1=1";
if (!empty($admin_search)) {
    $esc_adm = $conn->real_escape_string($admin_search);
    $admin_sql .= " AND (username LIKE '%$esc_adm%' OR email LIKE '%$esc_adm%')";
}
$admin_sql .= " ORDER BY username ASC";
$admins_result = $conn->query($admin_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprehensive Orders Report - CraftedCeylon Admin</title>
    <!-- Google Font -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        :root {
            --bg-dark: #0d0d12;
            --bg-card: rgba(26, 26, 40, 0.85);
            --bg-sidebar: #13131d;
            --text-main: #ffffff;
            --text-sub: #a0aec0;
            --accent-green: #198754;
            --border-color: rgba(255, 255, 255, 0.08);
        }

        body {
            background-color: var(--bg-dark);
            background-image: linear-gradient(135deg, rgba(13, 13, 18, 0.95), rgba(20, 30, 25, 0.9)), 
                            radial-gradient(circle at 10% 20%, rgba(25, 135, 84, 0.15) 0%, transparent 40%),
                            radial-gradient(circle at 90% 80%, rgba(13, 202, 240, 0.08) 0%, transparent 40%);
            background-attachment: fixed;
            color: var(--text-main);
            font-family: 'Plus Jakarta Sans', sans-serif;
            min-height: 100vh;
        }

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            background-color: var(--bg-sidebar);
            border-right: 1px solid var(--border-color);
            backdrop-filter: blur(15px);
            overflow-y: auto;
            z-index: 1030;
        }

        .main-content-wrapper {
            margin-left: 16.666667%;
        }
        @media (max-width: 768px) {
            .main-content-wrapper { margin-left: 0; }
            .sidebar { position: relative; height: auto; }
        }

        .nav-link {
            color: var(--text-sub);
            border-radius: 12px;
            padding: 12px 16px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.05);
            color: var(--text-main) !important;
            transform: translateX(4px);
        }
        .nav-link.active {
            background: linear-gradient(135deg, #198754 0%, #146c43 100%);
            color: #ffffff !important;
            box-shadow: 0 4px 20px rgba(25, 135, 84, 0.35);
        }

        .dark-card {
            background: var(--bg-card);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border-color);
            border-radius: 18px;
            box-shadow: 0 16px 40px rgba(0, 0, 0, 0.5);
        }

        .metric-card {
            background: rgba(19, 19, 29, 0.85);
            border: 1px solid var(--border-color);
            border-left: 4px solid var(--accent-green);
            border-radius: 12px;
            padding: 12px 18px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-dark-custom {
            --bs-table-bg: transparent;
            --bs-table-color: var(--text-main);
            border-color: var(--border-color);
        }
        .table-dark-custom th {
            color: var(--accent-green);
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.8px;
            padding: 12px 14px;
            border-bottom: 2px solid var(--border-color);
            background-color: rgba(19, 19, 29, 0.7);
        }
        .table-dark-custom td {
            padding: 11px 14px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-main);
            vertical-align: middle;
            font-size: 0.88rem;
        }
        .filter-input {
            background-color: rgba(18, 18, 22, 0.8);
            border: 1px solid var(--border-color);
            color: var(--text-main);
            border-radius: 8px;
            font-size: 0.85rem;
            padding: 8px 12px;
        }
        .filter-input:focus {
            background-color: rgba(18, 18, 22, 0.95);
            border-color: var(--accent-green);
            color: var(--text-main);
            box-shadow: 0 0 0 0.2rem rgba(25, 135, 84, 0.25);
        }

        .admin-checkbox-list {
            max-height: 180px;
            overflow-y: auto;
            background: rgba(18, 18, 22, 0.6);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 10px;
        }

        /* Creative Brand Banner (Navbar style logo & name) */
        .creative-brand-banner {
            background: linear-gradient(135deg, rgba(25, 135, 84, 0.2) 0%, rgba(19, 19, 29, 0.9) 100%);
            border: 1px solid rgba(25, 135, 84, 0.35);
            border-radius: 16px;
            padding: 18px 24px;
            margin-bottom: 22px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        /* Clean Print Styles */
        @media print {
            @page {
                size: A4 portrait;
                margin: 10mm;
            }
            .sidebar, .filter-section, .btn-print-export, .nav-link, .email-share-section, .screen-only, button {
                display: none !important;
            }
            .main-content-wrapper { 
                margin-left: 0 !important; 
                padding: 0 !important; 
                width: 100% !important;
            }
            body { 
                background: #ffffff !important; 
                color: #000000 !important; 
                font-size: 11px !important;
            }
            .creative-brand-banner {
                background: #f0fdf4 !important;
                border: 2px solid #198754 !important;
                border-radius: 10px !important;
                padding: 12px 18px !important;
                margin-bottom: 14px !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .creative-brand-banner h2 {
                color: #198754 !important;
                font-size: 20px !important;
            }
            .creative-brand-banner span, .creative-brand-banner p, .creative-brand-banner strong {
                color: #222222 !important;
            }
            .dark-card { 
                background: #ffffff !important; 
                border: none !important; 
                box-shadow: none !important; 
                color: #000000 !important; 
                padding: 0 !important;
                margin-bottom: 10px !important;
            }
            .summary-metrics-row {
                display: flex !important;
                flex-wrap: wrap !important;
                margin: 0 -5px 12px -5px !important;
            }
            .summary-metrics-row > div {
                width: 50% !important;
                flex: 0 0 50% !important;
                max-width: 50% !important;
                padding: 3px 5px !important;
            }
            .metric-card { 
                background: #f8f9fa !important; 
                border: 1px solid #ccc !important; 
                border-left: 4px solid #198754 !important;
                color: #000 !important; 
                padding: 7px 12px !important;
                border-radius: 6px !important;
            }
            .metric-card h6 {
                font-size: 9px !important;
                color: #444 !important;
                margin: 0 !important;
            }
            .metric-card h4 {
                font-size: 14px !important;
                color: #000 !important;
                margin: 0 !important;
            }
            .table-responsive {
                overflow: visible !important;
            }
            .table-dark-custom {
                width: 100% !important;
                color: #000000 !important;
                border-collapse: collapse !important;
            }
            .table-dark-custom th { 
                color: #ffffff !important; 
                background-color: #198754 !important; 
                border: 1px solid #198754 !important;
                padding: 5px 8px !important;
                font-size: 9.5px !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .table-dark-custom td { 
                color: #000000 !important; 
                border: 1px solid #ccc !important; 
                padding: 5px 8px !important;
                font-size: 10px !important;
            }
            .text-white, .text-secondary, .text-success, .text-info, .text-warning, .text-danger {
                color: #000000 !important;
            }
            .badge {
                border: 1px solid #555 !important;
                color: #000 !important;
                background: transparent !important;
                padding: 2px 5px !important;
                font-size: 9px !important;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid p-0">
        <div class="row g-0">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar p-3">
                <div class="d-flex align-items-center gap-2 mb-4 px-2">
                    <div class="bg-success text-white rounded-3 p-2 d-flex align-items-center justify-content-center">
                        <i class="bi bi-flower1 fs-4"></i>
                    </div>
                    <h5 class="fw-bold text-white mb-0">CraftedCeylon</h5>
                </div>
                <ul class="nav flex-column gap-2">
                    <li class="nav-item"><a href="dashboard.php" class="nav-link"><i class="bi bi-speedometer2 me-2"></i> Dashboard</a></li>
                    <li class="nav-item"><a href="products.php" class="nav-link"><i class="bi bi-box-seam me-2"></i> Products</a></li>
                    <li class="nav-item"><a href="orders.php" class="nav-link"><i class="bi bi-cart-check me-2"></i> Orders & Tracking</a></li>
        
                    <li class="nav-item"><a href="inventory.php" class="nav-link"><i class="bi bi-stack me-2"></i> Inventory Stock</a></li>
                    <li class="nav-item"><a href="customers.php" class="nav-link"><i class="bi bi-people me-2"></i> Customers</a></li>
                    <li class="nav-item"><a href="reviews.php" class="nav-link"><i class="bi bi-star-half me-2"></i> Customer Reviews</a></li>
                    <?php if ($is_super_admin): ?>
                    <li class="nav-item"><a href="manage-admins.php" class="nav-link text-warning"><i class="bi bi-shield-lock-fill me-2"></i> Manage Admins</a></li>
                    <?php endif; ?>
                    <li class="nav-item mt-5"><a href="logout.php" class="nav-link text-danger"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
                </ul>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 ms-auto main-content-wrapper p-4 p-md-5">
                
                <?php if(!empty($success_msg)): ?>
                    <div class="alert alert-success bg-success text-white border-0 shadow-sm alert-dismissible fade show screen-only" role="alert">
                        <i class="bi bi-check-circle me-1"></i> <?php echo $success_msg; ?>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if(!empty($error_msg)): ?>
                    <div class="alert alert-danger bg-danger text-white border-0 shadow-sm alert-dismissible fade show screen-only" role="alert">
                        <i class="bi bi-exclamation-triangle me-1"></i> <?php echo $error_msg; ?>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Navbar Logo & Name Matched Creative Brand Banner -->
                <div class="creative-brand-banner">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-success text-white rounded-3 d-flex align-items-center justify-content-center shadow" style="width: 48px; height: 48px; -webkit-print-color-adjust: exact; print-color-adjust: exact;">
                            <i class="bi bi-flower1 fs-4"></i>
                        </div>
                        <div>
                            <h2 class="fw-bold text-white mb-0" style="letter-spacing: -0.5px;">CraftedCeylon</h2>
                            <span class="text-success small fw-semibold text-uppercase" style="letter-spacing: 0.8px;">Orders & Financial Summary Report</span>
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="small text-secondary">
                            <strong class="text-white">Period:</strong> <?php echo (!empty($from_date) && !empty($to_date)) ? htmlspecialchars("$from_date to $to_date") : "All Time"; ?><br>
                            <strong class="text-white">Generated:</strong> <?php echo date('Y-m-d h:i A'); ?>
                        </div>
                        <div class="d-flex gap-2 justify-content-end mt-2 btn-print-export">
                            <a href="order_report.php?from_date=<?php echo urlencode($from_date); ?>&to_date=<?php echo urlencode($to_date); ?>&status_filter=<?php echo urlencode($status_filter); ?>&export=csv" class="btn btn-outline-success btn-sm fw-bold">
                                <i class="bi bi-file-earmark-spreadsheet me-1"></i> Excel
                            </a>
                            <a href="order_report.php?from_date=<?php echo urlencode($from_date); ?>&to_date=<?php echo urlencode($to_date); ?>&status_filter=<?php echo urlencode($status_filter); ?>&export=pdf" class="btn btn-outline-danger btn-sm fw-bold">
                                <i class="bi bi-file-earmark-pdf me-1"></i> PDF
                            </a>
                            <button onclick="window.print()" class="btn btn-success btn-sm fw-bold">
                                <i class="bi bi-printer me-1"></i> Print
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Filter Section -->
                <div class="dark-card p-3 p-md-4 mb-4 filter-section">
                    <form action="order_report.php" method="GET" class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label small text-secondary fw-bold">From Date</label>
                            <input type="date" name="from_date" class="form-control filter-input" value="<?php echo htmlspecialchars($from_date); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-secondary fw-bold">To Date</label>
                            <input type="date" name="to_date" class="form-control filter-input" value="<?php echo htmlspecialchars($to_date); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-secondary fw-bold">Order Status</label>
                            <select name="status_filter" class="form-select filter-input">
                                <option value="">All Statuses</option>
                                <option value="Processing" <?php if($status_filter=='Processing') echo 'selected'; ?>>Processing</option>
                                <option value="Shipped" <?php if($status_filter=='Shipped') echo 'selected'; ?>>Shipped</option>
                                <option value="Delivered" <?php if($status_filter=='Delivered') echo 'selected'; ?>>Delivered</option>
                                <option value="Cancelled" <?php if($status_filter=='Cancelled') echo 'selected'; ?>>Cancelled</option>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex gap-2">
                            <button type="submit" class="btn btn-success w-100 fw-bold btn-sm py-2"><i class="bi bi-funnel me-1"></i> Filter</button>
                            <a href="order_report.php" class="btn btn-outline-secondary btn-sm py-2" title="Reset Filters"><i class="bi bi-arrow-counterclockwise"></i></a>
                        </div>
                    </form>
                </div>

                <!-- Compact Summary Metrics Grid -->
                <div class="row g-3 mb-4 summary-metrics-row">
                    <div class="col-md-6">
                        <div class="metric-card">
                            <h6 class="text-secondary small text-uppercase fw-bold mb-0"><i class="bi bi-bag-check text-success me-2"></i>Total Orders</h6>
                            <h4 class="fw-bold text-white mb-0"><?php echo intval($total_orders_count); ?></h4>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="metric-card">
                            <h6 class="text-secondary small text-uppercase fw-bold mb-0"><i class="bi bi-cash-stack text-success me-2"></i>Total Income (LKR)</h6>
                            <h4 class="fw-bold text-success mb-0">Rs. <?php echo number_format($total_revenue_lkr, 2); ?></h4>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="metric-card">
                            <h6 class="text-secondary small text-uppercase fw-bold mb-0"><i class="bi bi-check2-circle text-info me-2"></i>Delivered / Cancelled</h6>
                            <h4 class="fw-bold text-info mb-0"><?php echo $status_counts['Delivered']; ?> <span class="text-secondary fs-6">/</span> <span class="text-danger"><?php echo $status_counts['Cancelled']; ?></span></h4>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="metric-card">
                            <h6 class="text-secondary small text-uppercase fw-bold mb-0"><i class="bi bi-truck text-warning me-2"></i>Processing / Shipped</h6>
                            <h4 class="fw-bold text-warning mb-0"><?php echo $status_counts['Processing']; ?> <span class="text-secondary fs-6">/</span> <span class="text-info"><?php echo $status_counts['Shipped']; ?></span></h4>
                        </div>
                    </div>
                </div>

                <!-- Share Report via Email to Admins Section (Placed BEFORE Orders Detailed Log) -->
                <div class="dark-card p-4 email-share-section mb-4">
                    <h5 class="fw-bold text-white mb-2"><i class="bi bi-envelope-at me-2 text-success"></i> Share Report via Email to Admins</h5>
                    <p class="text-secondary small mb-3">Choose whether to send a PDF document or Excel sheet, select administrators, and send immediately.</p>
                    
                    <form action="order_report.php?from_date=<?php echo urlencode($from_date); ?>&to_date=<?php echo urlencode($to_date); ?>&status_filter=<?php echo urlencode($status_filter); ?>" method="POST">
                        
                        <!-- File Format Selection -->
                        <div class="mb-3 p-3 rounded" style="background: rgba(18, 18, 22, 0.6); border: 1px solid var(--border-color);">
                            <label class="form-label small text-white fw-bold d-block mb-2">Select Report Format to Attach:</label>
                            <div class="d-flex flex-wrap gap-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="attachment_format" id="formatPDF" value="pdf" checked>
                                    <label class="form-check-label text-white small fw-semibold" for="formatPDF">
                                        <i class="bi bi-file-earmark-pdf-fill text-danger me-1"></i> PDF Document (.pdf)
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="attachment_format" id="formatExcel" value="excel">
                                    <label class="form-check-label text-white small fw-semibold" for="formatExcel">
                                        <i class="bi bi-file-earmark-excel-fill text-success me-1"></i> Excel Sheet (.csv)
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Search Box for Admins -->
                        <div class="input-group input-group-sm mb-3" style="max-width: 400px;">
                            <input type="text" name="admin_search" class="form-control filter-input" placeholder="Search admin by username or email..." value="<?php echo htmlspecialchars($admin_search); ?>">
                            <button class="btn btn-outline-success" type="submit"><i class="bi bi-search"></i> Search</button>
                            <?php if(!empty($admin_search)): ?>
                                <a href="order_report.php" class="btn btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
                            <?php endif; ?>
                        </div>

                        <!-- Admins Checkbox List -->
                        <div class="admin-checkbox-list mb-3">
                            <?php if ($admins_result && $admins_result->num_rows > 0): ?>
                                <div class="row row-cols-1 row-cols-md-3 g-2">
                                    <?php while($adm = $admins_result->fetch_assoc()): ?>
                                        <div class="col">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="selected_admins[]" value="<?php echo $adm['admin_id']; ?>" id="adminCheck<?php echo $adm['admin_id']; ?>">
                                                <label class="form-check-label text-white small" for="adminCheck<?php echo $adm['admin_id']; ?>">
                                                    <strong><?php echo htmlspecialchars($adm['username']); ?></strong><br>
                                                    <span class="text-secondary" style="font-size: 11px;"><?php echo htmlspecialchars($adm['email']); ?> (<?php echo htmlspecialchars($adm['role']); ?>)</span>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-secondary small mb-0 text-center py-2">No matching admins found.</p>
                            <?php endif; ?>
                        </div>

                        <button type="submit" name="send_report_email" class="btn btn-success btn-sm fw-bold px-4 py-2">
                            <i class="bi bi-send me-1"></i> Send Report to Selected Admins
                        </button>
                    </form>
                </div>

                <!-- Detailed Orders Table Report -->
                <div class="dark-card p-3 p-md-4 mb-4">
                    <h5 class="fw-bold text-white mb-3 screen-only"><i class="bi bi-table me-2 text-success"></i> Orders Detailed Log</h5>
                    <div class="table-responsive">
                        <table class="table table-dark-custom align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Ref ID</th>
                                    <th>Order Date & Time</th>
                                    <th>Customer Email</th>
                                    <th>Order State</th>
                                    <th>Tracking No</th>
                                    <th class="text-end">Total Price</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($orders_array)): ?>
                                    <?php foreach($orders_array as $order): 
                                        $st = $order['order_status'];
                                        $badge = 'warning text-dark';
                                        if($st == 'Delivered') $badge = 'success';
                                        elseif($st == 'Shipped') $badge = 'info';
                                        elseif($st == 'Cancelled') $badge = 'danger';
                                    ?>
                                        <tr>
                                            <td class="fw-bold text-success"><?php echo htmlspecialchars($order['order_reference']); ?></td>
                                            <td class="small text-secondary">
                                                <?php echo date('Y-m-d', strtotime($order['order_date'])); ?> 
                                                <span class="ms-1"><?php echo date('H:i:s', strtotime($order['order_date'])); ?></span>
                                            </td>
                                            <td><span class="text-white"><?php echo htmlspecialchars($order['resolved_email']); ?></span></td>
                                            <td><span class="badge bg-<?php echo $badge; ?>"><?php echo htmlspecialchars($st); ?></span></td>
                                            <td><small class="text-white"><?php echo !empty($order['tracking_number']) ? htmlspecialchars($order['tracking_number']) : '-'; ?></small></td>
                                            <td class="fw-bold text-end text-success"><?php echo htmlspecialchars($order['currency_code'] ?? 'LKR') . ' ' . number_format((float)$order['total_amount'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-secondary py-5">No orders recorded for the selected filter criteria.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>