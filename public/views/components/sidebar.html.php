<?php $stylesheets[] = '/css/sidebar.css'; ?>

<aside class="sidebar">
    <details class="sidebar-account">
        <summary class="sidebar-account--current">
            <img src="/assets/icons/<?= htmlspecialchars($currentAccount['platform'], ENT_QUOTES, 'UTF-8') ?>.svg" alt="" class="sidebar-account--icon" width="20" height="20" />
            <span class="sidebar-account--text">
                <span class="sidebar-account--name"><?= htmlspecialchars($currentAccount['name'], ENT_QUOTES, 'UTF-8') ?></span>
                <span class="sidebar-account--platform">on <?= htmlspecialchars($currentAccount['platformName'], ENT_QUOTES, 'UTF-8') ?></span>
            </span>
            <i class="fa-solid fa-chevron-down sidebar-account--chevron" aria-hidden="true"></i>
        </summary>
        <form method="post" action="/app/account" class="sidebar-account--switch">
            <input type="hidden" name="section" value="<?= htmlspecialchars($activeSection, ENT_QUOTES, 'UTF-8') ?>" />
            <p class="sidebar-account--label">Switch account</p>
            <ul class="sidebar-account--list">
                <?php foreach ($otherAccounts as $accountId => $account): ?>
                    <li>
                        <button type="submit" name="account" value="<?= (int) $accountId ?>" class="sidebar-account--option">
                            <img src="/assets/icons/<?= htmlspecialchars($account['platform'], ENT_QUOTES, 'UTF-8') ?>.svg" alt="" class="sidebar-account--icon" width="20" height="20" />
                            <span class="sidebar-account--text">
                                <span class="sidebar-account--name"><?= htmlspecialchars($account['name'], ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="sidebar-account--platform">on <?= htmlspecialchars($account['platformName'], ENT_QUOTES, 'UTF-8') ?></span>
                            </span>
                        </button>
                    </li>
                <?php endforeach; ?>
            </ul>
        </form>
    </details>
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
