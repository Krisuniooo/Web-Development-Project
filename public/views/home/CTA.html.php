<?php $scripts[] = '/js/cta-composer.js'; ?>

<section class="CTA">
    <h2 class="CTA-title">Lorem ipsum dolor sit amet consectetur</h2>
    <div class="CTA-composer">
        <span class="CTA-composer--icons">
            <?php foreach ($ctaPlatforms as $index => $platform): ?>
                <img
                    src="<?= htmlspecialchars($platform['icon'], ENT_QUOTES, 'UTF-8') ?>"
                    class="CTA-composer--icon<?= $index === 0 ? ' is-active' : '' ?>"
                    alt=""
                    width="24"
                    height="24"
                />
            <?php endforeach; ?>
        </span>
        <span class="CTA-composer--text">
            <?php foreach ($ctaPlatforms as $platform): ?>
                <span class="CTA-composer--message"><?= htmlspecialchars($platform['message'], ENT_QUOTES, 'UTF-8') ?></span>
            <?php endforeach; ?>
            <span class="CTA-composer--typed" aria-hidden="true"><?= htmlspecialchars($ctaPlatforms[0]['message'], ENT_QUOTES, 'UTF-8') ?></span>
        </span>
        <button type="button" class="CTA-button">
            <span>Try Now</span>
            <i class="fa-regular fa-paper-plane"></i>
        </button>
    </div>
    <p class="CTA-description">Lorem ipsum dolor sit amet consectetur adipiscing elit</p>
</section>
