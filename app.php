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

function valid_contact_number(string $contactNumber): bool
{
    $digits = preg_replace('/\D/', '', $contactNumber);

    return strlen($contactNumber) <= 30
        && preg_match('/\A\+?[0-9][0-9(). -]*\z/', $contactNumber) === 1
        && is_string($digits)
        && strlen($digits) >= 7
        && strlen($digits) <= 15;
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

function cart_selection_ids(): array
{
    $cart = cart();
    $rawSelection = $_SESSION['cart_selected_product_ids'] ?? array_keys($cart);
    if (!is_array($rawSelection)) {
        return [];
    }

    $selectedIds = [];
    foreach ($rawSelection as $rawId) {
        $id = filter_var($rawId, FILTER_VALIDATE_INT);
        if ($id !== false && $id !== null && $id > 0 && array_key_exists($id, $cart)) {
            $selectedIds[$id] = (int) $id;
        }
    }

    return array_values($selectedIds);
}

function selected_cart(): array
{
    $cart = cart();
    $selectedCart = [];
    foreach (cart_selection_ids() as $id) {
        $selectedCart[$id] = $cart[$id];
    }

    return $selectedCart;
}

function cart_count(): int
{
    return array_sum(cart());
}

function cart_items(?array $cartContents = null): array
{
    $cart = $cartContents ?? cart();
    if (!$cart) {
        return [];
    }

    $ids = array_map('intval', array_keys($cart));
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $statement = db()->prepare(
        "SELECT p.id, p.name, p.price, p.stock, p.is_available, p.image_path
         FROM products p
         WHERE p.is_active = 1
           AND p.approval_status = 'approved'
           AND (p.shop_id IS NULL OR EXISTS (
               SELECT 1 FROM shops s WHERE s.id = p.shop_id AND s.status = 'approved'
           ))
           AND p.id IN ({$placeholders})"
    );
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
    return '₱' . number_format(price_to_cents($price) / 100, 2);
}

function order_status_label(string $status): string
{
    $labels = [
        'pending' => 'Pending',
        'ongoing' => 'Ongoing',
        'on_shipment' => 'On shipment',
        'delivered' => 'Delivered',
        'cancelled' => 'Cancelled',
    ];

    return $labels[$status] ?? ucfirst(str_replace('_', ' ', $status));
}

function payment_method_label(string $method): string
{
    $labels = [
        'cod' => 'Cash on delivery (COD)',
        'online_test' => 'Online payment (test only)',
    ];

    return $labels[$method] ?? ucfirst(str_replace('_', ' ', $method));
}

function save_profile_image(?array $upload): ?string
{
    if ($upload === null) {
        return null;
    }

    $error = filter_var($upload['error'] ?? null, FILTER_VALIDATE_INT);
    if ($error !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException('Choose a JPG, PNG, or WebP profile picture under 5 MB.');
    }

    $temporaryPath = $upload['tmp_name'] ?? null;
    if (!is_string($temporaryPath) || !is_uploaded_file($temporaryPath)) {
        throw new InvalidArgumentException('Choose a valid profile picture and try again.');
    }

    $fileSize = filesize($temporaryPath);
    if ($fileSize === false || $fileSize < 1 || $fileSize > 5 * 1024 * 1024) {
        throw new InvalidArgumentException('Profile pictures must be smaller than 5 MB.');
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($temporaryPath);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!is_string($mime) || !isset($extensions[$mime])) {
        throw new InvalidArgumentException('Use a JPG, PNG, or WebP profile picture.');
    }

    $imageInfo = @getimagesize($temporaryPath);
    if (!is_array($imageInfo) || ($imageInfo['mime'] ?? null) !== $mime) {
        throw new InvalidArgumentException('The uploaded file is not a valid image.');
    }

    $width = (int) $imageInfo[0];
    $height = (int) $imageInfo[1];
    if ($width < 1 || $height < 1 || $width > 6000 || $height > 6000 || $width * $height > 20000000) {
        throw new InvalidArgumentException('Images must be at most 6000 pixels per side and 20 megapixels.');
    }

    $uploadDirectory = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'profiles';
    if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
        throw new RuntimeException('Unable to create the profile picture folder.');
    }

    $fileName = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
    if (!move_uploaded_file($temporaryPath, $uploadDirectory . DIRECTORY_SEPARATOR . $fileName)) {
        throw new RuntimeException('Unable to save the uploaded profile picture.');
    }

    return 'uploads/profiles/' . $fileName;
}

function remove_profile_image(?string $imagePath): void
{
    if ($imagePath === null || !preg_match('#\Auploads/profiles/[a-f0-9]{32}\.(?:jpg|png|webp)\z#', $imagePath)) {
        return;
    }

    $filePath = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'profiles' . DIRECTORY_SEPARATOR . basename($imagePath);
    if (is_file($filePath) && !unlink($filePath)) {
        error_log('Could not remove profile picture: ' . $filePath);
    }
}

function current_customer(): ?array
{
    $customerId = filter_var($_SESSION['customer_id'] ?? null, FILTER_VALIDATE_INT);
    if ($customerId === false || $customerId === null || $customerId < 1) {
        return null;
    }

    $statement = db()->prepare('SELECT id, full_name, email, contact_number, is_admin, profile_image_path FROM customers WHERE id = ?');
    $statement->execute([$customerId]);
    $customer = $statement->fetch();

    if (!$customer) {
        unset($_SESSION['customer_id']);
        return null;
    }

    return $customer;
}

function seller_shop_for_customer(int $customerId): ?array
{
    $statement = db()->prepare(
        'SELECT id, customer_id, store_name, description, status, review_note, created_at, updated_at, approved_at
         FROM shops
         WHERE customer_id = ?'
    );
    $statement->execute([$customerId]);
    $shop = $statement->fetch();

    return $shop ?: null;
}

function current_admin(): ?array
{
    $adminId = filter_var($_SESSION['admin_id'] ?? null, FILTER_VALIDATE_INT);
    if ($adminId === false || $adminId === null || $adminId < 1) {
        return null;
    }

    $statement = db()->prepare('SELECT id, full_name, email, is_admin, profile_image_path FROM customers WHERE id = ? AND is_admin = 1');
    $statement->execute([$adminId]);
    $admin = $statement->fetch();

    if (!$admin) {
        unset($_SESSION['admin_id']);
        return null;
    }

    return $admin;
}

function establish_customer_session(array $customer): void
{
    session_regenerate_id(true);
    $_SESSION['customer_id'] = (int) $customer['id'];
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function establish_admin_session(array $admin): void
{
    session_regenerate_id(true);

    $customerId = filter_var($_SESSION['customer_id'] ?? null, FILTER_VALIDATE_INT);
    if ($customerId !== false && $customerId !== null && $customerId === (int) $admin['id']) {
        unset($_SESSION['customer_id']);
    }

    $_SESSION['admin_id'] = (int) $admin['id'];
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function redirect_after_login(): void
{
    if ($_SESSION['checkout_after_login'] ?? false) {
        $destination = 'checkout.php';
    } else {
        $destination = 'account.php';
    }

    unset($_SESSION['checkout_after_login']);
    redirect($destination);
}
