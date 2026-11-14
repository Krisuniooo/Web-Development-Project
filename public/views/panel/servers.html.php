<?php $stylesheets[] = '/css/panel/servers.css'; ?>

<p class="panel-content--description"><?= $t('servers.lead') ?></p>

<?php
// each line: what it is about, whether it works (null when unknown) and the text for that state
$statusLines = [
    ['status.bot', $status['isBotConnected'], $status['isBotConnected'] === null ? 'status.unknown' : ($status['isBotConnected'] ? 'status.bot.ok' : 'status.bot.down')],
    ['status.model', $status['isModelReady'], $status['isModelReady'] ? 'status.model.ok' : 'status.model.down'],
];
?>
<section class="servers-status" aria-labelledby="servers-status-title">
    <h2 id="servers-status-title" class="servers-title"><?= $t('status.heading') ?></h2>
    <dl class="servers-status--list">
        <?php foreach ($statusLines as [$name, $isWorking, $text]): ?>
            <div class="servers-status--item">
                <dt><?= $t($name) ?></dt>
                <dd class="panel-card--status<?= $isWorking === true ? ' is-active' : ($isWorking === false ? ' is-down' : '') ?>"><?= $t($text) ?></dd>
            </div>
        <?php endforeach; ?>
        <?php if ($status['checked'] !== null && $status['acted'] !== null): ?>
            <div class="servers-status--item">
                <dt><?= sprintf($t('status.days'), $status['days']) ?></dt>
                <dd><?= sprintf($t('status.counts'), $status['checked'], $status['acted']) ?></dd>
            </div>
        <?php endif; ?>
    </dl>
</section>
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
                <li class="servers-card<?= $server['isManaged'] ? ' is-managed' : '' ?>">
                    <?php if ($server['iconUrl'] !== null): ?>
                        <img src="<?= htmlspecialchars($server['iconUrl'], ENT_QUOTES, 'UTF-8') ?>" alt="" class="servers-card--icon" width="64" height="64" loading="lazy" />
                    <?php else: ?>
                        <span class="servers-card--icon" aria-hidden="true"><?= htmlspecialchars($server['initials'], ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                    <span class="servers-card--name" title="<?= htmlspecialchars($server['name'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($server['name'], ENT_QUOTES, 'UTF-8') ?></span>
                    <?php if ($server['isManaged']): ?>
                        <span class="servers-card--status">
                            <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                            <?= $t('servers.managed') ?>
                        </span>
                    <?php elseif ($server['hasBot']): ?>
                        <form method="post" action="/app/manage" class="servers-card--form">
                            <input type="hidden" name="server" value="<?= htmlspecialchars($server['id'], ENT_QUOTES, 'UTF-8') ?>" />
                            <button type="submit" class="servers-card--manage"><?= $t('servers.manage') ?></button>
                        </form>
                    <?php else: ?>
                        <a href="<?= htmlspecialchars($server['inviteUrl'], ENT_QUOTES, 'UTF-8') ?>" class="servers-card--invite" target="_blank" rel="noopener noreferrer">
                            <?= $t('servers.add_bot') ?>
                            <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                        </a>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
