<?php 
require_once 'common/header.php'; 

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$user_id = $_SESSION['user_id'];

// Fetch orders with associated product details
$sql = "
    SELECT 
        o.id as order_id, o.total_amount, o.status, o.created_at,
        oi.quantity,
        p.name as product_name, p.image as product_image
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    WHERE o.user_id = ?
    ORDER BY o.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$orders = [];
while ($row = $result->fetch_assoc()) {
    $order_id = $row['order_id'];
    if (!isset($orders[$order_id])) {
        $orders[$order_id] = [
            'total_amount' => $row['total_amount'],
            'status' => $row['status'],
            'created_at' => $row['created_at'],
            'items' => []
        ];
    }
    $orders[$order_id]['items'][] = [
        'name' => $row['product_name'],
        'image' => $row['product_image'],
        'quantity' => $row['quantity']
    ];
}
$stmt->close();

$active_orders = array_filter($orders, fn($o) => in_array($o['status'], ['Placed', 'Dispatched']));
$history_orders = array_filter($orders, fn($o) => in_array($o['status'], ['Delivered', 'Cancelled']));

function render_progress_tracker($status) {
    $stages = ['Placed', 'Dispatched', 'Delivered'];
    $current_stage_index = array_search($status, $stages);
    $is_cancelled = ($status === 'Cancelled');

    if ($is_cancelled) {
         echo '<div class="text-center py-2"><p class="text-red-500 font-bold"><i class="fas fa-times-circle mr-2"></i>Order Cancelled</p></div>';
         return;
    }
    
    echo '<div class="flex justify-between items-center px-2 pt-4">';
    foreach ($stages as $index => $stage) {
        $is_completed = ($index <= $current_stage_index);
        $icon_class = $is_completed ? 'bg-green-500 text-white' : 'bg-gray-300 text-gray-500';
        $text_class = $is_completed ? 'text-green-600' : 'text-gray-500';
        $icon = ['fas fa-box-open', 'fas fa-truck', 'fas fa-check-circle'][$index];
        
        echo '<div class="flex flex-col items-center z-10">';
        echo "<div class='w-8 h-8 rounded-full flex items-center justify-center $icon_class'><i class='$icon'></i></div>";
        echo "<p class='text-xs mt-1 font-semibold $text_class'>$stage</p>";
        echo '</div>';

        if ($index < count($stages) - 1) {
            $line_class = ($index < $current_stage_index) ? 'bg-green-500' : 'bg-gray-300';
            echo "<div class='flex-grow h-1 -mx-4 $line_class'></div>";
        }
    }
    echo '</div>';
}

?>

<!-- Top Header -->
<header class="sticky top-0 bg-white shadow-sm z-10 p-4 flex items-center">
    <a href="index.php" class="text-gray-600 text-xl mr-4"><i class="fas fa-arrow-left"></i></a>
    <h1 class="text-xl font-bold text-gray-800">My Orders</h1>
</header>

<div class="p-4">
    <!-- Tabs -->
    <div class="flex border-b mb-4">
        <button id="active-tab" class="flex-1 py-2 text-center font-semibold text-indigo-600 border-b-2 border-indigo-600">Active</button>
        <button id="history-tab" class="flex-1 py-2 text-center font-semibold text-gray-500">History</button>
    </div>

    <!-- Active Orders Container -->
    <div id="active-orders" class="space-y-4">
        <?php if (empty($active_orders)): ?>
            <p class="text-center text-gray-500 py-10">No active orders.</p>
        <?php else: ?>
            <?php foreach ($active_orders as $order_id => $order): ?>
                <div class="bg-white rounded-lg shadow-md border overflow-hidden">
                    <div class="p-4">
                        <div class="flex justify-between items-start">
                            <div>
                                <h3 class="font-bold text-gray-800">Order #<?= $order_id ?></h3>
                                <p class="text-sm text-gray-500"><?= count($order['items']) ?> item(s) • ₹<?= number_format($order['total_amount']) ?></p>
                            </div>
                            <span class="text-sm font-semibold text-blue-600"><?= $order['status'] ?></span>
                        </div>
                         <!-- Product Preview -->
                        <div class="flex items-center mt-4 border-t pt-4">
                            <img src="uploads/products/<?= htmlspecialchars($order['items'][0]['image']) ?>" class="w-12 h-12 rounded-md object-cover">
                            <div class="ml-3 flex-grow">
                                <p class="font-semibold text-sm truncate"><?= htmlspecialchars($order['items'][0]['name']) ?></p>
                                <?php if(count($order['items']) > 1): ?>
                                <p class="text-xs text-gray-500">+ <?= count($order['items']) - 1 ?> more item(s)</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 p-4 border-t">
                        <?php render_progress_tracker($order['status']); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Order History Container -->
    <div id="history-orders" class="hidden space-y-4">
        <?php if (empty($history_orders)): ?>
            <p class="text-center text-gray-500 py-10">No past orders.</p>
        <?php else: ?>
            <?php foreach ($history_orders as $order_id => $order): ?>
                <div class="bg-white rounded-lg shadow-md border overflow-hidden opacity-80">
                    <div class="p-4">
                        <div class="flex justify-between items-start">
                             <div>
                                <h3 class="font-bold text-gray-700">Order #<?= $order_id ?></h3>
                                <p class="text-sm text-gray-500"><?= date('d M Y', strtotime($order['created_at'])) ?> • ₹<?= number_format($order['total_amount']) ?></p>
                            </div>
                            <span class="text-sm font-semibold <?= $order['status'] === 'Delivered' ? 'text-green-600' : 'text-red-600' ?>">
                                <?= $order['status'] ?>
                            </span>
                        </div>
                        <div class="flex items-center mt-4 border-t pt-4">
                             <img src="uploads/products/<?= htmlspecialchars($order['items'][0]['image']) ?>" class="w-12 h-12 rounded-md object-cover">
                            <div class="ml-3 flex-grow">
                                <p class="font-semibold text-sm truncate"><?= htmlspecialchars($order['items'][0]['name']) ?></p>
                                <?php if(count($order['items']) > 1): ?>
                                <p class="text-xs text-gray-500">+ <?= count($order['items']) - 1 ?> more item(s)</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
    const activeTab = document.getElementById('active-tab');
    const historyTab = document.getElementById('history-tab');
    const activeOrders = document.getElementById('active-orders');
    const historyOrders = document.getElementById('history-orders');

    activeTab.addEventListener('click', () => {
        activeOrders.classList.remove('hidden');
        historyOrders.classList.add('hidden');
        activeTab.classList.add('text-indigo-600', 'border-indigo-600');
        historyTab.classList.remove('text-indigo-600', 'border-indigo-600');
        historyTab.classList.add('text-gray-500');
    });

     historyTab.addEventListener('click', () => {
        historyOrders.classList.remove('hidden');
        activeOrders.classList.add('hidden');
        historyTab.classList.add('text-indigo-600', 'border-indigo-600');
        activeTab.classList.remove('text-indigo-600', 'border-indigo-600');
        activeTab.classList.add('text-gray-500');
    });
</script>

<?php require_once 'common/bottom.php'; ?>