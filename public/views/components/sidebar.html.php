<?php $stylesheets[] = '/css/sidebar.css'; ?>

<aside class="sidebar">
    <div class="sidebar-account">
        <div class="sidebar-account--current">
            <img src="/assets/icons/<?= htmlspecialchars($currentAccount['platform'], ENT_QUOTES, 'UTF-8') ?>.svg" alt="" class="sidebar-account--icon" width="20" height="20" />
            <span class="sidebar-account--text">
                <span class="sidebar-account--name" title="<?= htmlspecialchars($currentAccount['name'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($currentAccount['name'], ENT_QUOTES, 'UTF-8') ?></span>
                <span class="sidebar-account--platform"><?= htmlspecialchars($currentPlatformName, ENT_QUOTES, 'UTF-8') ?></span>
            </span>
        </div>
        <form method="post" action="/logout">
            <button type="submit" class="sidebar-account--logout"><?= $t('account.logout') ?></button>
        </form>
    </div>
    <nav class="sidebar-nav" aria-label="<?= $t('nav.panel') ?>">
        <ul class="sidebar-nav--list">
            <?php foreach ($navigation as $key => $item): ?>
                <li>
                    <a href="<?= htmlspecialchars($item['path'], ENT_QUOTES, 'UTF-8') ?>" class="sidebar-nav--link<?= $key === $activeSection ? ' is-active' : '' ?>">
                        <i class="fa-solid <?= htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8') ?> sidebar-nav--icon" aria-hidden="true"></i>
                        <?= $t($item['name']) ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>
</aside>
