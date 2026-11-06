<?php $stylesheets[] = '/css/servers.css'; ?>

<p class="panel-content--description"><?= $t('servers.lead') ?></p>
<section class="servers" aria-labelledby="servers-title">
    <div class="servers-header">
        <h2 id="servers-title" class="servers-title">
            <?= $t('servers.title') ?>
            <?php if ($servers !== []): ?>
                <span class="servers-count">· <?= count($servers) ?></span>
            <?php endif; ?>
        </h2>
        <a href="/auth/discord" class="servers-refresh">
            <i class="fa-solid fa-rotate-right" aria-hidden="true"></i>
            <?= $t('servers.refresh') ?>
        </a>
    </div>
    <?php if ($servers === []): ?>
        <p class="servers-empty"><?= $t('servers.empty') ?></p>
    <?php else: ?>
        <ul class="servers-grid">
            <?php foreach ($servers as $server): ?>
                <li class="servers-card">
                    <?php if ($server['iconUrl'] !== null): ?>
                        <img src="<?= htmlspecialchars($server['iconUrl'], ENT_QUOTES, 'UTF-8') ?>" alt="" class="servers-card--icon" width="64" height="64" loading="lazy" />
                    <?php else: ?>
                        <span class="servers-card--icon" aria-hidden="true"><?= htmlspecialchars($server['initials'], ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                    <span class="servers-card--name" title="<?= htmlspecialchars($server['name'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($server['name'], ENT_QUOTES, 'UTF-8') ?></span>
                    <a href="<?= htmlspecialchars($server['inviteUrl'], ENT_QUOTES, 'UTF-8') ?>" class="servers-card--invite" target="_blank" rel="noopener noreferrer">
                        <?= $t('servers.add_bot') ?>
                        <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
