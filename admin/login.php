<?php
require_once dirname(__DIR__) . '/app.php';

if (current_admin()) {
    redirect('index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid()) {
        flash('error', 'Your session expired. Please try again.');
        redirect('login.php');
    }

    $email = isset($_POST['email']) && is_string($_POST['email']) ? strtolower(trim($_POST['email'])) : '';
    $password = isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '';
    $statement = db()->prepare('SELECT id, full_name, email, password_hash FROM customers WHERE email = ? AND is_admin = 1');
    $statement->execute([$email]);
    $admin = $statement->fetch();

    if (!$admin || !password_verify($password, $admin['password_hash'])) {
        flash('error', 'Administrator email or password is incorrect.');
        redirect('login.php');
    }

    establish_admin_session($admin);
    redirect('index.php');
}

$error = flash('error');
$notice = flash('notice');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Administrator sign in | Sari-Sari</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body class="admin-login-page">
    <main class="admin-login-shell">
        <a class="admin-login-brand" href="../index.php">
            <span class="admin-login-mark">S.S</span>
            <span>Sari-Sari <small>ADMIN</small></span>
        </a>
        <section class="admin-login-card">
            <p class="admin-login-eyebrow">SECURE WORKSPACE</p>
            <h1>Administrator sign in</h1>
            <p class="admin-login-intro">Use an administrator account to manage the store.</p>
            <?php if ($notice): ?><p class="alert success" role="status"><?= e($notice) ?></p><?php endif; ?>
            <?php if ($error): ?><p class="alert error" role="alert"><?= e($error) ?></p><?php endif; ?>
            <form class="admin-login-form" method="post" action="login.php">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <label for="admin-email">Administrator email</label>
                <input id="admin-email" type="email" name="email" autocomplete="username" maxlength="190" required>
                <label for="admin-password">Password</label>
                <input id="admin-password" type="password" name="password" autocomplete="current-password" required>
                <button type="submit">Sign in to dashboard</button>
            </form>
            <a class="admin-login-customer-link" href="../login.php">Customer sign in</a>
        </section>
        <p class="admin-login-note">Authorized administrators only</p>
    </main>
</body>
</html>
