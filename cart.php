<?php
require_once 'common/config.php';

// --- AJAX HANDLER ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $response = ['status' => 'error', 'message' => 'Invalid action.'];

    // Initialize cart if not set
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;

    switch ($_POST['action']) {
        case 'add_to_cart':
            $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
            if ($product_id > 0 && $quantity > 0) {
                // Check stock
                $res = $conn->query("SELECT stock FROM products WHERE id = $product_id");
                $product = $res->fetch_assoc();
                
                $current_qty_in_cart = isset($_SESSION['cart'][$product_id]) ? $_SESSION['cart'][$product_id] : 0;
                
                if ($product && ($product['stock'] >= $current_qty_in_cart + $quantity)) {
                    if (isset($_SESSION['cart'][$product_id])) {
                        $_SESSION['cart'][$product_id] += $quantity;
                    } else {
                        $_SESSION['cart'][$product_id] = $quantity;
                    }
                    $response = ['status' => 'success', 'message' => 'Item added.', 'cart_count' => count($_SESSION['cart'])];
                } else {
                    $response['message'] = 'Not enough stock available.';
                }
            }
            break;

        case 'update_cart':
            $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
             if ($product_id > 0 && isset($_SESSION['cart'][$product_id])) {
                 if ($quantity > 0) {
                     // Check stock
                    $res = $conn->query("SELECT stock FROM products WHERE id = $product_id");
                    $product = $res->fetch_assoc();
                    if ($product && $product['stock'] >= $quantity) {
                        $_SESSION['cart'][$product_id] = $quantity;
                        $response = ['status' => 'success'];
                    } else {
                        $response['message'] = "Only {$product['stock']} items available.";
                        $_SESSION['cart'][$product_id] = $product['stock']; // Adjust to max available
                        $response['new_quantity'] = $product['stock'];
                    }
                 } else { // Remove if quantity is 0 or less
                     unset($_SESSION['cart'][$product_id]);
                     $response = ['status' => 'success', 'removed' => true];
                 }
             }
            break;

        case 'remove_from_cart':
            if ($product_id > 0 && isset($_SESSION['cart'][$product_id])) {
                unset($_SESSION['cart'][$product_id]);
                $response = ['status' => 'success', 'message' => 'Item removed.'];
            }
            break;
    }
    
    echo json_encode($response);
    exit();
}
// --- END AJAX HANDLER ---

require_once 'common/header.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$cart_items = [];
$total_price = 0;

if (!empty($_SESSION['cart'])) {
    $product_ids = implode(',', array_keys($_SESSION['cart']));
    $sql = "SELECT id, name, price, image, stock FROM products WHERE id IN ($product_ids)";
    $result = $conn->query($sql);
    
    while ($product = $result->fetch_assoc()) {
        $quantity = $_SESSION['cart'][$product['id']];
        $cart_items[] = [
            'id' => $product['id'],
            'name' => $product['name'],
            'price' => $product['price'],
            'image' => $product['image'],
            'stock' => $product['stock'],
            'quantity' => $quantity,
            'subtotal' => $product['price'] * $quantity
        ];
        $total_price += $product['price'] * $quantity;
    }
}
?>

<!-- Top Header -->
<header class="sticky top-0 bg-white shadow-sm z-10 p-4 flex items-center">
    <a href="index.php" class="text-gray-600 text-xl mr-4"><i class="fas fa-arrow-left"></i></a>
    <h1 class="text-xl font-bold text-gray-800">My Cart</h1>
</header>

<div class="p-4" id="cart-container">
    <?php if (empty($cart_items)): ?>
        <div class="text-center py-20">
            <i class="fas fa-shopping-cart text-6xl text-gray-300"></i>
            <p class="mt-4 text-gray-500">Your cart is empty.</p>
            <a href="index.php" class="mt-6 inline-block bg-indigo-600 text-white font-bold py-2 px-6 rounded-lg">Shop Now</a>
        </div>
    <?php else: ?>
        <div class="space-y-4">
            <?php foreach ($cart_items as $item): ?>
            <div id="cart-item-<?= $item['id'] ?>" class="flex items-center bg-white p-3 rounded-lg shadow-sm border">
                <img src="uploads/products/<?= htmlspecialchars($item['image']) ?>" class="w-20 h-20 rounded-md object-cover">
                <div class="flex-grow ml-4">
                    <h3 class="font-semibold text-gray-800"><?= htmlspecialchars($item['name']) ?></h3>
                    <p class="text-indigo-600 font-bold">₹<?= number_format($item['price']) ?></p>
                    <div class="flex items-center mt-2">
                        <button onclick="updateQuantity(<?= $item['id'] ?>, -1)" class="w-6 h-6 border rounded-full text-gray-600">-</button>
                        <input type="text" id="qty-<?= $item['id'] ?>" value="<?= $item['quantity'] ?>" readonly class="w-10 text-center font-semibold">
                        <button onclick="updateQuantity(<?= $item['id'] ?>, 1, <?= $item['stock'] ?>)" class="w-6 h-6 border rounded-full text-gray-600">+</button>
                    </div>
                </div>
                <button onclick="removeItem(<?= $item['id'] ?>)" class="text-red-500 hover:text-red-700 ml-4"><i class="fas fa-trash-alt"></i></button>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Checkout Footer -->
<?php if (!empty($cart_items)): ?>
<div class="sticky bottom-16 left-0 right-0 p-4 bg-white border-t" id="checkout-footer">
    <div class="flex justify-between items-center mb-4">
        <span class="text-gray-600 font-semibold">Total:</span>
        <span id="total-price" class="text-2xl font-bold text-indigo-600">₹<?= number_format($total_price) ?></span>
    </div>
    <a href="checkout.php" class="block w-full text-center bg-indigo-600 text-white font-bold py-3 rounded-lg shadow-lg hover:bg-indigo-700">
        Proceed to Checkout
    </a>
</div>
<?php endif; ?>

<script>
    async function updateCart(productId, newQuantity) {
        showLoader();
        const formData = new FormData();
        formData.append('action', 'update_cart');
        formData.append('product_id', productId);
        formData.append('quantity', newQuantity);

        try {
            const response = await fetch('cart.php', { method: 'POST', body: formData });
            const result = await response.json();
            if (result.status === 'success') {
                if(result.removed) {
                    document.getElementById(`cart-item-${productId}`).remove();
                }
                if(result.new_quantity) {
                     document.getElementById(`qty-${productId}`).value = result.new_quantity;
                     showToast(result.message, false);
                }
                // For simplicity, we just reload the page to update total
                location.reload(); 
            } else {
                showToast(result.message, false);
                 if(result.new_quantity) {
                     document.getElementById(`qty-${productId}`).value = result.new_quantity;
                }
            }
        } catch (error) {
            showToast('An error occurred.', false);
        } finally {
            hideLoader();
        }
    }

    function updateQuantity(productId, change, stock) {
        const qtyInput = document.getElementById(`qty-${productId}`);
        let currentQty = parseInt(qtyInput.value);
        let newQty = currentQty + change;
        
        if (newQty > stock) {
            showToast(`Only ${stock} items available.`, false);
            return;
        }

        if (newQty >= 0) {
            qtyInput.value = newQty;
            updateCart(productId, newQty);
        }
    }

    async function removeItem(productId) {
        showLoader();
        const formData = new FormData();
        formData.append('action', 'remove_from_cart');
        formData.append('product_id', productId);
        
        try {
            const response = await fetch('cart.php', { method: 'POST', body: formData });
            const result = await response.json();
            if(result.status === 'success') {
                document.getElementById(`cart-item-${productId}`).style.transition = 'opacity 0.5s';
                document.getElementById(`cart-item-${productId}`).style.opacity = '0';
                setTimeout(() => {
                    location.reload(); // Easiest way to update everything
                }, 500);
            } else {
                showToast(result.message, false);
            }
        } catch(e) {
            showToast('An error occurred.', false);
        } finally {
            hideLoader();
        }
    }
</script>

<?php require_once 'common/bottom.php'; ?>