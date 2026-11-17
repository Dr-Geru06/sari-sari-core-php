<?php
require_once __DIR__ . '/app.php';

$admin = current_admin();
if (!$admin) {
    flash('notice', 'Sign in with an administrator account to edit the About page.');
    redirect('admin/login.php');
}

$creatorSlots = [
    ['key' => 'leader', 'label' => 'Project Leader'],
    ['key' => 'member_1', 'label' => 'Project Member 1'],
    ['key' => 'member_2', 'label' => 'Project Member 2'],
    ['key' => 'member_3', 'label' => 'Project Member 3'],
    ['key' => 'member_4', 'label' => 'Project Member 4'],
    ['key' => 'member_5', 'label' => 'Project Member 5'],
];
$slotLabels = [];
foreach ($creatorSlots as $slot) {
    $slotLabels[$slot['key']] = $slot['label'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid()) {
        flash('error', 'Your session expired. Please try again.');
        redirect('editabove.php');
    }

    $slotKey = isset($_POST['slot_key']) && is_string($_POST['slot_key']) ? $_POST['slot_key'] : '';
    $fullName = isset($_POST['full_name']) && is_string($_POST['full_name']) ? trim($_POST['full_name']) : '';
    $removeImage = isset($_POST['remove_image']) && $_POST['remove_image'] === '1';
    if (!isset($slotLabels[$slotKey]) || strlen($fullName) > 120) {
        flash('error', 'Choose a creator and keep the name within 120 characters.');
        redirect('editabove.php');
    }

    $upload = $_FILES['profile_image'] ?? null;
    if ($upload !== null && !is_array($upload)) {
        flash('error', 'Choose a valid profile picture.');
        redirect('editabove.php');
    }
    if (is_array($upload) && ($upload['error'] ?? null) === UPLOAD_ERR_NO_FILE) {
        $upload = null;
    }
    if ($removeImage && $upload !== null) {
        flash('error', 'Choose either a new profile picture or remove the current one.');
        redirect('editabove.php');
    }

    $currentStatement = db()->prepare(
        'SELECT profile_image_path FROM about_creators WHERE slot_key = ?'
    );
    $currentStatement->execute([$slotKey]);
    $currentImagePath = $currentStatement->fetchColumn();
    $currentImagePath = is_string($currentImagePath) ? $currentImagePath : null;

    try {
        $newImagePath = save_profile_image($upload);
    } catch (InvalidArgumentException $exception) {
        flash('error', $exception->getMessage());
        redirect('editabove.php');
    } catch (RuntimeException $exception) {
        error_log('About page profile upload failed: ' . $exception->getMessage());
        flash('error', 'The profile picture could not be saved. Check that the uploads folder is writable.');
        redirect('editabove.php');
    }

    $imagePath = $removeImage ? null : ($newImagePath ?? $currentImagePath);
    try {
        $saveStatement = db()->prepare(
            'INSERT INTO about_creators (slot_key, full_name, profile_image_path)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE full_name = VALUES(full_name), profile_image_path = VALUES(profile_image_path)'
        );
        $saveStatement->execute([$slotKey, $fullName, $imagePath]);
    } catch (PDOException $exception) {
        remove_profile_image($newImagePath);
        error_log('About page creator update failed: ' . $exception->getMessage());
        flash('error', 'The About page entry could not be saved.');
        redirect('editabove.php');
    }

    if ($currentImagePath !== null && $currentImagePath !== $imagePath) {
        remove_profile_image($currentImagePath);
    }
    flash('notice', $slotLabels[$slotKey] . ' details were saved.');
    redirect('editabove.php');
}

$creatorRows = [];
$creatorStatement = db()->query(
    'SELECT slot_key, full_name, profile_image_path FROM about_creators'
);
foreach ($creatorStatement->fetchAll() as $creator) {
    $creatorRows[$creator['slot_key']] = $creator;
}

$pageTitle = 'Edit About page';
$activePage = '';
$notice = flash('notice');
$error = flash('error');
?>
<?php if ($notice): ?><p class="alert success" role="status"><?= e($notice) ?></p><?php endif; ?>
<?php if ($error): ?><p class="alert error" role="alert"><?= e($error) ?></p><?php endif; ?>
<link rel="stylesheet" href="style.css">
<div class="about-editor-page">

    <section class="about-editor-hero">
        <div class="about-editor-hero-content">
            <span class="about-editor-kicker">
                <span></span> SARI-SARI ADMINISTRATION
            </span>

            <h1>Meet the people <em>behind the vision.</em></h1>

            <p>
                Curate the faces and names behind Sari-Sari.
                Keep your About page personal, polished, and up to date.
            </p>

            <div class="about-editor-hero-bottom">
                <span class="about-editor-count">06</span>
                <span>CREATOR PROFILES</span>
            </div>
        </div>

        <div class="about-editor-hero-art" aria-hidden="true">
            <div class="about-editor-ring about-ring-one"></div>
            <div class="about-editor-ring about-ring-two"></div>
            <div class="about-editor-monogram">S.S</div>
            <span class="about-editor-art-caption">THE PEOPLE BEHIND SARI-SARI</span>
        </div>
    </section>

    <?php if ($notice): ?>
        <div class="about-editor-alert about-alert-success" role="status">
            <span>✓</span>
            <?= e($notice) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="about-editor-alert about-alert-error" role="alert">
            <span>!</span>
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <div class="about-editor-intro">
        <div>
            <span class="about-editor-eyebrow">THE CREATIVE TEAM</span>
            <h2>Project leader & members</h2>
            <p>
                Add names and portraits to introduce the people who made
                this project possible.
            </p>
        </div>

        <a class="about-editor-preview-link"
           href="about.php"
           target="_blank"
           rel="noopener">
            <span>↗</span> Preview About page
        </a>
    </div>

    <div class="about-editor-guideline">
        <span class="about-guideline-icon">✦</span>
        <div>
            <strong>A little detail makes a difference.</strong>
            <p>
                Update a name, upload or replace a photo, or leave the name
                blank to show an empty box. Technical Support uses the admin profile.
            </p>
        </div>
    </div>

    <section class="about-creator-grid">

        <?php foreach ($creatorSlots as $index => $slot): ?>
            <?php
                $creator = $creatorRows[$slot['key']] ?? [
                    'full_name' => '',
                    'profile_image_path' => null
                ];
            ?>

            <article class="about-creator-card">

                <div class="about-creator-card-top">
                    <span class="about-creator-number">
                        <?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) ?>
                    </span>

                    <?php if ($slot['key'] === 'leader'): ?>
                        <span class="about-creator-role">LEADERSHIP</span>
                    <?php else: ?>
                        <span class="about-creator-role">PROJECT TEAM</span>
                    <?php endif; ?>
                </div>

                <div class="about-creator-photo-frame">
                    <?php if ($creator['profile_image_path']): ?>
                        <img
                            class="about-creator-photo"
                            src="<?= e($creator['profile_image_path']) ?>"
                            alt="Current photo for <?= e($slot['label']) ?>"
                        >
                    <?php else: ?>
                        <div class="about-creator-photo about-creator-photo-empty">
                            <span>✦</span>
                            <small>NO PHOTO</small>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="about-creator-card-heading">
                    <span>PROFILE DETAILS</span>
                    <h3><?= e($slot['label']) ?></h3>
                </div>

                <form
                    class="about-creator-form"
                    method="post"
                    action="editabove.php"
                    enctype="multipart/form-data"
                >
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e(csrf_token()) ?>"
                    >

                    <input
                        type="hidden"
                        name="slot_key"
                        value="<?= e($slot['key']) ?>"
                    >

                    <div class="about-editor-field">
                        <label for="creator-name-<?= e($slot['key']) ?>">
                            Full name
                        </label>

                        <input
                            id="creator-name-<?= e($slot['key']) ?>"
                            name="full_name"
                            maxlength="120"
                            value="<?= e($creator['full_name']) ?>"
                            placeholder="Enter creator's name"
                        >
                    </div>

                    <div class="about-editor-field">
                        <label for="creator-photo-<?= e($slot['key']) ?>">
                            Profile photograph
                        </label>

                        <div class="about-photo-input">
                            <input
                                id="creator-photo-<?= e($slot['key']) ?>"
                                type="file"
                                name="profile_image"
                                accept="image/jpeg,image/png,image/webp"
                            >
                        </div>

                        <small class="about-editor-hint">
                            JPG, PNG or WebP · Maximum 5 MB
                        </small>
                    </div>

                    <?php if ($creator['profile_image_path']): ?>
                        <label class="about-remove-photo">
                            <input type="checkbox" name="remove_image" value="1">
                            <span>Remove current photograph</span>
                        </label>
                    <?php endif; ?>

                    <button class="about-save-button" type="submit">
                        <span>Save profile</span>
                        <span>↗</span>
                    </button>
                </form>

            </article>
        <?php endforeach; ?>

    </section>

    <div class="about-editor-bottom-note">
        <span>✦</span>
        Every great store has a story. These are the people behind ours.
    </div>

</div>
