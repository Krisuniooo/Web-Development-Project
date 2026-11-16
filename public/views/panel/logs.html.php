<?php
$stylesheets[] = '/css/panel/logs.css';
$scripts[] = '/js/panel/local-time.js';

// an icon for each action, so the list can be skimmed by its left edge
$actionIcons = ['flag' => 'fa-flag', 'delete' => 'fa-trash-can', 'timeout' => 'fa-volume-xmark', 'ban' => 'fa-ban'];
$filters = $log['filters'];
// the chosen filters stay in the links to the other pages
$pageUrl = fn(array $more = []): string => $e(
    '/app/logs' . ($filters + $more === [] ? '' : '?' . http_build_query($filters + $more)),
);
?>

<p class="panel-content--description"><?= $t('logs.lead') ?></p>

<?php if ($log['events'] !== null): ?>
    <form method="get" action="/app/logs" class="logs-filters">
        <?php // with one channel there is nothing to choose between ?>
        <?php if (count($log['choices']['channel']) > 1): ?>
            <label class="logs-filter">
                <span class="logs-filter--label"><?= $t('logs.detail.channel') ?></span>
                <select name="channel" class="logs-filter--select">
                    <option value=""><?= $t('logs.filter.all') ?></option>
                    <?php foreach ($log['choices']['channel'] as $choice): ?>
                        <option value="<?= $e($choice['id']) ?>"<?= $choice['id'] === ($filters['channel'] ?? null) ? ' selected' : '' ?>>#<?= $e($choice['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        <?php endif; ?>
        <label class="logs-filter">
            <span class="logs-filter--label"><?= $t('logs.detail.rule') ?></span>
            <select name="category" class="logs-filter--select">
                <option value=""><?= $t('logs.filter.all') ?></option>
                <?php foreach ($log['choices']['category'] as $choice): ?>
                    <option value="<?= $e($choice) ?>"<?= $choice === ($filters['category'] ?? null) ? ' selected' : '' ?>><?= $t('category.' . $choice) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="logs-filter">
            <span class="logs-filter--label"><?= $t('logs.detail.action') ?></span>
            <select name="action" class="logs-filter--select">
                <option value=""><?= $t('logs.filter.all') ?></option>
                <?php foreach ($log['choices']['action'] as $choice): ?>
                    <option value="<?= $e($choice) ?>"<?= $choice === ($filters['action'] ?? null) ? ' selected' : '' ?>><?= $t('logs.action.' . $choice) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <div class="logs-filters--buttons">
            <button type="submit" class="panel-button is-primary">
                <i class="fa-solid fa-filter" aria-hidden="true"></i>
                <?= $t('logs.filter.submit') ?>
            </button>
            <?php if ($filters !== []): ?>
                <a href="/app/logs" class="panel-button is-quiet"><?= $t('logs.filter.clear') ?></a>
            <?php endif; ?>
        </div>
    </form>
<?php endif; ?>

<?php if ($log['events'] === null): ?>
    <?php $errorText = 'logs.load_error'; ?>
    <?php include __DIR__ . '/../components/panel-error.html.php'; ?>
<?php elseif ($log['events'] === []): ?>
    <p class="logs-empty">
        <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
        <?= $t(!$log['isNewest'] ? 'logs.no_older' : ($filters === [] ? 'logs.empty' : 'logs.no_match')) ?>
    </p>
<?php else: ?>
    <ol class="logs">
        <?php foreach ($log['events'] as $event): ?>
            <?php
            $action = $event->getAction();
            $createdAt = $event->getCreatedAt()?->setTimezone(new DateTimeZone('UTC'));
            // null once the event is old enough for its text and author to be removed
            $author = $event->getAuthorName() === null ? null : $e($event->getAuthorName());
            // an action the platform refused: the message is still in the chat, so the line says "not deleted" and why
            $failure = $event->getFailure();
            $actionLabel = $t(($failure === null ? 'logs.action.' : 'logs.failed.') . $action);
            ?>
            <li class="logs-event is-<?= $e($action) ?><?= $failure === null ? '' : ' is-failed' ?>">
                <details>
                    <?php // one line: what happened, to whom, the message and when; a click opens the rest ?>
                    <summary class="logs-event--row">
                        <span class="logs-event--icon" title="<?= $actionLabel ?>">
                            <i class="fa-solid <?= $failure === null ? ($actionIcons[$action] ?? 'fa-circle') : 'fa-triangle-exclamation' ?>" aria-hidden="true"></i>
                            <span class="panel-hidden"><?= $actionLabel ?></span>
                        </span>
                        <?php if ($author === null): ?>
                            <span class="logs-event--author is-gone"><?= $t('logs.no_author') ?></span>
                        <?php else: ?>
                            <span class="logs-event--author" title="<?= $author ?>"><?= $author ?></span>
                        <?php endif; ?>
                        <?php if ($event->getContent() === null): ?>
                            <span class="logs-event--text is-gone"><?= $t('logs.no_content') ?></span>
                        <?php else: ?>
                            <span class="logs-event--text"><?= $e($event->getContent()) ?></span>
                        <?php endif; ?>
                        <span class="logs-event--category"><?= $t('category.' . $event->getCategory()) ?></span>
                        <?php if ($createdAt !== null): ?>
                            <?php // in UTC here; local-time.js shows it in the viewer's own time zone ?>
                            <time class="logs-event--time" datetime="<?= $createdAt->format(DATE_ATOM) ?>" data-local="short"><?= $createdAt->format('d.m H:i') ?></time>
                        <?php endif; ?>
                        <i class="fa-solid fa-chevron-down logs-event--chevron" aria-hidden="true"></i>
                    </summary>
                    <?php include __DIR__ . '/../components/log-event-details.html.php'; ?>
                    <?php // its own address, to send to another moderator ?>
                    <a href="/app/logs/<?= (int) $event->getId() ?>" class="logs-event--open">
                        <?= $t('logs.event.open') ?>
                        <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                    </a>
                </details>
            </li>
        <?php endforeach; ?>
    </ol>
<?php endif; ?>

<?php if (!$log['isNewest'] || $log['olderBefore'] !== null): ?>
    <nav class="logs-pages" aria-label="<?= $t('logs.pages') ?>">
        <?php if (!$log['isNewest']): ?>
            <a href="<?= $pageUrl() ?>" class="logs-pages--link">
                <i class="fa-solid fa-arrow-up" aria-hidden="true"></i>
                <?= $t('logs.newest') ?>
            </a>
        <?php endif; ?>
        <?php if ($log['olderBefore'] !== null): ?>
            <a href="<?= $pageUrl(['before' => (int) $log['olderBefore']]) ?>" class="logs-pages--link">
                <?= $t('logs.older') ?>
                <i class="fa-solid fa-arrow-down" aria-hidden="true"></i>
            </a>
        <?php endif; ?>
    </nav>
<?php endif; ?>
