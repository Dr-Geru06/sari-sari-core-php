<?php
require __DIR__ . '/app.php';

$creatorRoles = [
    ['label' => 'Project Leader', 'count' => 1, 'class' => 'creator-leader'],
    ['label' => 'Project Member', 'count' => 9, 'class' => 'creator-member'],
    ['label' => 'Technical Support', 'count' => 1, 'class' => 'creator-support'],
];
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
        <p>The team behind this project. Creator details can be added to the empty boxes later.</p>
    </section>
    <section class="creator-grid" aria-label="Project creators">
        <?php foreach ($creatorRoles as $role): ?>
            <?php for ($index = 1; $index <= $role['count']; $index++): ?>
                <?php
                $roleLabel = $role['count'] === 1 ? $role['label'] : $role['label'] . ' ' . $index;
                ?>
                <article class="creator-card <?= e($role['class']) ?>">
                    <div class="creator-photo-placeholder" aria-hidden="true"></div>
                    <h2><?= e($roleLabel) ?></h2>
                    <div class="creator-name-placeholder" role="img" aria-label="Creator name not added yet"></div>
                </article>
            <?php endfor; ?>
        <?php endforeach; ?>
    </section>
</main>
<?php require __DIR__ . '/site_footer.php'; ?>
</body>
</html>
