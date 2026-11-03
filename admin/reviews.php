<?php
session_start();
require_once '../config/db.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged'])) {
    header("Location: login.php");
    exit();
}

$current_admin_id = $_SESSION['admin_id'];

// Check if the logged-in admin is a Super Admin
$role_stmt = $conn->prepare("SELECT role, username FROM admins WHERE admin_id = ?");
$role_stmt->bind_param("i", $current_admin_id);
$role_stmt->execute();
$current_admin = $role_stmt->get_result()->fetch_assoc();
$is_super_admin = ($current_admin && $current_admin['role'] === 'Super Admin');

// Handle Review Actions (Approve, Hide, Delete)
if (isset($_POST['action_type']) && isset($_POST['review_id'])) {
    $review_id = intval($_POST['review_id']);
    $action = $_POST['action_type'];

    if ($action === 'approve') {
        $stmt = $conn->prepare("UPDATE product_reviews SET status = 'Approved', is_approved = 1 WHERE review_id = ?");
        $stmt->bind_param("i", $review_id);
        $stmt->execute();
    } elseif ($action === 'hide') {
        $stmt = $conn->prepare("UPDATE product_reviews SET status = 'Hidden', is_approved = 0 WHERE review_id = ?");
        $stmt->bind_param("i", $review_id);
        $stmt->execute();
    } elseif ($action === 'delete') {
        $stmt = $conn->prepare("DELETE FROM product_reviews WHERE review_id = ?");
        $stmt->bind_param("i", $review_id);
        $stmt->execute();
    }
    header("Location: reviews.php");
    exit();
}

// Fetch all reviews with user and product details
$sql = "SELECT r.*, u.full_name, u.email, p.product_name 
        FROM product_reviews r 
        LEFT JOIN users u ON r.user_id = u.user_id 
        LEFT JOIN products p ON r.product_id = p.product_id 
        ORDER BY r.created_at DESC";
$reviews_result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Reviews Management - Admin Dark Portal</title>
    <!-- Google Font -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        :root {
            --bg-dark: #121216;
            --bg-card: #1e1e2f;
            --bg-sidebar: #161622;
            --text-main: #ffffff;
            --text-sub: #adb5bd;
            --accent-green: #198754;
            --border-color: rgba(255, 255, 255, 0.08);
        }

        body {
            background-color: var(--bg-dark);
            color: var(--text-main);
            font-family: 'Plus Jakarta Sans', sans-serif;
            min-height: 100vh;
        }

        /* Sidebar Styling */
        .sidebar {
            background-color: var(--bg-sidebar);
            border-right: 1px solid var(--border-color);
            backdrop-filter: blur(10px);
        }

        .nav-link {
            color: var(--text-sub);
            border-radius: 10px;
            padding: 12px 16px;
            font-weight: 500;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.05);
            color: var(--text-main) !important;
            transform: translateX(4px);
        }

        .nav-link.active {
            background: linear-gradient(135deg, #198754 0%, #146c43 100%);
            color: #ffffff !important;
            box-shadow: 0 4px 15px rgba(25, 135, 84, 0.3);
        }

        /* Glassmorphism Dark Card */
        .dark-card {
            background: rgba(30, 30, 47, 0.75);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.4);
        }

        /* High-Contrast Table Styling */
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
            padding: 14px 16px;
            border-bottom: 2px solid var(--border-color);
            background-color: rgba(22, 22, 34, 0.5);
            white-space: nowrap;
        }

        .table-dark-custom td {
            padding: 14px 16px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-main);
            vertical-align: middle;
            font-size: 0.9rem;
        }

        .table-dark-custom tbody tr:hover {
            background-color: rgba(255, 255, 255, 0.03);
        }

        .glow-success {
            box-shadow: 0 0 15px rgba(25, 135, 84, 0.2);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar min-vh-100 p-3">
                <div class="d-flex align-items-center gap-2 mb-4 px-2">
                    <div class="bg-success text-white rounded-3 p-2 d-flex align-items-center justify-content-center glow-success">
                        <i class="bi bi-flower1 fs-4"></i>
                    </div>
                    <h5 class="fw-bold text-white mb-0">CraftedCeylon</h5>
                </div>
                <ul class="nav flex-column gap-2">
                    <li class="nav-item">
                        <a href="dashboard.php" class="nav-link">
                            <i class="bi bi-speedometer2 me-2"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="products.php" class="nav-link">
                            <i class="bi bi-box-seam me-2"></i> Products
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="orders.php" class="nav-link">
                            <i class="bi bi-cart-check me-2"></i> Orders & Tracking
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="inventory.php" class="nav-link">
                            <i class="bi bi-stack me-2"></i> Inventory Stock
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="customers.php" class="nav-link">
                            <i class="bi bi-people me-2"></i> Customers
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="reviews.php" class="nav-link active">
                            <i class="bi bi-star-half me-2"></i> Customer Reviews
                        </a>
                    </li>

                    <!-- Super Admin Only Menu Item -->
                    <?php if ($is_super_admin): ?>
                    <li class="nav-item">
                        <a href="manage-admins.php" class="nav-link text-warning">
                            <i class="bi bi-shield-lock-fill me-2"></i> Manage Admins
                        </a>
                    </li>
                    <?php endif; ?>

                    <li class="nav-item mt-5">
                        <a href="logout.php" class="nav-link text-danger">
                            <i class="bi bi-box-arrow-right me-2"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 p-4 p-md-5">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="fw-bold text-white mb-1">Customer Reviews Management</h2>
                        <p class="text-secondary small mb-0">Approve, hide, or manage feedback submitted by customers.</p>
                    </div>
                </div>

                <div class="dark-card p-4">
                    <div class="table-responsive">
                        <table class="table table-dark-custom align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Review ID</th>
                                    <th>Customer</th>
                                    <th>Product</th>
                                    <th>Rating</th>
                                    <th>Comment</th>
                                    <th>Status</th>
                                    <th class="text-center" style="min-width: 200px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($reviews_result && $reviews_result->num_rows > 0): ?>
                                    <?php while($rev = $reviews_result->fetch_assoc()): ?>
                                    <tr>
                                        <td class="fw-bold text-secondary">#<?php echo $rev['review_id']; ?></td>
                                        <td>
                                            <span class="text-white fw-semibold"><?php echo htmlspecialchars($rev['full_name'] ?? 'Guest'); ?></span><br>
                                            <small class="text-secondary"><?php echo htmlspecialchars($rev['email'] ?? 'N/A'); ?></small>
                                        </td>
                                        <td><span class="text-info"><?php echo htmlspecialchars($rev['product_name'] ?? 'Product #' . $rev['product_id']); ?></span></td>
                                        <td>
                                            <span class="text-warning">
                                                <?php for($i = 1; $i <= 5; $i++): ?>
                                                    <i class="bi bi-star<?php echo ($i <= $rev['rating']) ? '-fill' : ''; ?>"></i>
                                                <?php endfor; ?>
                                            </span>
                                            <small class="text-secondary">(<?php echo $rev['rating']; ?>/5)</small>
                                        </td>
                                        <td>
                                            <span class="text-light" style="max-width: 250px; display: inline-block; word-break: break-word;">
                                                <?php echo htmlspecialchars($rev['comment']); ?>
                                            </span>
                                            <?php if(!empty($rev['review_image'])): ?>
                                                <br><a href="../<?php echo htmlspecialchars($rev['review_image']); ?>" target="_blank" class="small text-success"><i class="bi bi-image me-1"></i>View Image</a>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php 
                                                $st = $rev['status'] ?? 'Pending';
                                                $badge_bg = 'warning text-dark';
                                                if ($st === 'Approved') $badge_bg = 'success bg-opacity-25 text-success border border-success';
                                                elseif ($st === 'Hidden') $badge_bg = 'secondary text-light';
                                            ?>
                                            <span class="badge bg-<?php echo $badge_bg; ?>"><?php echo $st; ?></span>
                                        </td>
                                        <td class="text-center">
                                            <form action="reviews.php" method="POST" class="d-flex justify-content-center gap-1">
                                                <input type="hidden" name="review_id" value="<?php echo $rev['review_id']; ?>">
                                                
                                                <?php if($st !== 'Approved'): ?>
                                                    <button type="submit" name="action_type" value="approve" class="btn btn-sm btn-success px-2 py-1" title="Approve & Publish">
                                                        <i class="bi bi-check-lg"></i> Approve
                                                    </button>
                                                <?php else: ?>
                                                    <button type="submit" name="action_type" value="hide" class="btn btn-sm btn-outline-warning px-2 py-1" title="Hide Review">
                                                        <i class="bi bi-eye-slash"></i> Hide
                                                    </button>
                                                <?php endif; ?>

                                                <button type="submit" name="action_type" value="delete" class="btn btn-sm btn-outline-danger px-2 py-1" onclick="return confirm('Are you sure you want to delete this review?');" title="Delete Review">
                                                    <i class="bi bi-trash"></i> Delete
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-secondary py-5">No customer reviews found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>