-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 03, 2026 at 09:39 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `craftedceylon_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `admin_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reset_otp` varchar(6) DEFAULT NULL,
  `otp_expiry` datetime DEFAULT NULL,
  `account_status` varchar(50) DEFAULT 'Active',
  `role` varchar(20) DEFAULT 'Admin'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`admin_id`, `username`, `email`, `password_hash`, `created_at`, `reset_otp`, `otp_expiry`, `account_status`, `role`) VALUES
(4, 'lasanda', 'lasadaimesh@gmail.com', '$2y$10$TYSw8iDwCNaImdgtZW1W8OF7VyRd42v0rXmMPJdLt39I/n3Zrz/x.', '2026-10-21 08:32:28', NULL, NULL, 'Active', 'Super Admin'),
(5, 'Imesh', 'liagliagaa@gmail.com', '$2y$10$27QUDmXbW1mOR/PopLcVmeKraQvKJ7ieQeQV5ePKXv7Tn4D/1dmjm', '2026-11-02 07:09:17', NULL, NULL, 'Active', 'Admin');

-- --------------------------------------------------------

--
-- Table structure for table `admin_activity_logs`
--

CREATE TABLE `admin_activity_logs` (
  `log_id` int(11) NOT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `activity_description` varchar(255) DEFAULT NULL,
  `login_time` datetime DEFAULT current_timestamp(),
  `logout_time` datetime DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin_activity_logs`
--

INSERT INTO `admin_activity_logs` (`log_id`, `admin_id`, `activity_description`, `login_time`, `logout_time`, `ip_address`) VALUES
(1, 4, 'Logged In', '2026-11-03 12:43:22', NULL, '::1');

-- --------------------------------------------------------

--
-- Table structure for table `artisans`
--

CREATE TABLE `artisans` (
  `artisan_id` int(11) NOT NULL,
  `artisan_name` varchar(100) NOT NULL,
  `profile_img` varchar(255) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `location` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `artisans`
--

INSERT INTO `artisans` (`artisan_id`, `artisan_name`, `profile_img`, `bio`, `location`) VALUES
(1, 'unenable', NULL, NULL, 'Colombo');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `category_id` int(11) NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`category_id`, `category_name`, `description`) VALUES
(1, 'Wood', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `order_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `order_reference` varchar(50) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `currency_code` varchar(10) DEFAULT 'LKR',
  `shipping_name` varchar(100) NOT NULL,
  `shipping_email` varchar(100) NOT NULL,
  `shipping_phone` varchar(20) NOT NULL,
  `shipping_address` text NOT NULL,
  `city` varchar(50) NOT NULL,
  `postal_code` varchar(20) NOT NULL,
  `shipping_country` varchar(100) NOT NULL,
  `is_gift_wrapped` tinyint(1) DEFAULT 0,
  `gift_wrap_fee` decimal(10,2) DEFAULT 0.00,
  `gift_message` text DEFAULT NULL,
  `order_status` enum('Processing','Shipped','Delivered','Cancelled') DEFAULT 'Processing',
  `tracking_number` varchar(100) DEFAULT NULL,
  `order_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`order_id`, `user_id`, `order_reference`, `total_amount`, `currency_code`, `shipping_name`, `shipping_email`, `shipping_phone`, `shipping_address`, `city`, `postal_code`, `shipping_country`, `is_gift_wrapped`, `gift_wrap_fee`, `gift_message`, `order_status`, `tracking_number`, `order_date`) VALUES
(1, NULL, 'CC-6AD883A344857', 6000.00, 'LKR', 'Lasanda imesh Arjuna Gunasinghe', 'liagliagaa@gmail.com', '0785769838', 'No.198,Kakuluwela. Aralaganwila', 'Aralaganwila', '32128', '', 0, 0.00, '', 'Cancelled', '', '2026-10-21 09:19:31'),
(2, 2, 'CC-6AD8C9E49851F', 12000.00, 'LKR', 'Lasanda imesh', 'lasadaimesh@gmail.com', '0785769838', 'No.198,Kakuluwela. Aralaganwila', 'Aralaganwila', '32128', '', 0, 0.00, '', 'Delivered', '', '2026-10-21 14:19:16'),
(3, 2, 'CC-6AD8E3934F8E1', 37.50, 'USD', 'Lasanda imesh Arjuna Gunasinghe', 'lasadaimesh@gmail.com', '0785769838', 'No.198,Kakuluwela. Aralaganwila', 'Aralaganwila', '32128', 'Australia', 1, 2.50, '', 'Shipped', '', '2026-10-21 16:08:51'),
(4, 2, 'CC-6AD9B578B77D6', 6500.00, 'LKR', 'Lasanda imesh Arjuna Gunasinghe', 'lasadaimesh@gmail.com', '0785769838', 'No.198,Kakuluwela. Aralaganwila', 'Aralaganwila', '32128', 'Sri Lanka', 0, 0.00, '', 'Processing', '', '2026-10-22 07:04:25'),
(5, 2, 'CC-6AE5CCAF79B7B', 25200.00, 'LKR', 'Lasanda imesh Arjuna Gunasinghe', 'lasadaimesh@gmail.com', '+94785769838', 'No.198,Kakuluwela. Aralaganwila', 'Aralaganwila', '32128', 'Sri Lanka', 1, 700.00, '', 'Processing', NULL, '2026-10-31 11:10:39'),
(6, 2, 'CC-6AE806CC410ED', 6500.00, 'LKR', 'Lasanda imesh Arjuna Gunasinghe', 'lasadaimesh@gmail.com', '+94785769838', 'No.198,Kakuluwela. Aralaganwila', 'Aralaganwila', '32128', 'Sri Lanka', 0, 0.00, '', 'Processing', NULL, '2026-11-02 03:43:08');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `order_item_id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `line_total` decimal(10,2) NOT NULL,
  `custom_text` varchar(255) DEFAULT NULL,
  `selected_color` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`order_item_id`, `order_id`, `product_id`, `quantity`, `unit_price`, `line_total`, `custom_text`, `selected_color`) VALUES
(1, 1, 1, 1, 6000.00, 6000.00, '', 'Standard / Natural'),
(2, 2, 1, 2, 6000.00, 12000.00, '', 'Standard / Natural'),
(3, 3, 1, 1, 20.00, 20.00, '', 'Standard / Natural'),
(4, 4, 1, 1, 6000.00, 6000.00, '', 'Standard / Natural'),
(5, 5, 1, 4, 6000.00, 24000.00, '', 'Standard / Natural'),
(6, 6, 1, 1, 6000.00, 6000.00, '', 'Standard / Natural');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `product_id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `artisan_id` int(11) DEFAULT NULL,
  `product_name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `price_lkr` decimal(10,2) NOT NULL,
  `price_usd` decimal(10,2) NOT NULL,
  `stock_quantity` int(11) NOT NULL,
  `main_image` varchar(255) NOT NULL,
  `is_biodegradable` tinyint(1) DEFAULT 0,
  `is_recyclable` tinyint(1) DEFAULT 0,
  `is_customizable` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`product_id`, `category_id`, `artisan_id`, `product_name`, `description`, `price_lkr`, `price_usd`, `stock_quantity`, `main_image`, `is_biodegradable`, `is_recyclable`, `is_customizable`, `created_at`) VALUES
(1, 1, 1, 'Wooden elephant', 'Hand made', 6000.00, 17.99, 1, 'assets/images/products/1792574093_download.webp', 1, 1, 0, '2026-10-21 09:14:53');

-- --------------------------------------------------------

--
-- Table structure for table `product_images`
--

CREATE TABLE `product_images` (
  `image_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `product_images`
--

INSERT INTO `product_images` (`image_id`, `product_id`, `image_path`) VALUES
(1, 1, 'assets/images/products/1793609828_editgal_0_11a.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `product_reviews`
--

CREATE TABLE `product_reviews` (
  `review_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `rating` int(11) DEFAULT NULL CHECK (`rating` between 1 and 5),
  `comment` text DEFAULT NULL,
  `status` enum('Pending','Approved','Hidden') DEFAULT 'Pending',
  `is_approved` tinyint(1) DEFAULT 0,
  `review_image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `product_reviews`
--

INSERT INTO `product_reviews` (`review_id`, `product_id`, `user_id`, `rating`, `comment`, `status`, `is_approved`, `review_image`, `created_at`) VALUES
(1, 1, 2, 5, 'super', 'Approved', 1, NULL, '2026-10-31 09:05:39'),
(2, 1, 2, 5, 'good', 'Approved', 1, 'assets/images/reviews/1793437822_download.webp', '2026-10-31 09:10:22');

-- --------------------------------------------------------

--
-- Table structure for table `review_reactions`
--

CREATE TABLE `review_reactions` (
  `reaction_id` int(11) NOT NULL,
  `review_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `review_reactions`
--

INSERT INTO `review_reactions` (`reaction_id`, `review_id`, `user_id`, `created_at`) VALUES
(2, 1, 2, '2026-10-31 09:09:01');

-- --------------------------------------------------------

--
-- Table structure for table `site_settings`
--

CREATE TABLE `site_settings` (
  `setting_key` varchar(50) NOT NULL,
  `setting_value` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `site_settings`
--

INSERT INTO `site_settings` (`setting_key`, `setting_value`) VALUES
('gift_wrap_fee_lkr', '700'),
('gift_wrap_fee_usd', '2.5'),
('usd_exchange_rate', '333.58');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `google_id` varchar(255) DEFAULT NULL,
  `phone` varchar(20) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `account_status` enum('Active','Suspended','Blocked') DEFAULT 'Active',
  `is_verified` tinyint(1) DEFAULT 0,
  `otp_code` varchar(6) DEFAULT NULL,
  `otp_expiry` datetime DEFAULT NULL,
  `reset_token` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `full_name`, `email`, `google_id`, `phone`, `password_hash`, `account_status`, `is_verified`, `otp_code`, `otp_expiry`, `reset_token`, `created_at`) VALUES
(2, 'Lasanda imesh Arjuna Gunasinghe', 'lasadaimesh@gmail.com', NULL, '', '$2y$10$6nH.PnfyvLwVUGmlwIhyPOaeqxa88C/zkrzbEMCZXPo6KZvfaJVPC', 'Active', 1, NULL, NULL, NULL, '2026-10-21 14:10:32'),
(3, 'imesh Arjuna', 'liagliagaa@gmail.com', NULL, '', '$2y$10$.FxQUIxdBo2hJ5Aqzxbk0uA0F4P4yhyWsEwvSPTNlZvuNShG9UjCW', 'Active', 1, NULL, NULL, NULL, '2026-10-31 11:22:52');

-- --------------------------------------------------------

--
-- Table structure for table `user_activities`
--

CREATE TABLE `user_activities` (
  `activity_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action_name` varchar(255) NOT NULL,
  `page_url` text NOT NULL,
  `activity_time` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_activities`
--

INSERT INTO `user_activities` (`activity_id`, `user_id`, `action_name`, `page_url`, `activity_time`) VALUES
(9, 3, 'User Logged In', 'login.php', '2026-10-31 16:57:17'),
(10, 3, 'User Logged Out', 'logout.php', '2026-10-31 17:43:51'),
(11, 2, 'User Logged In', 'login.php', '2026-11-02 09:10:09'),
(12, 2, 'User Logged In', 'login.php', '2026-11-02 11:04:10'),
(13, 2, 'User Logged In', 'login.php', '2026-11-02 14:51:20'),
(14, 2, 'User Logged In', 'login.php', '2026-11-03 10:17:34');

-- --------------------------------------------------------

--
-- Table structure for table `user_login_logs`
--

CREATE TABLE `user_login_logs` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `login_time` datetime DEFAULT current_timestamp(),
  `logout_time` datetime DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `device_info` text NOT NULL,
  `location` varchar(255) DEFAULT 'Unknown',
  `login_status` varchar(50) DEFAULT 'Success'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_login_logs`
--

INSERT INTO `user_login_logs` (`log_id`, `user_id`, `login_time`, `logout_time`, `ip_address`, `device_info`, `location`, `login_status`) VALUES
(5, 3, '2026-10-31 16:57:17', '2026-10-31 13:13:50', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0', 'Unknown', 'Success'),
(6, 2, '2026-11-02 09:10:08', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0', 'Unknown', 'Success'),
(7, 2, '2026-11-02 11:04:09', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0', 'Unknown', 'Success'),
(8, 2, '2026-11-02 14:51:18', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0', 'Unknown', 'Success'),
(9, 2, '2026-11-03 10:17:34', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0', 'Unknown', 'Success');

-- --------------------------------------------------------

--
-- Table structure for table `wishlist`
--

CREATE TABLE `wishlist` (
  `wishlist_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `wishlist`
--

INSERT INTO `wishlist` (`wishlist_id`, `user_id`, `product_id`, `created_at`) VALUES
(7, 2, 1, '2026-11-02 09:21:43');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`admin_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `admin_activity_logs`
--
ALTER TABLE `admin_activity_logs`
  ADD PRIMARY KEY (`log_id`);

--
-- Indexes for table `artisans`
--
ALTER TABLE `artisans`
  ADD PRIMARY KEY (`artisan_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`category_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`order_id`),
  ADD UNIQUE KEY `order_reference` (`order_reference`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`order_item_id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`product_id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `artisan_id` (`artisan_id`);

--
-- Indexes for table `product_images`
--
ALTER TABLE `product_images`
  ADD PRIMARY KEY (`image_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `product_reviews`
--
ALTER TABLE `product_reviews`
  ADD PRIMARY KEY (`review_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `review_reactions`
--
ALTER TABLE `review_reactions`
  ADD PRIMARY KEY (`reaction_id`);

--
-- Indexes for table `site_settings`
--
ALTER TABLE `site_settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_activities`
--
ALTER TABLE `user_activities`
  ADD PRIMARY KEY (`activity_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `user_login_logs`
--
ALTER TABLE `user_login_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `wishlist`
--
ALTER TABLE `wishlist`
  ADD PRIMARY KEY (`wishlist_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `admin_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `admin_activity_logs`
--
ALTER TABLE `admin_activity_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `artisans`
--
ALTER TABLE `artisans`
  MODIFY `artisan_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `order_item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `product_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `product_images`
--
ALTER TABLE `product_images`
  MODIFY `image_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `product_reviews`
--
ALTER TABLE `product_reviews`
  MODIFY `review_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `review_reactions`
--
ALTER TABLE `review_reactions`
  MODIFY `reaction_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `user_activities`
--
ALTER TABLE `user_activities`
  MODIFY `activity_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `user_login_logs`
--
ALTER TABLE `user_login_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `wishlist`
--
ALTER TABLE `wishlist`
  MODIFY `wishlist_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`);

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`),
  ADD CONSTRAINT `products_ibfk_2` FOREIGN KEY (`artisan_id`) REFERENCES `artisans` (`artisan_id`) ON DELETE SET NULL;

--
-- Constraints for table `product_images`
--
ALTER TABLE `product_images`
  ADD CONSTRAINT `product_images_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE;

--
-- Constraints for table `product_reviews`
--
ALTER TABLE `product_reviews`
  ADD CONSTRAINT `product_reviews_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`),
  ADD CONSTRAINT `product_reviews_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `user_activities`
--
ALTER TABLE `user_activities`
  ADD CONSTRAINT `user_activities_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `user_login_logs`
--
ALTER TABLE `user_login_logs`
  ADD CONSTRAINT `user_login_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
