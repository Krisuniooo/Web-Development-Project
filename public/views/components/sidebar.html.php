<?php $stylesheets[] = '/css/sidebar.css'; ?>

<aside class="sidebar">
    <a href="/" class="sidebar-logo">
        <img src="/assets/icons/logo.svg" alt="" class="sidebar-logo--img" width="56" height="28" />
        <span class="sidebar-logo--name">APPNAME</span>
    </a>
    <nav class="sidebar-nav" aria-label="Panel">
        <ul class="sidebar-nav--list">
            <?php foreach ($navigation as $key => $item): ?>
                <li>
                    <a href="<?= htmlspecialchars($item['path'], ENT_QUOTES, 'UTF-8') ?>" class="sidebar-nav--link<?= $key === $activeSection ? ' is-active' : '' ?>">
                        <i class="fa-solid <?= htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8') ?> sidebar-nav--icon" aria-hidden="true"></i>
                        <?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>
</aside>
