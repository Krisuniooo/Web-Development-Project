<?php $stylesheets[] = '/css/header.css'; ?>

<header class="nav">
    <a href="/" class="logo">
        <div class="logo-box">
            <img src="/assets/icons/logo.svg" alt="" class="logo-box-img" width="56" height="28" />
        </div>
        <div class="logo-name">APPNAME</div>
    </a>
    <div class="nav-right">
        <nav class="platforms" aria-label="Manage APPNAME">
            <div class="available-platforms-label">Manage APPNAME on</div>
            <ul class="available-platforms">
                <?php foreach ($platforms as $platform): ?>
                    <li>
                        <a href="#" class="available-platform <?= htmlspecialchars($platform['slug'], ENT_QUOTES, 'UTF-8') ?>">
                            <img src="/assets/icons/<?= htmlspecialchars($platform['slug'], ENT_QUOTES, 'UTF-8') ?>.svg" alt="<?= htmlspecialchars($platform['name'], ENT_QUOTES, 'UTF-8') ?>" class="available-platform--img" width="16" height="16" />
                            <div aria-hidden="true"><?= htmlspecialchars($platform['name'], ENT_QUOTES, 'UTF-8') ?></div>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>
        <div class="nav-settings">
            <div class="available-languages" role="group" aria-label="Language">
                <button type="button" class="available-language" aria-pressed="true">EN</button>
                <button type="button" class="available-language" aria-pressed="false">PL</button>
            </div>
            <div class="toggle-light-mode">
                <button type="button" class="switch-mode" aria-label="Switch to light mode">
                    <i class="fa-regular fa-sun switch-mode-icon switch-mode-icon--sun" aria-hidden="true"></i>
                    <i class="fa-regular fa-moon switch-mode-icon switch-mode-icon--moon" aria-hidden="true"></i>
                </button>
            </div>
        </div>
    </div>
</header>