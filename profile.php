<?php
require_once 'common/config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$user_id = $_SESSION['user_id'];

// --- LOGOUT ACTION ---
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header("Location: login.php");
    exit();
}

// --- AJAX HANDLER ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $response = ['status' => 'error', 'message' => 'An unknown error occurred.'];

    if ($_POST['action'] === 'update_profile') {
        $name = sanitize_input($_POST['name']);
        $phone = sanitize_input($_POST['phone']);
        $address = sanitize_input($_POST['address']);
        
        if (empty($name) || empty($phone)) {
            $response['message'] = 'Name and Phone cannot be empty.';
        } else {
            $stmt = $conn->prepare("UPDATE users SET name = ?, phone = ?, address = ? WHERE id = ?");
            $stmt->bind_param("sssi", $name, $phone, $address, $user_id);
            if ($stmt->execute()) {
                $response = ['status' => 'success', 'message' => 'Profile updated successfully!'];
            } else {
                $response['message'] = 'Failed to update profile.';
            }
            $stmt->close();
        }
    } elseif ($_POST['action'] === 'change_password') {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        
        if (empty($current_password) || empty($new_password)) {
            $response['message'] = 'All password fields are required.';
        } elseif (strlen($new_password) < 6) {
            $response['message'] = 'New password must be at least 6 characters long.';
        } else {
            $res = $conn->query("SELECT password FROM users WHERE id = $user_id");
            $user = $res->fetch_assoc();
            if (password_verify($current_password, $user['password'])) {
                $hashed_new_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->bind_param("si", $hashed_new_password, $user_id);
                if ($stmt->execute()) {
                    $response = ['status' => 'success', 'message' => 'Password changed successfully!'];
                } else {
                    $response['message'] = 'Failed to change password.';
                }
                $stmt->close();
            } else {
                $response['message'] = 'Incorrect current password.';
            }
        }
    }
    
    echo json_encode($response);
    exit();
}
// --- END AJAX HANDLER ---

$res = $conn->query("SELECT * FROM users WHERE id = $user_id");
$user = $res->fetch_assoc();

require_once 'common/header.php';
?>

<!-- Top Header -->
<header class="sticky top-0 bg-white shadow-sm z-10 p-4 flex items-center">
    <button id="menu-button" class="text-gray-600 text-xl mr-4"><i class="fas fa-bars"></i></button>
    <h1 class="text-xl font-bold text-gray-800">My Profile</h1>
</header>

<div class="p-4 space-y-8">
    <!-- User Info Card -->
    <div class="text-center">
        <div class="w-24 h-24 bg-indigo-100 text-indigo-500 rounded-full mx-auto flex items-center justify-center text-4xl font-bold">
            <?= strtoupper(substr($user['name'], 0, 1)) ?>
        </div>
        <h2 class="mt-4 text-2xl font-bold text-gray-800"><?= htmlspecialchars($user['name']) ?></h2>
        <p class="text-gray-500"><?= htmlspecialchars($user['email']) ?></p>
    </div>

    <!-- Edit Profile Form -->
    <form id="profile-form" class="bg-white p-6 rounded-lg shadow-md border space-y-4">
        <input type="hidden" name="action" value="update_profile">
        <h3 class="text-lg font-semibold text-gray-700 border-b pb-2">Edit Information</h3>
        <div>
            <label for="name" class="text-sm font-medium text-gray-700">Full Name</label>
            <input type="text" name="name" id="name" value="<?= htmlspecialchars($user['name']) ?>" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
        </div>
        <div>
            <label for="phone" class="text-sm font-medium text-gray-700">Phone</label>
            <input type="tel" name="phone" id="phone" value="<?= htmlspecialchars($user['phone']) ?>" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
        </div>
        <div>
            <label for="address" class="text-sm font-medium text-gray-700">Address</label>
            <textarea name="address" id="address" rows="3" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"><?= htmlspecialchars($user['address']) ?></textarea>
        </div>
        <button type="submit" class="w-full bg-indigo-600 text-white font-bold py-2 rounded-lg">Save Changes</button>
    </form>

    <!-- Change Password Form -->
    <form id="password-form" class="bg-white p-6 rounded-lg shadow-md border space-y-4">
        <input type="hidden" name="action" value="change_password">
        <h3 class="text-lg font-semibold text-gray-700 border-b pb-2">Change Password</h3>
        <div>
            <label for="current_password" class="text-sm font-medium text-gray-700">Current Password</label>
            <input type="password" name="current_password" id="current_password" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
        </div>
        <div>
            <label for="new_password" class="text-sm font-medium text-gray-700">New Password</label>
            <input type="password" name="new_password" id="new_password" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
        </div>
        <button type="submit" class="w-full bg-gray-700 text-white font-bold py-2 rounded-lg">Update Password</button>
    </form>
    
    <!-- Logout Button -->
    <div class="text-center">
        <a href="?action=logout" class="text-red-500 font-semibold hover:underline">
            <i class="fas fa-sign-out-alt mr-2"></i>Logout
        </a>
    </div>
</div>

<script>
    async function handleFormSubmit(event) {
        event.preventDefault();
        showLoader();
        const form = event.target;
        const formData = new FormData(form);

        try {
            const response = await fetch('profile.php', { method: 'POST', body: formData });
            const result = await response.json();
            
            if (result.status === 'success') {
                showToast(result.message);
                if(form.id === 'password-form') {
                    form.reset();
                } else {
                    // Optionally update name on page
                    document.querySelector('.text-2xl.font-bold').textContent = formData.get('name');
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
    document.getElementById('profile-form').addEventListener('submit', handleFormSubmit);
    document.getElementById('password-form').addEventListener('submit', handleFormSubmit);
</script>

<?php require_once 'common/bottom.php'; ?>