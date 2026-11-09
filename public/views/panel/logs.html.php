<?php
$stylesheets[] = '/css/panel/logs.css';
$scripts[] = '/js/panel/local-time.js';

// an icon for each action, so the list can be skimmed by its left edge
$actionIcons = ['flag' => 'fa-flag', 'delete' => 'fa-trash-can', 'timeout' => 'fa-volume-xmark', 'ban' => 'fa-ban'];
?>

<p class="panel-content--description"><?= $t('logs.lead') ?></p>

<?php if ($log['events'] === null): ?>
    <p class="panel-error">
        <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
        <?= $t('logs.load_error') ?>
    </p>
<?php elseif ($log['events'] === []): ?>
    <p class="logs-empty">
        <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
        <?= $t($log['isNewest'] ? 'logs.empty' : 'logs.no_older') ?>
    </p>
<?php else: ?>
    <ol class="logs">
        <?php foreach ($log['events'] as $event): ?>
            <?php
            $action = $event->getAction();
            $createdAt = $event->getCreatedAt()?->setTimezone(new DateTimeZone('UTC'));
            $author = htmlspecialchars($event->getAuthorName(), ENT_QUOTES, 'UTF-8');
            ?>
            <li class="logs-event is-<?= htmlspecialchars($action, ENT_QUOTES, 'UTF-8') ?>">
                <details>
                    <?php // one line: what happened, to whom, the message and when; a click opens the rest ?>
                    <summary class="logs-event--row">
                        <span class="logs-event--icon" title="<?= $t('logs.action.' . $action) ?>">
                            <i class="fa-solid <?= $actionIcons[$action] ?? 'fa-circle' ?>" aria-hidden="true"></i>
                            <span class="panel-hidden"><?= $t('logs.action.' . $action) ?></span>
                        </span>
                        <span class="logs-event--author" title="<?= $author ?>"><?= $author ?></span>
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
                            <dd><?= $author ?> <span class="logs-event--id">ID <?= htmlspecialchars($event->getAuthorPlatformId(), ENT_QUOTES, 'UTF-8') ?></span></dd>
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
            <a href="/app/logs" class="logs-pages--link">
                <i class="fa-solid fa-arrow-up" aria-hidden="true"></i>
                <?= $t('logs.newest') ?>
            </a>
        <?php endif; ?>
        <?php if ($log['olderBefore'] !== null): ?>
            <a href="/app/logs?before=<?= (int) $log['olderBefore'] ?>" class="logs-pages--link">
                <?= $t('logs.older') ?>
                <i class="fa-solid fa-arrow-down" aria-hidden="true"></i>
            </a>
        <?php endif; ?>
    </nav>
<?php endif; ?>
