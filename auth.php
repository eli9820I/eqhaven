<?php
session_start();

// Initialize files
if (!file_exists('users.json')) {
    file_put_contents('users.json', '[]');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['action'] === 'register') {
        $users = json_decode(file_get_contents('users.json'), true);
        
        // Check if user exists
        foreach ($users as $user) {
            if ($user['email'] === $_POST['email']) {
                die("User already exists");
            }
        }
        
        // Create user
        $newUser = [
            'id' => uniqid(),
            'name' => $_POST['name'],
            'email' => $_POST['email'],
            'password' => password_hash($_POST['password'], PASSWORD_DEFAULT),
            'csc_address' => 'CSC' . bin2hex(random_bytes(10)),
            'balance' => 0,
            'usd_balance' => 0
        ];
        
        $users[] = $newUser;
        file_put_contents('users.json', json_encode($users));
        
        $_SESSION['user_id'] = $newUser['id'];
        $_SESSION['user_name'] = $newUser['name'];
        $_SESSION['csc_address'] = $newUser['csc_address'];
        
        header('Location: index.php');
        exit;
        
    } elseif ($_POST['action'] === 'login') {
        $users = json_decode(file_get_contents('users.json'), true);
        
        foreach ($users as $user) {
            if ($user['email'] === $_POST['email'] && password_verify($_POST['password'], $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['csc_address'] = $user['csc_address'];
                header('Location: index.php');
                exit;
            }
        }
        
        die("Invalid login");
    }
}

// If already logged in, redirect
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login - CSC</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-box">
            <h1>Centennial Spartan Coin</h1>
            
            <div class="tabs">
                <button onclick="showTab('login')" class="active">Login</button>
                <button onclick="showTab('register')">Register</button>
            </div>
            
            <!-- Login Form -->
            <form id="loginForm" method="POST" style="display: block;">
                <input type="hidden" name="action" value="login">
                <input type="email" name="email" placeholder="Email" required>
                <input type="password" name="password" placeholder="Password" required>
                <button type="submit">Login</button>
            </form>
            
            <!-- Register Form -->
            <form id="registerForm" method="POST" style="display: none;">
                <input type="hidden" name="action" value="register">
                <input type="text" name="name" placeholder="Full Name" required>
                <input type="email" name="email" placeholder="Email" required>
                <input type="password" name="password" placeholder="Password" required>
                <button type="submit">Register</button>
                <p class="note">Upon registration, you'll receive a unique CSC address</p>
            </form>
        </div>
    </div>
    
    <script>
        function showTab(tab) {
            document.querySelectorAll('.tabs button').forEach(b => b.classList.remove('active'));
            event.target.classList.add('active');
            
            document.getElementById('loginForm').style.display = tab === 'login' ? 'block' : 'none';
            document.getElementById('registerForm').style.display = tab === 'register' ? 'block' : 'none';
        }
    </script>
</body>
</html>