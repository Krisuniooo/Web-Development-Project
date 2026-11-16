<?php $stylesheets[] = '/css/panel.css'; ?>

<div class="panel">
    <?php include __DIR__ . '/components/panel-header.html.php'; ?>
    <?php include __DIR__ . '/components/sidebar.html.php'; ?>
    <main class="panel-content">
        <h1 class="panel-content--title"><?= $e($pageTitle) ?></h1>
        <?php // the view of the section, as PanelController names it ?>
        <?php include __DIR__ . '/panel/' . $sectionView . '.html.php'; ?>
    </main>
    <?php include __DIR__ . '/components/footer.html.php'; ?>
</div>
