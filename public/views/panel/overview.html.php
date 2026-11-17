<?php
$stylesheets[] = '/css/panel/overview.css';

// each line: its icon, its name with what it is under it, its state (ok, down or unknown),
// the short text for that state, and for a state that is not ok what it means for the chat, as safe HTML
$state = fn(?bool $isWorking): string => $isWorking === null ? 'unknown' : ($isWorking ? 'ok' : 'down');
$bot = $state($overview['isBotConnected']);
$botText = $bot === 'unknown' ? 'status.unknown' : 'status.bot.' . $bot;
$botHint = ['ok' => null, 'down' => $t('status.bot.down_hint'), 'unknown' => $t('status.unknown_hint')][$bot];

// the bot is connected, but on this channel nobody lets it delete anything, so it does not check the chat here
if ($bot === 'ok' && $botIsModerator === false && $botName !== null) {
    $bot = 'down';
    $botText = 'status.bot.not_moderator';
    $botHint = sprintf($t('status.bot.not_moderator_hint'), $e($botName));
}

$statusLines = [['fa-robot', $t('status.bot'), $e($currentPlatformName), $bot, $botText, $botHint]];

foreach ($overview['models'] as $model) {
    $statusLines[] = [
        'fa-microchip',
        $e($model['label']),
        $t('status.model.kind'),
        $model['isReady'] ? 'ok' : 'down',
        $model['isReady'] ? 'status.model.ok' : 'status.model.down',
        $model['isReady'] ? null : $t('status.model.down_hint'),
    ];
}

$stats = $overview['stats'];
?>

<p class="panel-content--description"><?= $t('overview.lead') ?></p>

<div class="overview-top">
    <?php // no visible title: the dots say enough, and a screen reader still hears what the group is ?>
    <section class="overview-card is-status" aria-label="<?= $t('status.heading') ?>">
        <ul class="overview-card--rows">
            <?php foreach ($statusLines as [$icon, $name, $caption, $lineState, $text, $hint]): ?>
                <li class="overview-card--row overview-service is-<?= $lineState ?>">
                    <span class="overview-number--icon" aria-hidden="true"><i class="fa-solid <?= $icon ?>"></i></span>
                    <span class="overview-service--who">
                        <span class="overview-service--name"><?= $name ?></span>
                        <span class="overview-service--caption"><?= $caption ?></span>
                    </span>
                    <span class="overview-pill is-<?= $lineState ?>"><?= $t($text) ?></span>
                    <?php if ($hint !== null): ?>
                        <span class="overview-service--hint"><?= $hint ?></span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>

    <section class="overview-card" aria-labelledby="overview-period-title">
        <div class="overview-head">
            <h2 id="overview-period-title" class="overview-card--title"><?= sprintf($t('overview.period_title'), $overview['days']) ?></h2>
            <?php // the period is a link, so a page with it can be bookmarked or sent ?>
            <nav class="overview-periods" aria-label="<?= $t('overview.periods') ?>">
                <?php foreach ($overview['periods'] as $days): ?>
                    <a href="/app?days=<?= (int) $days ?>" class="overview-periods--link<?= $days === $overview['days'] ? ' is-active' : '' ?>"><?= sprintf($t('overview.days'), $days) ?></a>
                <?php endforeach; ?>
            </nav>
        </div>

        <?php if ($stats === null): ?>
            <?php $errorText = 'overview.load_error'; ?>
            <?php include __DIR__ . '/../components/panel-error.html.php'; ?>
        <?php else: ?>
            <?php
            // each number: its icon, what it counts and the value; a failed action means a bad message is still in the chat
            $numbers = [
                ['fa-magnifying-glass', 'overview.checked', $stats['checked'], false],
                ['fa-shield-halved', 'overview.acted', $stats['acted'], false],
                ['fa-triangle-exclamation', 'overview.failed', $stats['failed'], $stats['failed'] > 0],
            ];
            ?>
            <ul class="overview-card--rows">
                <?php foreach ($numbers as [$icon, $label, $value, $isError]): ?>
                    <li class="overview-card--row overview-number<?= $isError ? ' is-error' : '' ?>">
                        <span class="overview-number--icon" aria-hidden="true"><i class="fa-solid <?= $icon ?>"></i></span>
                        <span class="overview-card--label"><?= $t($label) ?></span>
                        <span class="overview-number--value"><?= (int) $value ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>

<?php if ($stats !== null): ?>
    <section class="overview-section" aria-labelledby="overview-channels-title">
        <h2 id="overview-channels-title" class="overview-title"><?= $t('overview.channels') ?></h2>
        <?php if ($stats['channels'] === []): ?>
            <?php include __DIR__ . '/../components/no-channels.html.php'; ?>
        <?php else: ?>
            <ul class="overview-list">
                <?php foreach ($stats['channels'] as $item): ?>
                    <?php
                    $channel = $item['channel'];
                    $name = $e($channel->getName());
                    $status = $e($channel->getStatus());
                    ?>
                    <li class="overview-row">
                        <span class="overview-row--name" title="#<?= $name ?>">#<?= $name ?></span>
                        <span class="overview-row--meta">
                            <span class="panel-card--status is-<?= $status ?>"><?= $t('channels.status.' . $channel->getStatus()) ?></span>
                            <span><?= $e($item['model']) ?></span>
                            <span><?= $t('language.' . $channel->getLanguage()) ?></span>
                        </span>
                        <span class="overview-row--count"><?= sprintf($t('overview.channel_counts'), $item['checked'], $item['acted']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <div class="overview-columns">
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
                        <?php // the most broken rule has the longest bar ?>
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
                <?php foreach ($stats['authors'] as $place => $author): ?>
                    <?php $authorName = $e($author['name']); ?>
                    <li class="overview-author">
                        <span class="overview-author--place"><?= $place + 1 ?></span>
                        <?php include __DIR__ . '/../components/author-avatar.html.php'; ?>
                        <span class="overview-author--who">
                            <span class="overview-author--name" title="<?= $authorName ?>"><?= $authorName ?></span>
                            <span class="overview-author--id">ID <?= $e($author['id']) ?></span>
                        </span>
                        <span class="overview-author--rule" title="<?= sprintf($t('overview.most_often'), $t('category.' . $author['category'])) ?>"><?= $t('category.' . $author['category']) ?></span>
                        <span class="overview-row--count"><?= sprintf($t($author['events'] === 1 ? 'overview.times_one' : 'overview.times'), $author['events']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ol>
            <p class="overview-note"><?= $t('overview.authors_note') ?></p>
        <?php endif; ?>
    </section>
    </div>
<?php endif; ?>
