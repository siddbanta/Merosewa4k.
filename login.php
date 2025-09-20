<?php
require_once 'common/config.php';

// If user is already logged in, redirect to home
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$response = ['status' => 'error', 'message' => 'An unknown error occurred.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    if ($_POST['action'] === 'login') {
        $email = sanitize_input($_POST['email']);
        $password = $_POST['password'];

        if (empty($email) || empty($password)) {
            $response['message'] = 'All fields are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $response['message'] = 'Invalid email format.';
        } else {
            $stmt = $conn->prepare("SELECT id, password FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                if (password_verify($password, $user['password'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $response = ['status' => 'success', 'message' => 'Login successful!', 'redirect' => 'index.php'];
                } else {
                    $response['message'] = 'Incorrect email or password.';
                }
            } else {
                $response['message'] = 'Incorrect email or password.';
            }
            $stmt->close();
        }
    } elseif ($_POST['action'] === 'signup') {
        $name = sanitize_input($_POST['name']);
        $phone = sanitize_input($_POST['phone']);
        $email = sanitize_input($_POST['email']);
        $password = $_POST['password'];
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        if (empty($name) || empty($phone) || empty($email) || empty($password)) {
            $response['message'] = 'All fields are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $response['message'] = 'Invalid email format.';
        } elseif (strlen($password) < 6) {
            $response['message'] = 'Password must be at least 6 characters long.';
        } else {
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $response['message'] = 'An account with this email already exists.';
            } else {
                $stmt = $conn->prepare("INSERT INTO users (name, phone, email, password) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssss", $name, $phone, $email, $hashed_password);
                if ($stmt->execute()) {
                    $_SESSION['user_id'] = $stmt->insert_id;
                    $response = ['status' => 'success', 'message' => 'Registration successful!', 'redirect' => 'index.php'];
                } else {
                    $response['message'] = 'Registration failed. Please try again.';
                }
            }
            $stmt->close();
        }
    }
    
    echo json_encode($response);
    $conn->close();
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Login - Quick Kart</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { -webkit-user-select: none; -moz-user-select: none; -ms-user-select: none; user-select: none; }
        .loader-dots div { animation: 1.5s loader-dots infinite ease-in-out; }
        @keyframes loader-dots { 0%, 80%, 100% { transform: scale(0); } 40% { transform: scale(1.0); } }
        .loader-dots div:nth-child(1) { animation-delay: -0.32s; }
        .loader-dots div:nth-child(2) { animation-delay: -0.16s; }
    </style>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex flex-col items-center justify-center">
        <div class="max-w-md w-full bg-white rounded-lg shadow-lg p-8">
            <h1 class="text-3xl font-bold text-center text-indigo-600 mb-4">Quick Kart</h1>
            
            <!-- Tabs -->
            <div class="flex border-b mb-6">
                <button id="login-tab" class="flex-1 py-2 text-center font-semibold text-indigo-600 border-b-2 border-indigo-600">Login</button>
                <button id="signup-tab" class="flex-1 py-2 text-center font-semibold text-gray-500">Sign Up</button>
            </div>

            <!-- Login Form -->
            <form id="login-form" class="space-y-6">
                <input type="hidden" name="action" value="login">
                <div>
                    <label for="login-email" class="text-sm font-medium text-gray-700">Email</label>
                    <input type="email" id="login-email" name="email" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div>
                    <label for="login-password" class="text-sm font-medium text-gray-700">Password</label>
                    <input type="password" id="login-password" name="password" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <button type="submit" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Login
                </button>
            </form>

            <!-- Signup Form (hidden by default) -->
            <form id="signup-form" class="hidden space-y-4">
                <input type="hidden" name="action" value="signup">
                <div>
                    <label for="signup-name" class="text-sm font-medium text-gray-700">Full Name</label>
                    <input type="text" id="signup-name" name="name" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                 <div>
                    <label for="signup-phone" class="text-sm font-medium text-gray-700">Phone Number</label>
                    <input type="tel" id="signup-phone" name="phone" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div>
                    <label for="signup-email" class="text-sm font-medium text-gray-700">Email</label>
                    <input type="email" id="signup-email" name="email" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div>
                    <label for="signup-password" class="text-sm font-medium text-gray-700">Password</label>
                    <input type="password" id="signup-password" name="password" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <button type="submit" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Create Account
                </button>
            </form>
             <!-- Loader Modal -->
            <div id="loader" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
                <div class="bg-white p-6 rounded-lg shadow-xl flex items-center space-x-4">
                    <div class="loader-dots relative flex space-x-2">
                        <div class="w-3 h-3 bg-indigo-600 rounded-full"></div>
                        <div class="w-3 h-3 bg-indigo-600 rounded-full"></div>
                        <div class="w-3 h-3 bg-indigo-600 rounded-full"></div>
                    </div>
                    <span class="text-gray-700 font-medium">Processing...</span>
                </div>
            </div>
             <!-- Toast Notification -->
            <div id="toast" class="hidden fixed top-5 right-5 bg-green-500 text-white py-2 px-4 rounded-lg shadow-lg z-50 transition-transform transform translate-x-full">
                <span id="toast-message"></span>
            </div>
        </div>
    </div>

<script>
    const loginTab = document.getElementById('login-tab');
    const signupTab = document.getElementById('signup-tab');
    const loginForm = document.getElementById('login-form');
    const signupForm = document.getElementById('signup-form');
    const loader = document.getElementById('loader');
    const toast = document.getElementById('toast');
    const toastMessage = document.getElementById('toast-message');

    loginTab.addEventListener('click', () => {
        loginForm.classList.remove('hidden');
        signupForm.classList.add('hidden');
        loginTab.classList.add('text-indigo-600', 'border-indigo-600');
        signupTab.classList.remove('text-indigo-600', 'border-indigo-600');
        signupTab.classList.add('text-gray-500');
    });

    signupTab.addEventListener('click', () => {
        signupForm.classList.remove('hidden');
        loginForm.classList.add('hidden');
        signupTab.classList.add('text-indigo-600', 'border-indigo-600');
        loginTab.classList.remove('text-indigo-600', 'border-indigo-600');
        loginTab.classList.add('text-gray-500');
    });

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
    
    async function handleFormSubmit(event) {
        event.preventDefault();
        showLoader();
        const form = event.target;
        const formData = new FormData(form);
        
        try {
            const response = await fetch('login.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            
            if (result.status === 'success') {
                showToast(result.message, true);
                setTimeout(() => {
                    window.location.href = result.redirect;
                }, 1500);
            } else {
                showToast(result.message, false);
            }
        } catch (error) {
            showToast('An error occurred. Please try again.', false);
        } finally {
            hideLoader();
        }
    }

    loginForm.addEventListener('submit', handleFormSubmit);
    signupForm.addEventListener('submit', handleFormSubmit);
</script>
</body>
</html>