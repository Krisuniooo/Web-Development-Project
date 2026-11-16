<?php $stylesheets[] = '/css/home/hero.css'; ?>
<?php $scripts[] = '/js/home/message-stream.js'; ?>
<?php $scripts[] = '/js/home/eye.js'; ?>

<section class="hero">
    <div class="hero-message-stream" data-messages="<?= $e(json_encode($chatMessages)) ?>" data-platforms="<?= $e(json_encode(array_column($platforms, 'slug'))) ?>" aria-hidden="true"></div>
    <div class="hero-content">
        <h1 class="hero-title"><?= $t('home.hero.title') ?></h1>
        <p class="hero-description"><?= $t('home.hero.description') ?></p>
        <div class="hero-actions">
            <a href="/login" class="hero-action-button--main"><?= $t('home.hero.primary') ?></a>
            <a href="#how-it-works" class="hero-action-button--secondary"><?= $t('home.hero.secondary') ?></a>
        </div>
    </div>
    <div class="hero-eye" aria-hidden="true">
        <div class="hero-eye--frame" data-frames="<?= $e(json_encode($eyeFrames)) ?>">
            <pre class="hero-eye--sclera-layer"></pre>
            <div class="hero-eye--opening">
                <pre class="hero-eye--iris-layer"></pre>
            </div>
        </div>
    </div>
</section>