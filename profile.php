<?php
require_once __DIR__ . '/app.php';

$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$isAdminView = preg_match('#/admin/profile\.php\z#', $scriptName) === 1;
$customer = $isAdminView ? current_admin() : current_customer();
if (!$customer) {
    redirect($isAdminView ? '../admin/login.php' : 'login.php');
}

$assetPrefix = $isAdminView ? '../' : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid()) {
        flash('error', 'Your session expired. Please try again.');
        redirect('profile.php');
    }

    $action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';
    if ($action === 'update_name' && !$isAdminView) {
        $fullName = isset($_POST['full_name']) && is_string($_POST['full_name']) ? trim($_POST['full_name']) : '';
        if ($fullName === '' || strlen($fullName) > 120) {
            flash('error', 'Enter a name between 1 and 120 characters.');
            redirect('profile.php');
        }

        $updateStatement = db()->prepare('UPDATE customers SET full_name = ? WHERE id = ?');
        $updateStatement->execute([$fullName, $customer['id']]);
        flash('notice', 'Your name was updated.');
        redirect('profile.php');
    }

    if ($action === 'update_picture') {
        $upload = $_FILES['profile_image'] ?? null;
        if ($upload !== null && !is_array($upload)) {
            flash('error', 'Choose a valid profile picture.');
            redirect('profile.php');
        }

        try {
            $newImagePath = save_profile_image($upload);
            if ($newImagePath === null) {
                throw new InvalidArgumentException('Choose a profile picture to upload.');
            }
        } catch (InvalidArgumentException $exception) {
            flash('error', $exception->getMessage());
            redirect('profile.php');
        } catch (RuntimeException $exception) {
            error_log('Profile picture upload failed: ' . $exception->getMessage());
            flash('error', 'The profile picture could not be saved. Check that the uploads folder is writable.');
            redirect('profile.php');
        }

        try {
            $updateStatement = db()->prepare('UPDATE customers SET profile_image_path = ? WHERE id = ?');
            $updateStatement->execute([$newImagePath, $customer['id']]);
        } catch (PDOException $exception) {
            remove_profile_image($newImagePath);
            error_log('Profile picture update failed: ' . $exception->getMessage());
            flash('error', 'Your profile picture could not be updated.');
            redirect('profile.php');
        }

        remove_profile_image($customer['profile_image_path']);
        flash('notice', 'Your profile picture was updated.');
        redirect('profile.php');
    }

    if ($action === 'change_password') {
        $currentPassword = isset($_POST['current_password']) && is_string($_POST['current_password']) ? $_POST['current_password'] : '';
        $newPassword = isset($_POST['new_password']) && is_string($_POST['new_password']) ? $_POST['new_password'] : '';
        $confirmPassword = isset($_POST['confirm_password']) && is_string($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

        if (strlen($newPassword) < 10 || strlen($newPassword) > 72) {
            flash('error', 'Choose a new password between 10 and 72 characters.');
            redirect('profile.php');
        }
        if ($newPassword !== $confirmPassword) {
            flash('error', 'The new password and confirmation do not match.');
            redirect('profile.php');
        }

        $passwordStatement = db()->prepare('SELECT password_hash FROM customers WHERE id = ?');
        $passwordStatement->execute([$customer['id']]);
        $passwordHash = $passwordStatement->fetchColumn();
        if (!is_string($passwordHash) || !password_verify($currentPassword, $passwordHash)) {
            flash('error', 'Your current password is incorrect.');
            redirect('profile.php');
        }

        $updateStatement = db()->prepare('UPDATE customers SET password_hash = ? WHERE id = ?');
        $updateStatement->execute([password_hash($newPassword, PASSWORD_DEFAULT), $customer['id']]);
        session_regenerate_id(true);
        flash('notice', 'Your password was changed.');
        redirect('profile.php');
    }

    flash('error', 'Choose a valid profile action.');
    redirect('profile.php');
}

$pageTitle = 'Profile settings';
$activePage = 'profile';
$admin = $customer;
$notice = flash('notice');
$error = flash('error');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Profile settings | Sari-Sari Store</title>
    <link rel="stylesheet" href="<?= e($assetPrefix) ?>style.css">
    <?php if ($isAdminView): ?>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <?php endif; ?>
</head>
<body<?= $isAdminView ? ' class="admin-body"' : '' ?>>
<?php if ($isAdminView): ?>
    <?php require __DIR__ . '/admin/_header.php'; ?>
<?php else: ?>
    <?php require __DIR__ . '/site_header.php'; ?>
<?php endif; ?>
<?php if ($isAdminView): ?>
    <?php if ($notice): ?><p class="alert success" role="status"><?= e($notice) ?></p><?php endif; ?>
    <?php if ($error): ?><p class="alert error" role="alert"><?= e($error) ?></p><?php endif; ?>
    <section class="admin-panel profile-settings-panel">
<?php else: ?>
    <main class="page-main profile-page">
        <p class="eyebrow">YOUR ACCOUNT</p>
        <h1 class="page-title">Profile settings</h1>
        <?php if ($notice): ?><p class="alert success" role="status"><?= e($notice) ?></p><?php endif; ?>
        <?php if ($error): ?><p class="alert error" role="alert"><?= e($error) ?></p><?php endif; ?>
        <section class="profile-settings-panel">
<?php endif; ?>
            <div class="profile-settings-heading">
                <div class="profile-picture-preview">
                    <?php if ($customer['profile_image_path']): ?>
                        <img src="<?= e($assetPrefix . $customer['profile_image_path']) ?>" alt="Profile picture of <?= e($customer['full_name']) ?>">
                    <?php else: ?>
                        <span><?= e(strtoupper(substr($customer['full_name'], 0, 1))) ?></span>
                    <?php endif; ?>
                </div>
                <div>
                    <p class="eyebrow">ACCOUNT PROFILE</p>
                    <h2><?= e($customer['full_name']) ?></h2>
                    <p><?= e($customer['email']) ?></p>
                </div>
            </div>

            <?php if (!$isAdminView): ?>
                <div class="profile-settings-divider"></div>
                <div class="profile-form-heading">
                    <h2>Edit profile</h2>
                    <p>Your email address is fixed to your account and cannot be changed here.</p>
                </div>
                <form class="profile-form" method="post" action="profile.php">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="update_name">
                    <div>
                        <label for="full-name">Name</label>
                        <input id="full-name" type="text" name="full_name" value="<?= e($customer['full_name']) ?>" maxlength="120" autocomplete="name" required>
                    </div>
                    <div>
                        <label for="account-email">Email address</label>
                        <input id="account-email" type="email" value="<?= e($customer['email']) ?>" readonly>
                    </div>
                    <button class="button" type="submit">Save changes</button>
                </form>
            <?php endif; ?>

            <form class="profile-form" method="post" action="profile.php" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="update_picture">
                <div>
                    <label for="profile-image">Profile picture</label>
                    <input id="profile-image" type="file" name="profile_image" accept="image/jpeg,image/png,image/webp" required>
                    <small class="field-hint">JPG, PNG, or WebP; maximum 5 MB.</small>
                </div>
                <button class="button" type="submit">Save profile picture</button>
            </form>

            <div class="profile-settings-divider"></div>
            <div class="profile-form-heading">
                <h2>Change password</h2>
                <p>For your security, confirm your current password first.</p>
            </div>
            <form class="profile-form password-change-form" method="post" action="profile.php">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="change_password">
                <div>
                    <label for="current-password">Current password</label>
                    <input id="current-password" type="password" name="current_password" autocomplete="current-password" required>
                </div>
                <div>
                    <label for="new-password">New password</label>
                    <input id="new-password" type="password" name="new_password" autocomplete="new-password" minlength="10" maxlength="72" required>
                </div>
                <div>
                    <label for="confirm-password">Confirm new password</label>
                    <input id="confirm-password" type="password" name="confirm_password" autocomplete="new-password" minlength="10" maxlength="72" required>
                    <small class="field-hint">Use at least 10 characters; passwords are limited to 72 bytes.</small>
                </div>
                <button class="button" type="submit">Change password</button>
            </form>
<?php if ($isAdminView): ?>
    </section>
<?php else: ?>
        </section>
    </main>
<?php endif; ?>
<?php if ($isAdminView): ?>
    <?php require __DIR__ . '/admin/_footer.php'; ?>
<?php else: ?>
    <?php require __DIR__ . '/site_footer.php'; ?>
<?php endif; ?>
</body>
</html>
