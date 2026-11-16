<?php $stylesheets[] = '/css/components/header.css'; ?>

<header class="nav">
    <a href="/" class="logo">
        <div class="logo-box">
            <img src="/assets/icons/logo.svg" alt="" class="logo-box-img" width="56" height="28" />
        </div>
        <div class="logo-name"><?= $t('app.name') ?></div>
    </a>
    <div class="nav-right">
        <nav class="platforms" aria-label="<?= $t('header.manage') ?>">
            <div class="available-platforms-label"><?= $t('header.manage_on') ?></div>
            <ul class="available-platforms">
                <?php foreach ($platforms as $platform): ?>
                    <li>
                        <a href="<?= $e($platform['loginPath'] ?? '#') ?>" class="available-platform <?= $e($platform['slug']) ?>">
                            <img src="/assets/icons/<?= $e($platform['slug']) ?>.svg" alt="<?= $e($platform['name']) ?>" class="available-platform--img" width="16" height="16" />
                            <div aria-hidden="true"><?= $e($platform['name']) ?></div>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>
        <?php include __DIR__ . '/nav-settings.html.php'; ?>
    </div>
</header>