<?php $stylesheets[] = '/css/panel.css'; ?>

<div class="panel">
    <?php include __DIR__ . '/components/panel-header.html.php'; ?>
    <?php include __DIR__ . '/components/sidebar.html.php'; ?>
    <main class="panel-content">
        <h1 class="panel-content--title"><?= $t($navigation[$activeSection]['name']) ?></h1>
        <p class="panel-content--description"><?= $t('panel.not_ready') ?></p>
    </main>
    <?php include __DIR__ . '/components/footer.html.php'; ?>
</div>
