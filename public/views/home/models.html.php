<?php $stylesheets[] = '/css/home/models.css'; ?>
<?php $scripts[] = '/js/model-orbit.js'; ?>

<section class="models">
    <h2 class="models-title">Supported Models</h2>
    <p class="models-description">lorem ipsum dolor sit amet consectetur adipiscing elit</p>
    <div class="models-display">
        <ul class="models-orbit">
            <?php foreach($models as $model): ?>
                <a href="<?= htmlspecialchars($model['github'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">
                    <li class="models-orbit-item<?= !empty($model['pro']) ? ' is-pro' : '' ?>">
                        <span class="models-orbit-item--media">
                            <img
                                src="<?= htmlspecialchars($model['logo'], ENT_QUOTES, 'UTF-8') ?>"
                                alt=""
                                class="models-orbit-item--logo"
                                width="64"
                                height="64"
                            />
                            <?php if (!empty($model['pro'])): ?>
                                <span class="models-orbit-item--pro" title="Available for PRO users only">PRO</span>
                            <?php elseif (!empty($model['recommended'])): ?>
                                <span class="models-orbit-item--recommended" role="img" aria-label="Recommended" title="Recommended">
                                    <svg viewBox="0 0 24 24" width="12" height="12" aria-hidden="true">
                                        <path fill="currentColor" d="M12 2.5l2.94 5.96 6.56.95-4.75 4.63 1.12 6.54L12 17.5l-5.87 3.08 1.12-6.54L2.5 9.41l6.56-.95z" />
                                    </svg>
                                </span>
                            <?php endif; ?>
                        </span>
                        <span class="models-orbit-item--label">
                            <?= htmlspecialchars($model['label'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                        <span class="models-orbit-item--size">
                            <?= htmlspecialchars($model['size'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </li>
                </a>
            <?php endforeach; ?>
        </ul>
        <a href="https://github.com/Krisuniooo/Web-Development-Project" class="models-display--center" target="_blank" rel="noopener noreferrer">
            <img src="/assets/icons/logo.svg" alt="APPNAME on GitHub" class="models-display--logo" width="112" height="56" />
        </a>
    </div>
</section>