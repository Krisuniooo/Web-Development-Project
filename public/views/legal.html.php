<?php $stylesheets[] = '/css/legal.css'; ?>

<div class="legal-page">
    <?php include __DIR__ . '/components/header.html.php'; ?>
    <main class="legal">
        <h1 class="legal-title"><?= $e($pageTitle) ?></h1>
        <p class="legal-updated">
            <?= sprintf($t('legal.updated'), '<time datetime="' . $e($updated) . '">' . $e($updated) . '</time>') ?>
        </p>
        <?php foreach ($sections as $section): ?>
            <section class="legal-section">
                <h2 class="legal-heading"><?= $e($section['heading']) ?></h2>
                <?php foreach ($section['blocks'] as $block): ?>
                    <?php if (is_array($block)): ?>
                        <ul class="legal-list">
                            <?php foreach ($block as $point): ?>
                                <li><?= $e($point) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="legal-text"><?= $e($block) ?></p>
                    <?php endif; ?>
                <?php endforeach; ?>
            </section>
        <?php endforeach; ?>
    </main>
    <?php include __DIR__ . '/components/footer.html.php'; ?>
</div>
