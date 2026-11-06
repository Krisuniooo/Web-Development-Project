<?php $stylesheets[] = '/css/sidebar.css'; ?>

<aside class="sidebar">
    <div class="sidebar-account">
        <div class="sidebar-account--current">
            <?php if ($currentAccount['avatarUrl'] !== null): ?>
                <img src="<?= htmlspecialchars($currentAccount['avatarUrl'], ENT_QUOTES, 'UTF-8') ?>" alt="" class="sidebar-account--avatar" width="36" height="36" />
            <?php else: ?>
                <img src="/assets/icons/<?= htmlspecialchars($currentAccount['platform'], ENT_QUOTES, 'UTF-8') ?>.svg" alt="" class="sidebar-account--icon" width="20" height="20" />
            <?php endif; ?>
            <span class="sidebar-account--text">
                <span class="sidebar-account--name" title="<?= htmlspecialchars($currentAccount['name'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($currentAccount['name'], ENT_QUOTES, 'UTF-8') ?></span>
                <?php if ($managedServer !== null): ?>
                    <span class="sidebar-account--platform" title="<?= htmlspecialchars($currentPlatformName . ' ' . $lang->text('account.managing') . ' ' . $managedServer['name'], ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars($currentPlatformName, ENT_QUOTES, 'UTF-8') ?> <?= $t('account.managing') ?>
                        <span class="sidebar-account--server"><?= htmlspecialchars($managedServer['name'], ENT_QUOTES, 'UTF-8') ?></span>
                    </span>
                <?php else: ?>
                    <span class="sidebar-account--platform"><?= htmlspecialchars($currentPlatformName, ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
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
                    <?php if ($item['locked']): // not a link until a server is chosen in the overview ?>
                        <span class="sidebar-nav--link is-locked" title="<?= $t('nav.choose_server') ?>">
                            <i class="fa-solid <?= htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8') ?> sidebar-nav--icon" aria-hidden="true"></i>
                            <?= $t($item['name']) ?>
                        </span>
                    <?php else: ?>
                        <a href="<?= htmlspecialchars($item['path'], ENT_QUOTES, 'UTF-8') ?>" class="sidebar-nav--link<?= $key === $activeSection ? ' is-active' : '' ?>">
                            <i class="fa-solid <?= htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8') ?> sidebar-nav--icon" aria-hidden="true"></i>
                            <?= $t($item['name']) ?>
                        </a>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>
</aside>
