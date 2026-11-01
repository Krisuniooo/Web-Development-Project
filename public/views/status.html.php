<?php $stylesheets[] = '/css/status.css'; ?>

<div class="status-page">
    <?php include __DIR__ . '/components/header.html.php'; ?>
    <main class="status">
        <h1 class="status-title"><?= $t('status.title') ?></h1>
        <p class="status-description"><?= $t('status.not_ready') ?></p>
    </main>
    <?php include __DIR__ . '/components/footer.html.php'; ?>
</div>
