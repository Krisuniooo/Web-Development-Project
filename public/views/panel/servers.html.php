<?php $stylesheets[] = '/css/panel/servers.css'; ?>

<p class="panel-content--description"><?= $t('servers.lead') ?></p>
<?php include __DIR__ . '/../components/panel-notice.html.php'; ?>

<section class="servers" aria-labelledby="servers-title">
    <div class="servers-header">
        <h2 id="servers-title" class="servers-title">
            <?= $t('servers.title') ?>
            <?php if ($servers !== []): ?>
                <span class="servers-count">· <?= count($servers) ?></span>
            <?php endif; ?>
        </h2>
        <a href="<?= $e($refreshPath) ?>" class="servers-refresh">
            <i class="fa-solid fa-rotate-right" aria-hidden="true"></i>
            <?= $t('servers.refresh') ?>
        </a>
    </div>
    <?php if ($servers === []): ?>
        <p class="servers-empty"><?= $t('servers.empty') ?></p>
    <?php else: ?>
        <ul class="servers-grid">
            <?php foreach ($servers as $server): ?>
                <li class="servers-card<?= $server['isManaged'] ? ' is-managed' : '' ?>">
                    <?php if ($server['iconUrl'] !== null): ?>
                        <img src="<?= $e($server['iconUrl']) ?>" alt="" class="servers-card--icon" width="64" height="64" loading="lazy" />
                    <?php else: ?>
                        <span class="servers-card--icon" aria-hidden="true"><?= $e($server['initials']) ?></span>
                    <?php endif; ?>
                    <span class="servers-card--name" title="<?= $e($server['name']) ?>"><?= $e($server['name']) ?></span>
                    <?php if ($server['isManaged']): ?>
                        <span class="servers-card--status">
                            <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                            <?= $t('servers.managed') ?>
                        </span>
                    <?php elseif ($server['hasBot']): ?>
                        <form method="post" action="/app/manage" class="servers-card--form">
                            <input type="hidden" name="server" value="<?= $e($server['id']) ?>" />
                            <button type="submit" class="servers-card--manage"><?= $t('servers.manage') ?></button>
                        </form>
                    <?php elseif ($server['inviteUrl'] !== null): ?>
                        <a href="<?= $e($server['inviteUrl']) ?>" class="servers-card--invite" target="_blank" rel="noopener noreferrer">
                            <?= $t('servers.add_bot') ?>
                            <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                        </a>
                    <?php elseif ($server['canConnect']): ?>
                        <?php // no invite on this platform: the owner connects the channel here, and the bot becomes its moderator ?>
                        <form method="post" action="/app/servers/connect" class="servers-card--form">
                            <input type="hidden" name="server" value="<?= $e($server['id']) ?>" />
                            <button type="submit" class="servers-card--invite">
                                <i class="fa-solid fa-plus" aria-hidden="true"></i>
                                <?= $t('servers.connect') ?>
                            </button>
                        </form>
                    <?php else: ?>
                        <?php // a moderator sees the channel, but cannot allow the bot to moderate it ?>
                        <p class="servers-card--note"><?= $t('servers.owner_only') ?></p>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
