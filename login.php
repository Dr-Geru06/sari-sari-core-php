<?php
require __DIR__ . '/app.php';

if (current_customer()) {
    redirect('account.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid()) {
        flash('error', 'Your session expired. Please try again.');
        redirect('login.php');
    }

    $email = isset($_POST['email']) && is_string($_POST['email']) ? strtolower(trim($_POST['email'])) : '';
    $password = isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '';
    $statement = db()->prepare('SELECT id, full_name, email, password_hash FROM customers WHERE email = ?');
    $statement->execute([$email]);
    $customer = $statement->fetch();

    if (!$customer || !password_verify($password, $customer['password_hash'])) {
        flash('error', 'Email or password is incorrect.');
        redirect('login.php');
    }

    establish_customer_session($customer);
    flash('notice', 'Welcome back, ' . $customer['full_name'] . '!');
    redirect_after_login();
}

$error = flash('error');
$notice = flash('notice');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in | 
        Sari-Sari Store
    </title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php require __DIR__ . '/site_header.php'; ?>
<main class="page-main auth-main">
    <section class="auth-card">
        <p class="eyebrow">WELCOME BACK</p>
        <h1 class="page-title">Sign in</h1>
        <p class="auth-intro">Sign in to finish your order and see your past purchases.</p>
        <?php if ($notice): ?><p class="alert success" role="status"><?= e($notice) ?></p><?php endif; ?>
        <?php if ($error): ?><p class="alert error" role="alert"><?= e($error) ?></p><?php endif; ?>
        <form class="auth-form" method="post" action="login.php">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <label for="email">Email address</label>
            <input id="email" type="email" name="email" autocomplete="email" maxlength="190" required>
            <label for="password">Password</label>
            <input id="password" type="password" name="password" autocomplete="current-password" required>
            <button class="button checkout-button" type="submit">Sign in</button>
        </form>
        <p class="auth-switch">New to Sari-Sari Store? <a href="register.php">Create an account</a></p>
    </section>
</main>
<?php require __DIR__ . '/site_footer.php'; ?>
</body>
</html>
