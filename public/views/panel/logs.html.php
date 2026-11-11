<?php
$stylesheets[] = '/css/panel/logs.css';
$scripts[] = '/js/panel/local-time.js';

// an icon for each action, so the list can be skimmed by its left edge
$actionIcons = ['flag' => 'fa-flag', 'delete' => 'fa-trash-can', 'timeout' => 'fa-volume-xmark', 'ban' => 'fa-ban'];
$filters = $log['filters'];
// the chosen filters stay in the links to the other pages
$pageUrl = fn(array $more = []): string => htmlspecialchars(
    '/app/logs' . ($filters + $more === [] ? '' : '?' . http_build_query($filters + $more)),
    ENT_QUOTES,
    'UTF-8',
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
                        <option value="<?= htmlspecialchars($choice['id'], ENT_QUOTES, 'UTF-8') ?>"<?= $choice['id'] === ($filters['channel'] ?? null) ? ' selected' : '' ?>>#<?= htmlspecialchars($choice['name'], ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        <?php endif; ?>
        <label class="logs-filter">
            <span class="logs-filter--label"><?= $t('logs.detail.rule') ?></span>
            <select name="category" class="logs-filter--select">
                <option value=""><?= $t('logs.filter.all') ?></option>
                <?php foreach ($log['choices']['category'] as $choice): ?>
                    <option value="<?= htmlspecialchars($choice, ENT_QUOTES, 'UTF-8') ?>"<?= $choice === ($filters['category'] ?? null) ? ' selected' : '' ?>><?= $t('category.' . $choice) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="logs-filter">
            <span class="logs-filter--label"><?= $t('logs.detail.action') ?></span>
            <select name="action" class="logs-filter--select">
                <option value=""><?= $t('logs.filter.all') ?></option>
                <?php foreach ($log['choices']['action'] as $choice): ?>
                    <option value="<?= htmlspecialchars($choice, ENT_QUOTES, 'UTF-8') ?>"<?= $choice === ($filters['action'] ?? null) ? ' selected' : '' ?>><?= $t('logs.action.' . $choice) ?></option>
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
    <p class="panel-error">
        <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
        <?= $t('logs.load_error') ?>
    </p>
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
            $author = $event->getAuthorName() === null ? null : htmlspecialchars($event->getAuthorName(), ENT_QUOTES, 'UTF-8');
            ?>
            <li class="logs-event is-<?= htmlspecialchars($action, ENT_QUOTES, 'UTF-8') ?>">
                <details>
                    <?php // one line: what happened, to whom, the message and when; a click opens the rest ?>
                    <summary class="logs-event--row">
                        <span class="logs-event--icon" title="<?= $t('logs.action.' . $action) ?>">
                            <i class="fa-solid <?= $actionIcons[$action] ?? 'fa-circle' ?>" aria-hidden="true"></i>
                            <span class="panel-hidden"><?= $t('logs.action.' . $action) ?></span>
                        </span>
                        <?php if ($author === null): ?>
                            <span class="logs-event--author is-gone"><?= $t('logs.no_author') ?></span>
                        <?php else: ?>
                            <span class="logs-event--author" title="<?= $author ?>"><?= $author ?></span>
                        <?php endif; ?>
                        <?php if ($event->getContent() === null): ?>
                            <span class="logs-event--text is-gone"><?= $t('logs.no_content') ?></span>
                        <?php else: ?>
                            <span class="logs-event--text"><?= htmlspecialchars($event->getContent(), ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                        <span class="logs-event--category"><?= $t('category.' . $event->getCategory()) ?></span>
                        <?php if ($createdAt !== null): ?>
                            <?php // in UTC here; local-time.js shows it in the viewer's own time zone ?>
                            <time class="logs-event--time" datetime="<?= $createdAt->format(DATE_ATOM) ?>" data-local="short"><?= $createdAt->format('d.m H:i') ?></time>
                        <?php endif; ?>
                        <i class="fa-solid fa-chevron-down logs-event--chevron" aria-hidden="true"></i>
                    </summary>
                    <dl class="logs-event--details">
                        <div>
                            <dt><?= $t('logs.detail.action') ?></dt>
                            <dd><?= $t('logs.action.' . $action) ?></dd>
                        </div>
                        <div>
                            <dt><?= $t('logs.detail.rule') ?></dt>
                            <dd><?= $t('category.' . $event->getCategory()) ?> (<?= $t('level.' . $event->getLevel()) ?>)</dd>
                        </div>
                        <?php if ($event->getScore() !== null): ?>
                            <div>
                                <dt><?= $t('logs.detail.score') ?></dt>
                                <dd><?= (int) round($event->getScore() * 100) ?>%</dd>
                            </div>
                        <?php endif; ?>
                        <div>
                            <dt><?= $t('logs.detail.channel') ?></dt>
                            <dd>#<?= htmlspecialchars($event->getChannelName(), ENT_QUOTES, 'UTF-8') ?></dd>
                        </div>
                        <div>
                            <dt><?= $t('logs.detail.author') ?></dt>
                            <?php if ($author === null): ?>
                                <dd><?= $t('logs.no_author') ?></dd>
                            <?php else: ?>
                                <dd><?= $author ?> <span class="logs-event--id">ID <?= htmlspecialchars((string) $event->getAuthorPlatformId(), ENT_QUOTES, 'UTF-8') ?></span></dd>
                            <?php endif; ?>
                        </div>
                        <?php if ($createdAt !== null): ?>
                            <div>
                                <dt><?= $t('logs.detail.time') ?></dt>
                                <dd><time datetime="<?= $createdAt->format(DATE_ATOM) ?>" data-local="full"><?= $createdAt->format('Y-m-d H:i:s') ?> UTC</time></dd>
                            </div>
                        <?php endif; ?>
                        <div>
                            <dt><?= $t('logs.detail.model') ?></dt>
                            <dd><?= htmlspecialchars($event->getModel(), ENT_QUOTES, 'UTF-8') ?></dd>
                        </div>
                    </dl>
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
