<?php
session_start();

// Load env & DB from parent config
require_once dirname(__DIR__) . '/config.php';

// If already logged in as KITA admin, redirect to dashboard
if (isset($_SESSION['kita_logged_in']) && $_SESSION['kita_logged_in'] === true) {
    header('Location: admin.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $kitaUsername = getenv('KITA_ADMIN_USERNAME');
    $kitaHash     = getenv('KITA_ADMIN_PASSWORD');

    if ($username === $kitaUsername && password_verify($password, $kitaHash)) {
        $_SESSION['kita_logged_in'] = true;
        $_SESSION['kita_username']  = $username;
        $_SESSION['kita_login_time']= time();
        logActivity('kita_admin', $username, 'KITA_LOGIN', 'KITA admin logged in');
        header('Location: admin.php');
        exit();
    } else {
        logActivity('kita_admin', $username, 'KITA_FAILED_LOGIN', 'Failed KITA admin login attempt');
        $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KITA Admin Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-slate-100 min-h-screen flex items-center justify-center">
    <div class="w-full max-w-md px-4">

        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-blue-700 rounded-2xl mb-4 shadow-lg">
                <span class="text-white font-extrabold text-2xl">K</span>
            </div>
            <h1 class="text-2xl font-extrabold text-slate-900">KITA Admin Portal</h1>
            <p class="text-slate-500 text-sm mt-1">Krystal IT Academy — Hub Assessment</p>
        </div>

        <div class="bg-white rounded-2xl shadow-xl border border-slate-200 p-8">
            <h2 class="text-lg font-bold text-slate-800 mb-6">Sign in to your account</h2>

            <?php if ($error): ?>
                <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg flex items-center gap-2 text-red-700 text-sm">
                    <span class="material-icons text-base">error</span>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-5">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Username</label>
                    <div class="relative">
                        <span class="material-icons absolute left-3 top-3 text-slate-400 text-lg">person</span>
                        <input
                            type="text"
                            name="username"
                            value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                            placeholder="Enter username"
                            required
                            autocomplete="username"
                            class="w-full pl-10 pr-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition text-slate-900"
                        >
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Password</label>
                    <div class="relative">
                        <span class="material-icons absolute left-3 top-3 text-slate-400 text-lg">lock</span>
                        <input
                            type="password"
                            name="password"
                            placeholder="Enter password"
                            required
                            autocomplete="current-password"
                            class="w-full pl-10 pr-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition text-slate-900"
                        >
                    </div>
                </div>

                <button type="submit" class="w-full py-3 bg-blue-700 hover:bg-blue-800 text-white font-bold rounded-xl transition flex items-center justify-center gap-2 shadow-md">
                    <span class="material-icons">login</span>
                    Sign In
                </button>
            </form>
        </div>

        <p class="text-center text-xs text-slate-400 mt-6">
            © 2026 Krystal IT Academy (KITA)
        </p>
    </div>
</body>
</html>
