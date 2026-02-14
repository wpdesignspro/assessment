<?php
require_once 'config.php';

// If already logged in, redirect to appropriate dashboard
if (isLoggedIn()) {
    if (isAdmin()) {
        header('Location: admin_dashboard.php');
    } else {
        header('Location: review_dashboard.php');
    }
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password';
    } else {
        // Check admin credentials
        if ($username === ADMIN_USERNAME && password_verify($password, ADMIN_PASSWORD)) {
            $_SESSION['logged_in'] = true;
            $_SESSION['user_type'] = 'admin';
            $_SESSION['username'] = $username;
            $_SESSION['login_time'] = time();
            
            logActivity('admin', $username, 'LOGIN', 'Admin logged in successfully');
            
            header('Location: admin_dashboard.php');
            exit();
        }
        // Check review credentials
        elseif ($username === REVIEW_USERNAME && password_verify($password, REVIEW_PASSWORD)) {
            $_SESSION['logged_in'] = true;
            $_SESSION['user_type'] = 'review';
            $_SESSION['username'] = $username;
            $_SESSION['login_time'] = time();
            
            logActivity('review', $username, 'LOGIN', 'Reviewer logged in successfully');
            
            header('Location: review_dashboard.php');
            exit();
        } else {
            $error = 'Invalid username or password';
            logActivity('unknown', $username, 'FAILED_LOGIN', 'Failed login attempt');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - ICT Assessment Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .bg-grid-pattern { 
            background-image: radial-gradient(circle at 2px 2px, rgba(0,101,212,0.05) 1px, transparent 0); 
            background-size: 40px 40px; 
        }
    </style>
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center">
    <div class="absolute inset-0 bg-grid-pattern"></div>
    
    <div class="relative z-10 w-full max-w-md px-6">
        <!-- Logo -->
        <div class="text-center mb-8">
            <div class="inline-block">
                <img src="https://i0.wp.com/thenigeriaeducationnews.com/wp-content/uploads/2025/03/TETFUND.jpg?fit=1053%2C343&ssl=1" alt="TETFUND" class="h-16 mx-auto mb-4">
            </div>
            <h1 class="text-2xl font-bold text-slate-900 mb-2">ICT Assessment Portal</h1>
            <p class="text-slate-600 text-sm">Dashboard Login</p>
        </div>

        <!-- Login Card -->
        <div class="bg-white rounded-2xl shadow-xl border border-slate-200 p-8">
            <?php if ($error): ?>
                <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                    <p class="text-red-700 text-sm font-medium"><?php echo htmlspecialchars($error); ?></p>
                </div>
            <?php endif; ?>

            <form method="POST" action="" class="space-y-6">
                <div>
                    <label for="username" class="block text-sm font-semibold text-slate-700 mb-2">Username</label>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        required
                        class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                        placeholder="Enter your username"
                        autocomplete="username"
                    >
                </div>

                <div>
                    <label for="password" class="block text-sm font-semibold text-slate-700 mb-2">Password</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        required
                        class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                        placeholder="Enter your password"
                        autocomplete="current-password"
                    >
                </div>

                <button 
                    type="submit" 
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-xl transition-colors shadow-lg shadow-blue-200"
                >
                    Sign In
                </button>
            </form>
        </div>

        <p class="text-center text-slate-500 text-xs mt-6">
            © 2026 ICT Infrastructure Assessment Portal
        </p>
    </div>
</body>
</html>