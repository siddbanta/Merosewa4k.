<?php
require_once 'common/config.php';

// Redirect if not logged in or cart is empty
if (!isset($_SESSION['user_id']) || empty($_SESSION['cart'])) {
    header("Location: " . (empty($_SESSION['cart']) ? "cart.php" : "login.php"));
    exit();
}

$user_id = $_SESSION['user_id'];

// --- ORDER PLACEMENT LOGIC ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $name = sanitize_input($_POST['name']);
    $phone = sanitize_input($_POST['phone']);
    $address = sanitize_input($_POST['address']);
    
    // Basic validation
    if (empty($name) || empty($phone) || empty($address)) {
        $error = "All fields are required.";
    } else {
        // Calculate total amount from server-side to prevent manipulation
        $total_amount = 0;
        $cart_items = [];
        $product_ids = implode(',', array_keys($_SESSION['cart']));
        $sql = "SELECT id, price FROM products WHERE id IN ($product_ids)";
        $result = $conn->query($sql);
        $products_data = [];
        while($row = $result->fetch_assoc()) {
            $products_data[$row['id']] = $row['price'];
        }

        foreach ($_SESSION['cart'] as $product_id => $quantity) {
            if (isset($products_data[$product_id])) {
                $price = $products_data[$product_id];
                $total_amount += $price * $quantity;
            }
        }

        // Use transaction for placing order
        $conn->begin_transaction();
        try {
            // 1. Insert into orders table
            $stmt = $conn->prepare("INSERT INTO orders (user_id, total_amount, address, phone) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("idss", $user_id, $total_amount, $address, $phone);
            $stmt->execute();
            $order_id = $stmt->insert_id;
            $stmt->close();
            
            // 2. Insert into order_items table
            $stmt_items = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
            foreach ($_SESSION['cart'] as $product_id => $quantity) {
                if (isset($products_data[$product_id])) {
                    $price = $products_data[$product_id];
                    $stmt_items->bind_param("iiid", $order_id, $product_id, $quantity, $price);
                    $stmt_items->execute();
                }
            }
            $stmt_items->close();
            
            // 3. (Optional but good practice) Update stock
            foreach ($_SESSION['cart'] as $product_id => $quantity) {
                $conn->query("UPDATE products SET stock = stock - $quantity WHERE id = $product_id");
            }
            
            // All good, commit the transaction
            $conn->commit();
            
            // 4. Clear the cart
            unset($_SESSION['cart']);
            
            // 5. Redirect to order confirmation/details page
            header("Location: order.php?success=true&order_id=$order_id");
            exit();

        } catch (mysqli_sql_exception $exception) {
            $conn->rollback();
            $error = "Failed to place order. Please try again. " . $exception->getMessage();
        }
    }
}
// --- END ORDER LOGIC ---

// Fetch user details to pre-fill the form
$user_res = $conn->query("SELECT name, phone, address FROM users WHERE id = $user_id");
$user = $user_res->fetch_assoc();

require_once 'common/header.php';
?>

<!-- Top Header -->
<header class="sticky top-0 bg-white shadow-sm z-10 p-4 flex items-center">
    <a href="cart.php" class="text-gray-600 text-xl mr-4"><i class="fas fa-arrow-left"></i></a>
    <h1 class="text-xl font-bold text-gray-800">Checkout</h1>
</header>

<div class="p-4">
    <form method="POST" class="space-y-6">
        <h2 class="text-lg font-semibold text-gray-700 border-b pb-2">Shipping Information</h2>

        <?php if (isset($error)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
            <span class="block sm:inline"><?= $error ?></span>
        </div>
        <?php endif; ?>

        <div>
            <label for="name" class="block text-sm font-medium text-gray-700">Full Name</label>
            <input type="text" name="name" id="name" value="<?= htmlspecialchars($user['name'] ?? '') ?>" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
        </div>

        <div>
            <label for="phone" class="block text-sm font-medium text-gray-700">Phone Number</label>
            <input type="tel" name="phone" id="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
        </div>

        <div>
            <label for="address" class="block text-sm font-medium text-gray-700">Full Address</label>
            <textarea name="address" id="address" rows="4" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
        </div>

        <h2 class="text-lg font-semibold text-gray-700 border-b pb-2 pt-4">Payment Method</h2>
        <div class="bg-gray-100 p-4 rounded-lg flex items-center">
            <i class="fas fa-money-bill-wave text-2xl text-green-600 mr-4"></i>
            <div>
                <h3 class="font-semibold">Cash on Delivery (COD)</h3>
                <p class="text-sm text-gray-500">Pay when your order arrives.</p>
            </div>
        </div>
        
        <button type="submit" name="place_order" class="w-full bg-indigo-600 text-white font-bold py-3 rounded-lg shadow-lg hover:bg-indigo-700">
            Place Order
        </button>
    </form>
</div>

<?php require_once 'common/bottom.php'; ?>