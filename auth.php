<?php
session_start();

// Initialize files
if (!file_exists('users.json')) {
    file_put_contents('users.json', '[]');
}

if (!file_exists('config.json')) {
    $config = [
        'price' => 0.02,
        'supply' => 100000,
        'stripe_public_key' => 'pk_test_your_key_here',
        'stripe_secret_key' => 'sk_test_your_key_here'
    ];
    file_put_contents('config.json', json_encode($config));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $users = json_decode(file_get_contents('users.json'), true);
    
    if ($_POST['action'] === 'register') {
        // Check if user exists
        foreach ($users as $user) {
            if ($user['email'] === $_POST['email']) {
                $error = "User already exists";
                break;
            }
        }
        
        if (!isset($error)) {
            // Create user
            $newUser = [
                'id' => uniqid(),
                'firstname' => $_POST['firstname'],
                'lastname' => $_POST['lastname'],
                'email' => $_POST['email'],
                'username' => $_POST['username'],
                'password' => password_hash($_POST['password'], PASSWORD_DEFAULT),
                'csc_address' => 'CSC' . bin2hex(random_bytes(10)),
                'balance' => 0,
                'usd_balance' => 0,
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $users[] = $newUser;
            file_put_contents('users.json', json_encode($users));
            
            $_SESSION['user_id'] = $newUser['id'];
            $_SESSION['user_name'] = $newUser['firstname'] . ' ' . $newUser['lastname'];
            $_SESSION['username'] = $newUser['username'];
            $_SESSION['csc_address'] = $newUser['csc_address'];
            $_SESSION['logged_in'] = true;
            
            header('Location: index.php');
            exit;
        }
        
    } elseif ($_POST['action'] === 'login') {
        foreach ($users as $user) {
            if ($user['username'] === $_POST['username'] && password_verify($_POST['password'], $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['firstname'] . ' ' . $user['lastname'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['csc_address'] = $user['csc_address'];
                $_SESSION['logged_in'] = true;
                header('Location: index.php');
                exit;
            }
        }
        $error = "Invalid username or password";
    }
}

// If already logged in, redirect
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CSC Platform - Authentication</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --gradient-start: #0f172a;
            --gradient-mid: #1e293b;
            --gradient-end: #0f172a;
            --accent-primary: #3b82f6;
            --accent-secondary: #8b5cf6;
            --accent-gold: #f59e0b;
            --bg-primary: #0f172a;
            --bg-secondary: #1e293b;
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .min-h-screen {
            min-height: 100vh;
        }

        .flex {
            display: flex;
        }

        .items-center {
            align-items: center;
        }

        .justify-center {
            justify-content: center;
        }

        .p-4 {
            padding: 1rem;
        }

        .w-full {
            width: 100%;
        }

        .max-w-md {
            max-width: 28rem;
        }

        .text-center {
            text-align: center;
        }

        .mb-6 {
            margin-bottom: 1.5rem;
        }

        .mb-4 {
            margin-bottom: 1rem;
        }

        .mb-2 {
            margin-bottom: 0.5rem;
        }

        .mt-2 {
            margin-top: 0.5rem;
        }

        .mt-4 {
            margin-top: 1rem;
        }

        .w-16 {
            width: 4rem;
        }

        .h-16 {
            height: 4rem;
        }

        .rounded-2xl {
            border-radius: 1rem;
        }

        .text-white {
            color: #fff;
        }

        .text-slate-400 {
            color: #94a3b8;
        }

        .text-blue-400 {
            color: #60a5fa;
        }

        .text-red-400 {
            color: #f87171;
        }

        .font-bold {
            font-weight: 700;
        }

        .text-3xl {
            font-size: 1.875rem;
            line-height: 2.25rem;
        }

        .text-sm {
            font-size: 0.875rem;
        }

        .bg-slate-800\/50 {
            background-color: rgba(30, 41, 59, 0.5);
        }

        .backdrop-blur-xl {
            backdrop-filter: blur(24px);
        }

        .border-slate-700\/50 {
            border-color: rgba(51, 65, 85, 0.5);
        }

        .border {
            border-width: 1px;
        }

        .shadow-2xl {
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }

        .logo-icon {
            background: linear-gradient(to bottom right, #3b82f6, #8b5cf6);
            box-shadow: 0 25px 50px -12px rgba(59, 130, 246, 0.3);
            margin: 0 auto 1rem;
        }

        .card {
            background: rgba(30, 41, 59, 0.5);
            backdrop-filter: blur(24px);
            border-radius: 1.5rem;
            border: 1px solid rgba(51, 65, 85, 0.5);
            padding: 2rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }

        .tab-buttons {
            display: flex;
            background: rgba(51, 65, 85, 0.5);
            border-radius: 0.75rem;
            padding: 0.25rem;
            margin-bottom: 2rem;
        }

        .tab-button {
            flex: 1;
            padding: 0.75rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            background: transparent;
            color: #94a3b8;
        }

        .tab-button.active {
            background: #3b82f6;
            color: white;
            box-shadow: 0 10px 15px -3px rgba(59, 130, 246, 0.3);
        }

        .tab-button:hover:not(.active) {
            color: white;
        }

        .space-y-4 > * + * {
            margin-top: 1rem;
        }

        .space-y-5 > * + * {
            margin-top: 1.25rem;
        }

        label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: #cbd5e1;
            margin-bottom: 0.5rem;
        }

        input {
            width: 100%;
            height: 3rem;
            padding: 0 0.75rem;
            background: rgba(51, 65, 85, 0.5);
            border: 1px solid #475569;
            border-radius: 0.75rem;
            color: white;
            font-size: 0.875rem;
            transition: all 0.3s;
            outline: none;
        }

        input::placeholder {
            color: #64748b;
        }

        input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .submit-button {
            width: 100%;
            height: 3rem;
            background: linear-gradient(to right, #3b82f6, #8b5cf6);
            border: none;
            border-radius: 0.75rem;
            color: white;
            font-weight: 600;
            font-size: 0.875rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            box-shadow: 0 10px 15px -3px rgba(59, 130, 246, 0.25);
            transition: all 0.3s;
        }

        .submit-button:hover {
            background: linear-gradient(to right, #2563eb, #7c3aed);
            transform: translateY(-2px);
            box-shadow: 0 15px 25px -5px rgba(59, 130, 246, 0.4);
        }

        .submit-button:active {
            transform: translateY(0);
        }

        .error-message {
            background: rgba(248, 113, 113, 0.1);
            border: 1px solid rgba(248, 113, 113, 0.3);
            color: #f87171;
            padding: 0.75rem;
            border-radius: 0.75rem;
            text-align: center;
            margin-bottom: 1rem;
            font-size: 0.875rem;
        }

        .promo-card {
            margin-top: 2rem;
            padding: 1rem;
            border-radius: 1rem;
            background: linear-gradient(to right, rgba(168, 85, 247, 0.1), rgba(236, 72, 153, 0.1));
            border: 1px solid rgba(168, 85, 247, 0.2);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .promo-icon {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 0.75rem;
            background: linear-gradient(to bottom right, #a855f7, #ec4899);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .promo-text {
            flex: 1;
        }

        .promo-title {
            font-size: 0.875rem;
            font-weight: 600;
            color: white;
            margin-bottom: 0.125rem;
        }

        .promo-subtitle {
            font-size: 0.75rem;
            color: #94a3b8;
        }

        .hidden {
            display: none;
        }

        svg {
            flex-shrink: 0;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
    </style>
</head>
<body>
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="w-full max-w-md">
            <!-- Header -->
            <div class="text-center mb-6">
                <div class="w-16 h-16 rounded-2xl logo-icon flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white">
                        <path d="M12 4v16m8-8H4"></path>
                    </svg>
                </div>
                <h1 class="text-3xl font-bold text-white mb-2">
                    CSC<span class="text-blue-400">Platform</span>
                </h1>
                <p class="text-slate-400">Centennial Spartan Coin Exchange</p>
            </div>

            <!-- Card -->
            <div class="card">
                <?php if (isset($error)): ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($error); ?>
                </div>
                <?php endif; ?>

                <!-- Tabs -->
                <div class="tab-buttons">
                    <button class="tab-button active" id="loginTab" onclick="switchTab('login')">
                        Sign In
                    </button>
                    <button class="tab-button" id="registerTab" onclick="switchTab('register')">
                        Create Account
                    </button>
                </div>

                <!-- Login Form -->
                <form class="space-y-4" id="loginForm" method="POST">
                    <input type="hidden" name="action" value="login">
                    <div>
                        <label>Username</label>
                        <input type="text" name="username" placeholder="Enter your username" required>
                    </div>
                    <div>
                        <label>Password</label>
                        <input type="password" name="password" placeholder="Enter your password" required>
                    </div>
                    <button type="submit" class="submit-button">
                        Sign In
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M5 12h14"></path>
                            <path d="m12 5 7 7-7 7"></path>
                        </svg>
                    </button>
                </form>

                <!-- Register Form -->
                <form class="hidden space-y-4" id="registerForm" method="POST">
                    <input type="hidden" name="action" value="register">
                    
                    <div class="form-row">
                        <div>
                            <label>First Name</label>
                            <input type="text" name="firstname" placeholder="First name" required>
                        </div>
                        <div>
                            <label>Last Name</label>
                            <input type="text" name="lastname" placeholder="Last name" required>
                        </div>
                    </div>
                    
                    <div>
                        <label>Email</label>
                        <input type="email" name="email" placeholder="Enter your email" required>
                    </div>
                    
                    <div>
                        <label>Username</label>
                        <input type="text" name="username" placeholder="Choose a username" required>
                    </div>
                    
                    <div>
                        <label>Password</label>
                        <input type="password" name="password" placeholder="Create a password" required>
                    </div>
                    
                    <button type="submit" class="submit-button">
                        Create Account
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M5 12h14"></path>
                            <path d="m12 5 7 7-7 7"></path>
                        </svg>
                    </button>
                    
                    <p class="text-slate-400 text-sm text-center mt-4">
                        Upon registration, you'll receive a unique CSC address
                    </p>
                </form>

                <!-- Promo Card -->
                <div class="promo-card">
                    <div class="promo-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white">
                            <path d="M11.017 2.814a1 1 0 0 1 1.966 0l1.051 5.558a2 2 0 0 0 1.594 1.594l5.558 1.051a1 1 0 0 1 0 1.966l-5.558 1.051a2 2 0 0 0-1.594 1.594l-1.051 5.558a1 1 0 0 1-1.966 0l-1.051-5.558a2 2 0 0 0-1.594-1.594l-5.558-1.051a1 1 0 0 1 0-1.966l5.558-1.051a2 2 0 0 0 1.594-1.594z"></path>
                            <path d="M20 2v4"></path>
                            <path d="M22 4h-4"></path>
                            <circle cx="4" cy="20" r="2"></circle>
                        </svg>
                    </div>
                    <div class="promo-text">
                        <p class="promo-title">Starting Price: $0.02!</p>
                        <p class="promo-subtitle">Limited supply of 100,000 CSC tokens</p>
                    </div>
                </div>
            </div>

            <p class="text-slate-400 text-sm text-center mt-4">Secured by SHA-256 Blockchain</p>
        </div>
    </div>

    <script>
        function switchTab(tab) {
            const loginTab = document.getElementById('loginTab');
            const registerTab = document.getElementById('registerTab');
            const loginForm = document.getElementById('loginForm');
            const registerForm = document.getElementById('registerForm');

            if (tab === 'login') {
                loginTab.classList.add('active');
                registerTab.classList.remove('active');
                loginForm.classList.remove('hidden');
                registerForm.classList.add('hidden');
            } else {
                registerTab.classList.add('active');
                loginTab.classList.remove('active');
                registerForm.classList.remove('hidden');
                loginForm.classList.add('hidden');
            }
        }
    </script>
</body>
</html>