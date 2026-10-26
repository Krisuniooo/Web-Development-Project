<!-- Maybe I should swap it to load from database later -->
<?php

$models = [
    [
        "label" => "Laya",
        "size" => "421M",
        "logo" => "/assets/models/laya.png",
    ],
    [
        "label" => "Basal-omni-pico",
        "size" => "0.8B",
        "logo" => "/assets/models/basal.png",
    ],
]

?>

<section class="models">
    <h2 class="models-title">Supported Models</h2>
    <p class="models-description">lorem ipsum dolor sit amet consectetur adipiscing elit</p>
    <div class="models-display">
        <ul class="models-orbit">
            <?php foreach($models as $model): ?>
                <li class="models-orbit-item">
                    <img
                        src="<?= htmlspecialchars($model['logo'], ENT_QUOTES, 'UTF-8') ?>"
                        alt=""
                        class="models-orbit-item--logo"
                        width="64"
                        height="64"
                    />
                    <span class="models-orbit-item--label">
                        <?= htmlspecialchars($model['label'], ENT_QUOTES, 'UTF-8') ?>
                    </span>
                    <span class="models-orbit-item--size">
                        <?= htmlspecialchars($model['size'], ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
        <a href="https://github.com/Krisuniooo/Web-Development-Project" class="models-display--center" target="_blank" rel="noopener noreferrer">
            <img src="/assets/icons/logo.svg" alt="APPNAME on GitHub" class="models-display--logo" width="112" height="56" />
        </a>
    </div>
</section>