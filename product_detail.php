<?php 
require_once 'common/header.php'; 

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($product_id === 0) {
    header("Location: index.php"); 
    exit();
}

// Fetch product details
$stmt = $conn->prepare("SELECT p.*, c.name as category_name FROM products p JOIN categories c ON p.cat_id = c.id WHERE p.id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    echo "Product not found."; // Or a more graceful error page
    exit();
}
$product = $result->fetch_assoc();
$stmt->close();
?>

<!-- Top Header with Back Button -->
<header class="sticky top-0 bg-white shadow-sm z-10 p-4 flex items-center">
    <a href="javascript:history.back()" class="text-gray-600 text-xl mr-4"><i class="fas fa-arrow-left"></i></a>
    <h1 class="text-xl font-bold text-gray-800 truncate"><?= htmlspecialchars($product['name']) ?></h1>
</header>

<div>
    <!-- Product Image Slider (Basic) -->
    <div class="bg-gray-200">
        <img src="uploads/products/<?= htmlspecialchars($product['image']) ?>" alt="<?= htmlspecialchars($product['name']) ?>" class="w-full h-80 object-contain">
    </div>

    <!-- Product Info -->
    <div class="p-4 space-y-4">
        <div>
            <span class="text-sm text-indigo-500 font-semibold"><?= htmlspecialchars($product['category_name']) ?></span>
            <h2 class="text-2xl font-bold text-gray-800"><?= htmlspecialchars($product['name']) ?></h2>
        </div>
        
        <div class="flex justify-between items-center">
            <p class="text-3xl font-bold text-indigo-600">₹<?= number_format($product['price']) ?></p>
            <span class="px-3 py-1 text-sm font-semibold rounded-full <?= $product['stock'] > 0 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                <?= $product['stock'] > 0 ? 'In Stock' : 'Out of Stock' ?>
            </span>
        </div>

        <!-- Quantity Selector -->
        <div class="flex items-center space-x-4">
            <label class="font-semibold text-gray-700">Quantity:</label>
            <div class="flex items-center border border-gray-300 rounded-md">
                <button id="qty-minus" class="px-3 py-1 text-lg font-bold">-</button>
                <input id="quantity" type="text" value="1" readonly class="w-12 text-center border-l border-r">
                <button id="qty-plus" class="px-3 py-1 text-lg font-bold">+</button>
            </div>
        </div>

        <!-- Description -->
        <div>
            <h3 class="text-lg font-semibold border-b pb-2 mb-2">Description</h3>
            <p class="text-gray-600 leading-relaxed">
                <?= nl2br(htmlspecialchars($product['description'])) ?>
            </p>
        </div>
    </div>
</div>

<!-- Floating Add to Cart Button -->
<div class="sticky bottom-0 left-0 right-0 p-4 bg-white border-t">
    <button id="add-to-cart-btn" class="w-full bg-indigo-600 text-white font-bold py-3 rounded-lg shadow-lg hover:bg-indigo-700 transition-colors disabled:bg-gray-400" <?= $product['stock'] <= 0 ? 'disabled' : '' ?>>
        <?= $product['stock'] > 0 ? 'Add to Cart' : 'Out of Stock' ?>
    </button>
</div>

<script>
    const qtyInput = document.getElementById('quantity');
    const qtyMinus = document.getElementById('qty-minus');
    const qtyPlus = document.getElementById('qty-plus');
    const addToCartBtn = document.getElementById('add-to-cart-btn');
    const stock = <?= $product['stock'] ?>;

    qtyMinus.addEventListener('click', () => {
        let currentQty = parseInt(qtyInput.value);
        if (currentQty > 1) {
            qtyInput.value = currentQty - 1;
        }
    });

    qtyPlus.addEventListener('click', () => {
        let currentQty = parseInt(qtyInput.value);
        if(currentQty < stock) {
            qtyInput.value = currentQty + 1;
        } else {
            showToast(`Only ${stock} items available.`, false);
        }
    });

    addToCartBtn.addEventListener('click', async () => {
        showLoader();
        const formData = new FormData();
        formData.append('action', 'add_to_cart');
        formData.append('product_id', <?= $product_id ?>);
        formData.append('quantity', qtyInput.value);

        try {
            const response = await fetch('cart.php', { method: 'POST', body: formData });
            const result = await response.json();
            if (result.status === 'success') {
                showToast('Product added to cart!');
                 // Update cart count in bottom nav
                const cartCountSpan = document.querySelector('footer a[href="cart.php"] span.absolute');
                if (cartCountSpan) {
                    cartCountSpan.textContent = result.cart_count;
                    cartCountSpan.classList.remove('hidden');
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
    });
</script>

<?php 
// NOTE: Don't include bottom.php here because we have a sticky button that acts as the footer.
// We close the tags manually.
?>
        </main>
    </div>
    <script>
        // Paste the global JS functions here again as bottom.php is not included
        document.addEventListener('contextmenu', event => event.preventDefault());
        document.addEventListener('touchstart', function(e) { if (e.touches.length > 1) { e.preventDefault(); } }, { passive: false });
        let lastTouchEnd = 0;
        document.addEventListener('touchend', function(e) {
            const now = (new Date()).getTime();
            if (now - lastTouchEnd <= 300) { e.preventDefault(); }
            lastTouchEnd = now;
        }, false);

        const loader = document.getElementById('loader');
        const toast = document.getElementById('toast');
        const toastMessage = document.getElementById('toast-message');
        function showLoader() { loader.classList.remove('hidden'); }
        function hideLoader() { loader.classList.add('hidden'); }
        function showToast(message, isSuccess = true) {
            toastMessage.innerText = message;
            toast.classList.remove('hidden', 'bg-red-500', 'bg-green-500', 'translate-x-full');
            toast.classList.add(isSuccess ? 'bg-green-500' : 'bg-red-500');
            setTimeout(() => { toast.classList.remove('translate-x-full'); }, 10);
            setTimeout(() => {
                toast.classList.add('translate-x-full');
                setTimeout(() => { toast.classList.add('hidden'); }, 300);
            }, 3000);
        }
    </script>
</body>
</html>