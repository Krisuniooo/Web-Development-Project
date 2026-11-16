<?php $stylesheets[] = '/css/components/sidebar.css'; ?>

<aside class="sidebar">
    <div class="sidebar-account">
        <div class="sidebar-account--current">
            <?php if ($currentAccount['avatarUrl'] !== null): ?>
                <img src="<?= $e($currentAccount['avatarUrl']) ?>" alt="" class="sidebar-account--avatar" width="36" height="36" />
            <?php else: ?>
                <img src="/assets/icons/<?= $e($currentAccount['platform']) ?>.svg" alt="" class="sidebar-account--icon" width="20" height="20" />
            <?php endif; ?>
            <span class="sidebar-account--text">
                <span class="sidebar-account--name" title="<?= $e($currentAccount['name']) ?>"><?= $e($currentAccount['name']) ?></span>
                <?php if ($managedServer !== null): ?>
                    <span class="sidebar-account--platform" title="<?= $e($currentPlatformName . ' ' . $lang->text('account.managing') . ' ' . $managedServer['name']) ?>">
                        <?= $e($currentPlatformName) ?> <?= $t('account.managing') ?>
                        <span class="sidebar-account--server"><?= $e($managedServer['name']) ?></span>
                    </span>
                <?php else: ?>
                    <span class="sidebar-account--platform"><?= $e($currentPlatformName) ?></span>
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
                    <?php if ($item['locked']): // not a link until a server is chosen on the servers tab ?>
                        <span class="sidebar-nav--link is-locked" title="<?= $t('nav.choose_server') ?>">
                            <i class="fa-solid <?= $e($item['icon']) ?> sidebar-nav--icon" aria-hidden="true"></i>
                            <?= $t($item['name']) ?>
                        </span>
                    <?php else: ?>
                        <a href="<?= $e($item['path']) ?>" class="sidebar-nav--link<?= $key === $activeSection ? ' is-active' : '' ?>">
                            <i class="fa-solid <?= $e($item['icon']) ?> sidebar-nav--icon" aria-hidden="true"></i>
                            <?= $t($item['name']) ?>
                        </a>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>
</aside>
