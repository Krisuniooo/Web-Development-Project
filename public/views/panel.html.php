<?php $stylesheets[] = '/css/panel.css'; ?>

<div class="panel">
    <?php include __DIR__ . '/components/panel-header.html.php'; ?>
    <?php include __DIR__ . '/components/sidebar.html.php'; ?>
    <main class="panel-content">
        <h1 class="panel-content--title"><?= $t($navigation[$activeSection]['name']) ?></h1>
        <?php if ($activeSection === 'overview'): ?>
            <?php include __DIR__ . '/panel/servers.html.php'; ?>
        <?php elseif ($activeSection === 'channels'): ?>
            <?php include __DIR__ . '/panel/channels.html.php'; ?>
        <?php elseif ($activeSection === 'limits'): ?>
            <?php include __DIR__ . '/panel/limits.html.php'; ?>
        <?php else: ?>
            <p class="panel-content--description"><?= $t('panel.not_ready') ?></p>
        <?php endif; ?>
    </main>
    <?php include __DIR__ . '/components/footer.html.php'; ?>
</div>
