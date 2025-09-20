<?php 
require_once 'common/header.php'; 

// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Fetch user name for greeting
$user_id = $_SESSION['user_id'];
$user_res = $conn->query("SELECT name FROM users WHERE id = $user_id");
$user = $user_res->fetch_assoc();
$user_name = explode(' ', $user['name'])[0]; // Get first name

// Fetch categories
$categories_res = $conn->query("SELECT * FROM categories ORDER BY name ASC LIMIT 8");

// Fetch featured products
$products_res = $conn->query("SELECT p.id, p.name, p.price, p.image FROM products p ORDER BY p.created_at DESC LIMIT 10");
?>

<!-- Top Header -->
<header class="sticky top-0 bg-white shadow-sm z-10 p-4 flex justify-between items-center">
    <button id="menu-button" class="text-gray-600 text-xl">
        <i class="fas fa-bars"></i>
    </button>
    <h1 class="text-xl font-bold text-indigo-600">Quick Kart</h1>
    <a href="cart.php" class="text-gray-600 text-xl relative">
        <i class="fas fa-shopping-cart"></i>
        <?php 
        $cart_count = isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0;
        if ($cart_count > 0): ?>
        <span class="absolute -top-2 -right-2 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
            <?= $cart_count ?>
        </span>
        <?php endif; ?>
    </a>
</header>

<div class="p-4 space-y-6">
    <!-- Welcome Message & Search -->
    <div>
        <h2 class="text-2xl font-bold text-gray-800">Hi, <?= htmlspecialchars($user_name) ?>!</h2>
        <p class="text-gray-500">What are you looking for today?</p>
        <div class="mt-4 relative">
            <input type="text" placeholder="Search for products..." class="w-full bg-gray-100 border-2 border-gray-200 rounded-full py-2 pl-10 pr-4 focus:outline-none focus:bg-white focus:border-indigo-500">
            <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
        </div>
    </div>

    <!-- Categories -->
    <div>
        <h3 class="text-lg font-semibold text-gray-700 mb-3">Categories</h3>
        <div class="flex space-x-4 overflow-x-auto pb-2 no-scrollbar">
            <?php while ($cat = $categories_res->fetch_assoc()): ?>
            <a href="product.php?cat_id=<?= $cat['id'] ?>" class="flex-shrink-0 flex flex-col items-center space-y-2 w-20">
                <div class="w-16 h-16 bg-indigo-100 rounded-full flex items-center justify-center">
                    <img src="uploads/categories/<?= htmlspecialchars($cat['image']) ?>" alt="<?= htmlspecialchars($cat['name']) ?>" class="w-12 h-12 object-contain">
                </div>
                <span class="text-xs text-center text-gray-600 font-medium"><?= htmlspecialchars($cat['name']) ?></span>
            </a>
            <?php endwhile; ?>
        </div>
    </div>

    <!-- Featured Products -->
    <div>
        <h3 class="text-lg font-semibold text-gray-700 mb-3">Featured Products</h3>
        <div class="grid grid-cols-2 gap-4">
            <?php while($product = $products_res->fetch_assoc()): ?>
            <div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden">
                <a href="product_detail.php?id=<?= $product['id'] ?>">
                    <img src="uploads/products/<?= htmlspecialchars($product['image']) ?>" alt="<?= htmlspecialchars($product['name']) ?>" class="w-full h-32 object-cover">
                </a>
                <div class="p-3">
                    <h4 class="text-sm font-semibold text-gray-800 truncate"><?= htmlspecialchars($product['name']) ?></h4>
                    <p class="text-lg font-bold text-indigo-600 mt-1">₹<?= number_format($product['price']) ?></p>
                    <button onclick="addToCart(<?= $product['id'] ?>)" class="w-full mt-2 bg-indigo-500 text-white text-xs font-bold py-2 rounded-lg hover:bg-indigo-600 transition-colors">
                        Add to Cart
                    </button>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
</div>

<script>
async function addToCart(productId) {
    showLoader();
    const formData = new FormData();
    formData.append('action', 'add_to_cart');
    formData.append('product_id', productId);
    formData.append('quantity', 1);

    try {
        const response = await fetch('cart.php', { method: 'POST', body: formData });
        const result = await response.json();
        if (result.status === 'success') {
            showToast('Product added to cart!');
            // Update cart count in header and bottom nav
            document.querySelectorAll('.fa-shopping-cart').forEach(icon => {
                let countSpan = icon.nextElementSibling;
                if (!countSpan || !countSpan.classList.contains('absolute')) {
                     // Create new span for header
                    if (icon.parentElement.href.includes('cart.php')) {
                        countSpan = document.createElement('span');
                        countSpan.className = 'absolute -top-2 -right-2 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center';
                        icon.parentElement.appendChild(countSpan);
                    } else { // Create new span for bottom nav
                        countSpan = document.createElement('span');
                        countSpan.className = 'absolute top-0 right-6 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center';
                         icon.parentElement.appendChild(countSpan);
                    }
                }
                countSpan.textContent = result.cart_count;
            });
        } else {
            showToast(result.message, false);
        }
    } catch (error) {
        showToast('An error occurred.', false);
    } finally {
        hideLoader();
    }
}
</script>

<?php require_once 'common/bottom.php'; ?>