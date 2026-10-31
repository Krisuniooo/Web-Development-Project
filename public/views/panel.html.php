<?php $stylesheets[] = '/css/panel.css'; ?>

<div class="panel">
    <?php include __DIR__ . '/components/panel-header.html.php'; ?>
    <?php include __DIR__ . '/components/sidebar.html.php'; ?>
    <main class="panel-content">
        <h1 class="panel-content--title"><?= htmlspecialchars($navigation[$activeSection]['name'], ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="panel-content--description">This section is not ready yet.</p>
    </main>
    <?php include __DIR__ . '/components/footer.html.php'; ?>
</div>
