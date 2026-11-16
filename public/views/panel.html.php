<?php $stylesheets[] = '/css/panel.css'; ?>

<div class="panel">
    <?php include __DIR__ . '/components/panel-header.html.php'; ?>
    <?php include __DIR__ . '/components/sidebar.html.php'; ?>
    <main class="panel-content">
        <h1 class="panel-content--title"><?= $e($pageTitle) ?></h1>
        <?php if ($activeSection === 'servers'): ?>
            <?php include __DIR__ . '/panel/servers.html.php'; ?>
        <?php elseif ($activeSection === 'overview'): ?>
            <?php include __DIR__ . '/panel/overview.html.php'; ?>
        <?php elseif ($activeSection === 'channels'): ?>
            <?php include __DIR__ . '/panel/channels.html.php'; ?>
        <?php elseif ($activeSection === 'models'): ?>
            <?php include __DIR__ . '/panel/models.html.php'; ?>
        <?php elseif ($activeSection === 'limits'): ?>
            <?php include __DIR__ . '/panel/limits.html.php'; ?>
        <?php elseif ($activeSection === 'logs'): ?>
            <?php include __DIR__ . ($logEvent === null ? '/panel/logs.html.php' : '/panel/log-event.html.php'); ?>
        <?php endif; ?>
    </main>
    <?php include __DIR__ . '/components/footer.html.php'; ?>
</div>
