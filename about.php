<?php
require __DIR__ . '/app.php';

$creatorSlots = [
    ['key' => 'leader', 'label' => 'Project Leader', 'class' => 'creator-leader'],
    ['key' => 'member_1', 'label' => 'Project Member 1', 'class' => 'creator-member'],
    ['key' => 'member_2', 'label' => 'Project Member 2', 'class' => 'creator-member'],
    ['key' => 'member_3', 'label' => 'Project Member 3', 'class' => 'creator-member'],
    ['key' => 'member_4', 'label' => 'Project Member 4', 'class' => 'creator-member'],
    ['key' => 'member_5', 'label' => 'Project Member 5', 'class' => 'creator-member'],
];
$creatorRows = [];
$creatorStatement = db()->query('SELECT slot_key, full_name, profile_image_path FROM about_creators');
foreach ($creatorStatement->fetchAll() as $creator) {
    $creatorRows[$creator['slot_key']] = $creator;
}
$supportStatement = db()->prepare(
    'SELECT full_name, email, profile_image_path
     FROM customers
     WHERE email = ? AND is_admin = 1
     LIMIT 1'
);
$supportStatement->execute(['sigbin@admin.ph']);
$supportAdmin = $supportStatement->fetch() ?: null;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>About Us | Sari-Sari</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php require __DIR__ . '/site_header.php'; ?>
<main class="page-main about-page">
    <section class="about-intro">
        <p class="eyebrow">ABOUT THIS PROJECT</p>
        <h1 class="page-title">Meet our creators</h1>
        <p>Our creators brought this project to life through their collaboration and dedication. Special thanks to our technical support for their guidance and assistance.</p>
    </section>
    <section class="creator-grid" aria-label="Project creators">
        <?php foreach ($creatorSlots as $slot): ?>
            <?php $creator = $creatorRows[$slot['key']] ?? ['full_name' => '', 'profile_image_path' => null]; ?>
            <article class="creator-card <?= e($slot['class']) ?>">
                <div class="creator-photo-placeholder<?= $creator['profile_image_path'] ? ' has-profile' : '' ?>">
                    <?php if ($creator['profile_image_path']): ?>
                        <img src="<?= e($creator['profile_image_path']) ?>" alt="Profile picture of <?= e($creator['full_name']) ?>">
                    <?php endif; ?>
                </div>
                <h2><?= e($slot['label']) ?></h2>
                <?php if ($creator['full_name'] !== ''): ?>
                    <p class="creator-person-name"><?= e($creator['full_name']) ?></p>
                <?php else: ?>
                    <div class="creator-name-placeholder" role="img" aria-label="Creator name not added yet"></div>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
        <article class="creator-card creator-support">
            <div class="creator-photo-placeholder has-profile">
                <?php if ($supportAdmin && $supportAdmin['profile_image_path']): ?>
                    <img src="<?= e($supportAdmin['profile_image_path']) ?>" alt="Profile picture of <?= e($supportAdmin['full_name']) ?>">
                <?php elseif ($supportAdmin): ?>
                    <span><?= e(strtoupper(substr($supportAdmin['full_name'], 0, 1))) ?></span>
                <?php endif; ?>
            </div>
            <h2>Technical Support</h2>
            <?php if ($supportAdmin): ?>
                <p class="creator-person-name"><?= e($supportAdmin['full_name']) ?></p>
            <?php else: ?>
                <p class="creator-person-name">Admin profile unavailable</p>
            <?php endif; ?>
            <a class="creator-person-email" href="mailto:sigbin@admin.ph">sigbin@admin.ph</a>
        </article>
    </section>
</main>
<?php require __DIR__ . '/site_footer.php'; ?>
</body>
</html>
