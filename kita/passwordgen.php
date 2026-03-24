<?php
$hash = '';
if (!empty($_POST['password'])) {
    $hash = password_hash($_POST['password'], PASSWORD_BCRYPT);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>KITA Password Generator</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>body{font-family:sans-serif;}</style>
</head>
<body class="bg-slate-100 min-h-screen flex items-center justify-center">
    <div class="bg-white rounded-2xl shadow-xl border border-slate-200 p-8 w-full max-w-lg">
        <h1 class="text-xl font-bold text-slate-900 mb-2">KITA Password Hash Generator</h1>
        <p class="text-sm text-slate-500 mb-6">Generate a bcrypt hash and paste it into <code class="bg-slate-100 px-1 rounded">.env</code> as <code class="bg-slate-100 px-1 rounded">KITA_ADMIN_PASSWORD</code>.</p>
        <form method="POST" class="space-y-4">
            <input type="password" name="password" placeholder="Enter new password" required class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500">
            <button type="submit" class="w-full py-3 bg-blue-700 hover:bg-blue-800 text-white font-bold rounded-xl">Generate Hash</button>
        </form>
        <?php if ($hash): ?>
            <div class="mt-6 p-4 bg-slate-50 border border-slate-200 rounded-xl">
                <p class="text-xs font-semibold text-slate-600 mb-2">Hash (copy this into .env):</p>
                <code class="text-xs text-green-700 break-all"><?php echo htmlspecialchars($hash); ?></code>
            </div>
        <?php endif; ?>
        <p class="text-xs text-slate-400 mt-4 text-center">Delete or restrict access to this file after use.</p>
    </div>
</body>
</html>
