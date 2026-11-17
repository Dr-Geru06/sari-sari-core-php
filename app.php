<?php
declare(strict_types=1);

session_start();

function db(): PDO
{
    static $connection;

    if (!$connection) {
        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $name = getenv('DB_NAME') ?: 'hard_sys_shop';
        $user = getenv('DB_USER') ?: 'root';
        $password = getenv('DB_PASSWORD') ?: '';

        $connection = new PDO(
            "mysql:host={$host};dbname={$name};charset=utf8mb4",
            $user,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    }

    return $connection;
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_is_valid(): bool
{
    return isset($_POST['csrf_token'], $_SESSION['csrf_token'])
        && is_string($_POST['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }

    $value = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return is_string($value) ? $value : null;
}

function redirect(string $location): never
{
    header('Location: ' . $location);
    exit;
}

function cart(): array
{
    return isset($_SESSION['cart']) && is_array($_SESSION['cart']) ? $_SESSION['cart'] : [];
}

function cart_count(): int
{
    return array_sum(cart());
}

function cart_items(): array
{
    $cart = cart();
    if (!$cart) {
        return [];
    }

    $ids = array_map('intval', array_keys($cart));
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $statement = db()->prepare("SELECT id, name, price, stock FROM products WHERE is_active = 1 AND id IN ({$placeholders})");
    $statement->execute($ids);

    $items = [];
    foreach ($statement->fetchAll() as $product) {
        $product['quantity'] = (int) $cart[$product['id']];
        $items[] = $product;
    }

    return $items;
}

function price_to_cents(string $price): int
{
    $parts = explode('.', $price, 2);
    $whole = (int) $parts[0];
    $fraction = isset($parts[1]) ? (int) str_pad(substr($parts[1], 0, 2), 2, '0') : 0;

    return $whole * 100 + $fraction;
}

function cents_to_price(int $cents): string
{
    return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
}

function money(string $price): string
{
    return '$' . number_format(price_to_cents($price) / 100, 2);
}

function current_customer(): ?array
{
    $customerId = filter_var($_SESSION['customer_id'] ?? null, FILTER_VALIDATE_INT);
    if ($customerId === false || $customerId === null || $customerId < 1) {
        return null;
    }

    $statement = db()->prepare('SELECT id, full_name, email, is_admin FROM customers WHERE id = ?');
    $statement->execute([$customerId]);
    $customer = $statement->fetch();

    if (!$customer) {
        unset($_SESSION['customer_id']);
        return null;
    }

    return $customer;
}

function establish_customer_session(array $customer): void
{
    session_regenerate_id(true);
    $_SESSION['customer_id'] = (int) $customer['id'];
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function redirect_after_login(): void
{
    if ($_SESSION['admin_after_login'] ?? false) {
        $destination = 'admin/index.php';
    } elseif ($_SESSION['checkout_after_login'] ?? false) {
        $destination = 'checkout.php';
    } else {
        $destination = 'account.php';
    }

    unset($_SESSION['admin_after_login'], $_SESSION['checkout_after_login']);
    redirect($destination);
}
