<?php $stylesheets[] = '/css/home/hero.css'; ?>
<?php $scripts[] = '/js/message-stream.js'; ?>
<?php $scripts[] = '/js/eye.js'; ?>

<section class="hero">
    <div class="hero-message-stream" data-messages="<?= htmlspecialchars(json_encode($chatMessages), ENT_QUOTES, 'UTF-8') ?>" data-platforms="<?= htmlspecialchars(json_encode(array_column($platforms, 'slug')), ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></div>
    <div class="hero-content">
        <h1 class="hero-title"><?= $t('home.hero.title') ?></h1>
        <p class="hero-description"><?= $t('home.hero.description') ?></p>
        <div class="hero-actions">
            <button type="button" class="hero-action-button--main"><?= $t('home.hero.primary') ?></button>
            <button type="button" class="hero-action-button--secondary"><?= $t('home.hero.secondary') ?></button>
        </div>
    </div>
    <div class="hero-eye" aria-hidden="true">
        <div class="hero-eye--frame" data-frames="<?= htmlspecialchars(json_encode($eyeFrames), ENT_QUOTES, 'UTF-8') ?>">
            <pre class="hero-eye--sclera-layer"></pre>
            <div class="hero-eye--opening">
                <pre class="hero-eye--iris-layer"></pre>
            </div>
        </div>
    </div>
</section>