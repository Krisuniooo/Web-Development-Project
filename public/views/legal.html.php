<?php $stylesheets[] = '/css/legal.css'; ?>

<div class="legal-page">
    <?php include __DIR__ . '/components/header.html.php'; ?>
    <main class="legal">
        <h1 class="legal-title"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="legal-updated">
            <?= sprintf($t('legal.updated'), '<time datetime="' . htmlspecialchars($updated, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($updated, ENT_QUOTES, 'UTF-8') . '</time>') ?>
        </p>
        <?php foreach ($sections as $section): ?>
            <section class="legal-section">
                <h2 class="legal-heading"><?= htmlspecialchars($section['heading'], ENT_QUOTES, 'UTF-8') ?></h2>
                <?php foreach ($section['blocks'] as $block): ?>
                    <?php if (is_array($block)): ?>
                        <ul class="legal-list">
                            <?php foreach ($block as $point): ?>
                                <li><?= htmlspecialchars($point, ENT_QUOTES, 'UTF-8') ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="legal-text"><?= htmlspecialchars($block, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>
                <?php endforeach; ?>
            </section>
        <?php endforeach; ?>
    </main>
    <?php include __DIR__ . '/components/footer.html.php'; ?>
</div>
