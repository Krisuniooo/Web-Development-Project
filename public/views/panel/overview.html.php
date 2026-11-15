<?php
$stylesheets[] = '/css/panel/overview.css';

// each line: what it is about, whether it works (null when unknown) and the text for that state
$statusLines = [
    [$t('status.bot'), $overview['isBotConnected'], $overview['isBotConnected'] === null ? 'status.unknown' : ($overview['isBotConnected'] ? 'status.bot.ok' : 'status.bot.down')],
];

// one line for each model, with its name
foreach ($overview['models'] as $model) {
    $statusLines[] = [
        $t('status.model') . ' ' . htmlspecialchars($model['label'], ENT_QUOTES, 'UTF-8'),
        $model['isReady'],
        $model['isReady'] ? 'status.model.ok' : 'status.model.down',
    ];
}

$stats = $overview['stats'];
?>

<p class="panel-content--description"><?= $t('overview.lead') ?></p>

<section class="overview-section" aria-labelledby="overview-status-title">
    <h2 id="overview-status-title" class="overview-title"><?= $t('status.heading') ?></h2>
    <dl class="overview-status">
        <?php foreach ($statusLines as [$name, $isWorking, $text]): ?>
            <div class="overview-status--item">
                <dt><?= $name ?></dt>
                <dd class="panel-card--status<?= $isWorking === true ? ' is-active' : ($isWorking === false ? ' is-down' : '') ?>"><?= $t($text) ?></dd>
            </div>
        <?php endforeach; ?>
    </dl>
</section>

<?php // the period is a link, so a page with it can be bookmarked or sent ?>
<nav class="overview-periods" aria-label="<?= $t('overview.periods') ?>">
    <?php foreach ($overview['periods'] as $days): ?>
        <a href="/app?days=<?= (int) $days ?>" class="overview-periods--link<?= $days === $overview['days'] ? ' is-active' : '' ?>"><?= sprintf($t('overview.days'), $days) ?></a>
    <?php endforeach; ?>
</nav>

<?php if ($stats === null): ?>
    <p class="panel-error">
        <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
        <?= $t('overview.load_error') ?>
    </p>
<?php else: ?>
    <ul class="overview-tiles">
        <li class="overview-tile">
            <span class="overview-tile--value"><?= (int) $stats['checked'] ?></span>
            <span class="overview-tile--label"><?= $t('overview.checked') ?></span>
        </li>
        <li class="overview-tile">
            <span class="overview-tile--value"><?= (int) $stats['acted'] ?></span>
            <span class="overview-tile--label"><?= $t('overview.acted') ?></span>
        </li>
        <?php // a failed action means a bad message is still in the chat, so it stands out ?>
        <li class="overview-tile<?= $stats['failed'] > 0 ? ' is-error' : '' ?>">
            <span class="overview-tile--value"><?= (int) $stats['failed'] ?></span>
            <span class="overview-tile--label"><?= $t('overview.failed') ?></span>
        </li>
    </ul>

    <section class="overview-section" aria-labelledby="overview-channels-title">
        <h2 id="overview-channels-title" class="overview-title"><?= $t('overview.channels') ?></h2>
        <?php if ($stats['channels'] === []): ?>
            <p class="overview-empty">
                <?= $t('limits.empty') ?>
                <a href="/app/channels"><?= $t('limits.empty_link') ?></a>
            </p>
        <?php else: ?>
            <ul class="overview-list">
                <?php foreach ($stats['channels'] as $item): ?>
                    <?php
                    $channel = $item['channel'];
                    $name = htmlspecialchars($channel->getName(), ENT_QUOTES, 'UTF-8');
                    $status = htmlspecialchars($channel->getStatus(), ENT_QUOTES, 'UTF-8');
                    ?>
                    <li class="overview-row">
                        <span class="overview-row--name" title="#<?= $name ?>">#<?= $name ?></span>
                        <span class="overview-row--meta">
                            <span class="panel-card--status is-<?= $status ?>"><?= $t('channels.status.' . $channel->getStatus()) ?></span>
                            <span><?= htmlspecialchars($item['model'], ENT_QUOTES, 'UTF-8') ?></span>
                            <span><?= $t('language.' . $channel->getLanguage()) ?></span>
                        </span>
                        <span class="overview-row--count"><?= sprintf($t('overview.channel_counts'), $item['checked'], $item['acted']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="overview-section" aria-labelledby="overview-rules-title">
        <h2 id="overview-rules-title" class="overview-title"><?= $t('overview.rules') ?></h2>
        <?php $mostBroken = max($stats['categories'] === [] ? [0] : $stats['categories']); ?>
        <?php if ($mostBroken === 0): ?>
            <p class="overview-empty"><?= $t('overview.rules_empty') ?></p>
        <?php else: ?>
            <ul class="overview-list">
                <?php foreach ($stats['categories'] as $category => $count): ?>
                    <li class="overview-row">
                        <span class="overview-row--name"><?= $t('category.' . $category) ?></span>
                        <?php // the longest bar is the most broken rule; the width is the only style the page sets itself ?>
                        <span class="overview-bar" style="--share: <?= round($count / $mostBroken * 100) ?>%" aria-hidden="true"></span>
                        <span class="overview-row--count"><?= (int) $count ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="overview-section" aria-labelledby="overview-authors-title">
        <h2 id="overview-authors-title" class="overview-title"><?= $t('overview.authors') ?></h2>
        <?php if ($stats['authors'] === []): ?>
            <p class="overview-empty"><?= $t('overview.rules_empty') ?></p>
        <?php else: ?>
            <ol class="overview-list">
                <?php foreach ($stats['authors'] as $author): ?>
                    <?php $authorName = htmlspecialchars($author['name'], ENT_QUOTES, 'UTF-8'); ?>
                    <li class="overview-row">
                        <span class="overview-row--name" title="<?= $authorName ?>">
                            <?= $authorName ?>
                            <span class="overview-row--id">ID <?= htmlspecialchars($author['id'], ENT_QUOTES, 'UTF-8') ?></span>
                        </span>
                        <span class="overview-row--meta"><?= sprintf($t('overview.most_often'), $t('category.' . $author['category'])) ?></span>
                        <span class="overview-row--count"><?= sprintf($t($author['events'] === 1 ? 'overview.times_one' : 'overview.times'), $author['events']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ol>
            <p class="overview-note"><?= $t('overview.authors_note') ?></p>
        <?php endif; ?>
    </section>
<?php endif; ?>
