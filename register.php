<?php
require __DIR__ . '/app.php';

if (current_customer()) {
    redirect('account.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid()) {
        flash('error', 'Your session expired. Please try again.');
        redirect('register.php');
    }

    $name = isset($_POST['name']) && is_string($_POST['name']) ? trim($_POST['name']) : '';
    $contactNumber = isset($_POST['contact_number']) && is_string($_POST['contact_number'])
        ? trim($_POST['contact_number'])
        : '';
    $email = isset($_POST['email']) && is_string($_POST['email']) ? strtolower(trim($_POST['email'])) : '';
    $password = isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '';

    if ($name === '' || strlen($name) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
        flash('error', 'Enter your name and a valid email address.');
        redirect('register.php');
    }
    if (!valid_contact_number($contactNumber)) {
        flash('error', 'Enter a valid contact number using 7 to 15 digits.');
        redirect('register.php');
    }
    if (strlen($password) < 10 || strlen($password) > 72) {
        flash('error', 'Choose a password between 10 and 72 characters.');
        redirect('register.php');
    }

    try {
        $statement = db()->prepare('INSERT INTO customers (full_name, email, contact_number, password_hash) VALUES (?, ?, ?, ?)');
        $statement->execute([$name, $email, $contactNumber, password_hash($password, PASSWORD_DEFAULT)]);
    } catch (PDOException $exception) {
        if (($exception->errorInfo[1] ?? null) !== 1062) {
            throw $exception;
        }
        flash('error', 'An account with that email already exists. Please sign in instead.');
        redirect('register.php');
    }

    establish_customer_session(['id' => db()->lastInsertId()]);
    flash('notice', 'Your account is ready. Welcome to Corner Shop!');
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
    <title>Create account | Corner Shop</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php require __DIR__ . '/site_header.php'; ?>
<main class="page-main auth-main">
    <section class="auth-card">
        <p class="eyebrow">JOIN THE SHOP</p>
        <h1 class="page-title">Create account</h1>
        <p class="auth-intro">A quick sign-up keeps your orders together in one place.</p>
        <?php if ($notice): ?><p class="alert success" role="status"><?= e($notice) ?></p><?php endif; ?>
        <?php if ($error): ?><p class="alert error" role="alert"><?= e($error) ?></p><?php endif; ?>
        <form class="auth-form" method="post" action="register.php">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <label for="name">Full name</label>
            <input id="name" name="name" autocomplete="name" maxlength="120" required>
            <label for="contact-number">Contact number</label>
            <input id="contact-number" type="number" name="contact_number" autocomplete="tel" inputmode="tel" maxlength="11" required>
            <small class="field-hint">Use 7 to 15 digits. You may include a country code, spaces, or hyphens.</small>
            <label for="email">Email address</label>
            <input id="email" type="email" name="email" autocomplete="email" maxlength="190" required>
            <label for="password">Password</label>
            <input id="password" type="password" name="password" autocomplete="new-password" minlength="10" maxlength="72" required>
            <small class="field-hint">Use at least 10 characters; passwords are limited to 72 bytes.</small>
            <button class="button checkout-button" type="submit">Create account</button>
        </form>
        <p class="auth-switch">Already have an account? <a href="login.php">Sign in</a></p>
    </section>
</main>
<?php require __DIR__ . '/site_footer.php'; ?>
</body>
</html>
