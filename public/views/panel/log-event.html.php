<?php
// the details use the styles of the list
$stylesheets[] = '/css/panel/logs.css';
$stylesheets[] = '/css/panel/log-event.css';
$scripts[] = '/js/panel/local-time.js';

$event = $logEvent['event'];
// null once the event is old enough for its author to be removed
$author = $logEvent['author'];
$action = $event->getAction();
$createdAt = $event->getCreatedAt()?->setTimezone(new DateTimeZone('UTC'));
$failure = $event->getFailure();
$actionLabel = $t(($failure === null ? 'logs.action.' : 'logs.failed.') . $action);
// the author has a card of their own above the details
$hideAuthor = true;
?>

<a href="/app/logs" class="log-event--back">
    <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
    <?= $t('logs.event.back') ?>
</a>

<article class="logs-event log-event is-<?= $e($action) ?><?= $failure === null ? '' : ' is-failed' ?>">
    <h2 class="log-event--title"><?= $actionLabel ?> · <?= $t('category.' . $event->getCategory()) ?></h2>

    <?php if ($author === null): ?>
        <p class="log-event--author is-gone"><?= $t('logs.event.author_gone') ?></p>
    <?php else: ?>
        <?php $authorName = $e($author['name']); ?>
        <div class="log-event--author">
            <?php include __DIR__ . '/../components/author-avatar.html.php'; ?>
            <div class="log-event--who">
                <?php if ($author['profileUrl'] === null): ?>
                    <span class="log-event--name"><?= $authorName ?></span>
                <?php else: ?>
                    <a href="<?= $e($author['profileUrl']) ?>" class="log-event--name" target="_blank" rel="noopener noreferrer">
                        <?= $authorName ?>
                        <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                        <span class="panel-hidden"><?= $t('logs.event.profile') ?></span>
                    </a>
                <?php endif; ?>
                <span class="logs-event--id">ID <?= $e($author['id']) ?></span>
            </div>
            <?php // this event included, so the count is at least 1 unless the event is older than the period ?>
            <p class="log-event--history">
                <?php if ($author['events'] === 0): ?>
                    <?= sprintf($t('logs.event.history_none'), $logEvent['historyDays']) ?>
                <?php else: ?>
                    <?= sprintf($t('logs.event.history'), $logEvent['historyDays'], $author['events']) ?>
                    <span class="log-event--rule"><?= sprintf($t('overview.most_often'), $t('category.' . $author['category'])) ?></span>
                <?php endif; ?>
            </p>
        </div>
    <?php endif; ?>

    <?php // the whole message, which the list cuts to one line ?>
    <?php if ($event->getContent() === null): ?>
        <p class="log-event--text is-gone"><?= $t('logs.no_content') ?></p>
    <?php else: ?>
        <p class="log-event--text"><?= $e($event->getContent()) ?></p>
    <?php endif; ?>
    <?php include __DIR__ . '/../components/log-event-details.html.php'; ?>
</article>
