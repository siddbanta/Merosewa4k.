<?php 
require_once 'common/header.php'; 

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$cat_id = isset($_GET['cat_id']) ? (int)$_GET['cat_id'] : 0;
if ($cat_id === 0) {
    // Redirect or show all products if no category is selected
    header("Location: index.php"); 
    exit();
}

// Fetch Category Name
$cat_res = $conn->query("SELECT name FROM categories WHERE id = $cat_id");
$category = $cat_res->fetch_assoc();
$cat_name = $category ? $category['name'] : 'Products';

// Fetch products for the category
$products_res = $conn->query("SELECT * FROM products WHERE cat_id = $cat_id ORDER BY created_at DESC");
?>

<!-- Top Header -->
<header class="sticky top-0 bg-white shadow-sm z-10 p-4 flex items-center">
    <a href="index.php" class="text-gray-600 text-xl mr-4"><i class="fas fa-arrow-left"></i></a>
    <h1 class="text-xl font-bold text-gray-800"><?= htmlspecialchars($cat_name) ?></h1>
</header>

<div class="p-4">
    <!-- Filters (Example) -->
    <div class="flex justify-between items-center mb-4">
        <span class="font-semibold text-gray-700"><?= $products_res->num_rows ?> Items</span>
        <div class="flex space-x-2">
            <button class="px-3 py-1 text-sm border rounded-full">Sort by: New</button>
            <button class="px-3 py-1 text-sm border rounded-full">Filter</button>
        </div>
    </div>

    <!-- Product Grid -->
    <div class="grid grid-cols-2 gap-4">
        <?php if ($products_res->num_rows > 0): ?>
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
        <?php else: ?>
            <p class="col-span-2 text-center text-gray-500 mt-10">No products found in this category.</p>
        <?php endif; ?>
    </div>
</div>

<script>
// Re-using the addToCart function from the homepage
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
             // Quick and dirty update of cart count in bottom nav
            const cartCountSpan = document.querySelector('footer a[href="cart.php"] span.absolute');
            if (cartCountSpan) {
                cartCountSpan.textContent = result.cart_count;
            } else {
                 const cartLink = document.querySelector('footer a[href="cart.php"]');
                 const newSpan = document.createElement('span');
                 newSpan.className = 'absolute top-0 right-6 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center';
                 newSpan.textContent = result.cart_count;
                 cartLink.appendChild(newSpan);
            }
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