<?php
// We will capture all output into a buffer to display it neatly inside an HTML page.
ob_start();

// --- CONFIGURATION ---
$db_host = '127.0.0.1';
$db_user = 'root';
$db_pass = 'root';
$db_name = 'quickkart_db';
$admin_user = 'admin';
$admin_pass = 'password123';
// ---------------------

function execute_query($conn, $sql, $success_msg) {
    if ($conn->query($sql) === TRUE) {
        echo "✅ SUCCESS: " . htmlspecialchars($success_msg) . "\n";
    } else {
        echo "❌ ERROR executing query for '" . htmlspecialchars($success_msg) . "': " . htmlspecialchars($conn->error) . "\n";
        // Get the output so far and display it before dying
        $output_log = ob_get_clean();
        // Manually create an HTML page to show the error
        echo "<!DOCTYPE html><html lang=\"en\"><head><meta charset=\"UTF-8\"><title>Installation Error</title><style>body { background-color: #1e1e1e; color: #cecece; font-family: monospace; font-size: 14px; } pre { white-space: pre-wrap; word-wrap: break-word; }</style></head><body><pre>" . $output_log . "</pre></body></html>";
        die();
    }
}

// 1. Create Connection to MySQL Server
$conn = new mysqli($db_host, $db_user, $db_pass);
if ($conn->connect_error) {
    die("❌ FATAL: Connection to MySQL failed: " . $conn->connect_error . "\n");
}
echo "✅ SUCCESS: Connected to MySQL server.\n";

// 2. Create Database
$sql = "CREATE DATABASE IF NOT EXISTS $db_name";
execute_query($conn, $sql, "Database '$db_name' created or already exists.");

// 3. Select the Database
$conn->select_db($db_name);
echo "✅ SUCCESS: Selected database '$db_name'.\n";

// --- TABLE CREATION (abbreviated for clarity, use the full SQL from previous steps) ---
$sql_admin = "CREATE TABLE IF NOT EXISTS `admin` (`id` int(11) NOT NULL AUTO_INCREMENT,`username` varchar(50) NOT NULL,`password` varchar(255) NOT NULL,PRIMARY KEY (`id`),UNIQUE KEY `username` (`username`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
execute_query($conn, $sql_admin, "Table 'admin' created.");
$sql_users = "CREATE TABLE IF NOT EXISTS `users` (`id` int(11) NOT NULL AUTO_INCREMENT,`name` varchar(100) NOT NULL,`phone` varchar(15) NOT NULL,`email` varchar(100) NOT NULL,`password` varchar(255) NOT NULL,`address` text DEFAULT NULL,`created_at` timestamp NOT NULL DEFAULT current_timestamp(),PRIMARY KEY (`id`),UNIQUE KEY `email` (`email`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
execute_query($conn, $sql_users, "Table 'users' created.");
$sql_categories = "CREATE TABLE IF NOT EXISTS `categories` (`id` int(11) NOT NULL AUTO_INCREMENT,`name` varchar(100) NOT NULL,`image` varchar(255) DEFAULT NULL,PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
execute_query($conn, $sql_categories, "Table 'categories' created.");
$sql_products = "CREATE TABLE IF NOT EXISTS `products` (`id` int(11) NOT NULL AUTO_INCREMENT,`cat_id` int(11) NOT NULL,`name` varchar(255) NOT NULL,`description` text NOT NULL,`price` decimal(10,2) NOT NULL,`stock` int(11) NOT NULL,`image` varchar(255) NOT NULL,`created_at` timestamp NOT NULL DEFAULT current_timestamp(),PRIMARY KEY (`id`),KEY `cat_id` (`cat_id`),CONSTRAINT `products_ibfk_1` FOREIGN KEY (`cat_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
execute_query($conn, $sql_products, "Table 'products' created.");
$sql_orders = "CREATE TABLE IF NOT EXISTS `orders` (`id` int(11) NOT NULL AUTO_INCREMENT,`user_id` int(11) NOT NULL,`total_amount` decimal(10,2) NOT NULL,`status` enum('Placed','Dispatched','Delivered','Cancelled') NOT NULL DEFAULT 'Placed',`address` text NOT NULL,`phone` varchar(15) NOT NULL,`created_at` timestamp NOT NULL DEFAULT current_timestamp(),PRIMARY KEY (`id`),KEY `user_id` (`user_id`),CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
execute_query($conn, $sql_orders, "Table 'orders' created.");
$sql_order_items = "CREATE TABLE IF NOT EXISTS `order_items` (`id` int(11) NOT NULL AUTO_INCREMENT,`order_id` int(11) NOT NULL,`product_id` int(11) DEFAULT NULL,`quantity` int(11) NOT NULL,`price` decimal(10,2) NOT NULL,PRIMARY KEY (`id`),KEY `order_id` (`order_id`),KEY `product_id` (`product_id`),CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
execute_query($conn, $sql_order_items, "Table 'order_items' created.");


// --- DATA INSERTION & FOLDER CREATION ---
$hashed_password = password_hash($admin_pass, PASSWORD_DEFAULT);
$stmt = $conn->prepare("INSERT INTO `admin` (`username`, `password`) VALUES (?, ?) ON DUPLICATE KEY UPDATE password=?");
$stmt->bind_param("sss", $admin_user, $hashed_password, $hashed_password);
if ($stmt->execute()) {
    echo "✅ SUCCESS: Default admin user ('$admin_user') created/updated.\n";
} else {
    echo "⚠️ WARNING: Could not insert default admin user. It might already exist.\n";
}
$stmt->close();
$dirs = ['uploads', 'uploads/categories', 'uploads/products'];
foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        if (mkdir($dir, 0775, true)) {
            echo "✅ SUCCESS: Directory '$dir' created.\n";
        } else {
            echo "❌ ERROR: Failed to create directory '$dir'.\n";
        }
    } else {
        echo "✅ INFO: Directory '$dir' already exists.\n";
    }
}

echo "\n🎉 INSTALLATION COMPLETE! 🎉\n";
echo "You will be redirected to the login page in 5 seconds...\n";

$conn->close();

// Get all the output from the buffer
$output_log = ob_get_clean();

// Now, output the final HTML page
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installation Log</title>
    <style>
        body { background-color: #1e1e1e; color: #cecece; font-family: monospace; font-size: 14px; margin: 0; padding: 10px; }
        pre { white-space: pre-wrap; word-wrap: break-word; }
    </style>
</head>
<body>
    <pre><?php echo $output_log; ?></pre>
    <script>
        setTimeout(function() {
            window.location.href = 'login.php';
        }, 5000); // 5000 milliseconds = 5 seconds
    </script>
</body>
</html>