<?php $stylesheets[] = '/css/home/CTA.css'; ?>
<?php $scripts[] = '/js/cta-composer.js'; ?>

<section class="CTA">
    <h2 class="CTA-title"><?= $t('home.cta.title') ?></h2>
    <div class="CTA-composer">
        <span class="CTA-composer--icons">
            <?php foreach ($platforms as $index => $platform): ?>
                <img
                    src="/assets/icons/<?= htmlspecialchars($platform['slug'], ENT_QUOTES, 'UTF-8') ?>.svg"
                    class="CTA-composer--icon<?= $index === 0 ? ' is-active' : '' ?>"
                    alt=""
                    width="24"
                    height="24"
                />
            <?php endforeach; ?>
        </span>
        <span class="CTA-composer--text">
            <?php foreach ($platforms as $platform): ?>
                <span class="CTA-composer--message"><?= $t($platform['ctaMessage']) ?></span>
            <?php endforeach; ?>
            <span class="CTA-composer--typed" aria-hidden="true"><?= $t($platforms[0]['ctaMessage']) ?></span>
        </span>
        <a href="/login" class="CTA-button">
            <span><?= $t('home.cta.button') ?></span>
            <i class="fa-regular fa-paper-plane"></i>
        </a>
    </div>
    <p class="CTA-description"><?= $t('home.cta.description') ?></p>
</section>
