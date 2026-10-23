<?php
require_once '../config/db.php';

// කලින් තිබුණු Admin records සියල්ල ඉවත් කර අලුතින් නිවැරදිව එකතු කිරීම
$conn->query("DELETE FROM admins WHERE username = 'Admin'");

$username = 'Admin';
$email = 'lasadaimesh@gmail.com';
$password = 'Admin@123';

// BCRYPT මුරපද Hash එක නිවැරදිව සාදා ගැනීම
$hashed_password = password_hash($password, PASSWORD_BCRYPT);

$stmt = $conn->prepare("INSERT INTO admins (username, email, password_hash) VALUES (?, ?, ?)");
$stmt->bind_param("sss", $username, $email, $hashed_password);

if ($stmt->execute()) {
    echo "<h2 style='color:green;'>Success: Admin Account Created/Reset Successfully!</h2>";
    echo "<p>Username: <b>Admin</b></p>";
    echo "<p>Password: <b>Admin@123</b></p>";
    echo "<br><a href='login.php'>Go to Admin Login Page</a>";
} else {
    echo "Error: " . $conn->error;
}
?>