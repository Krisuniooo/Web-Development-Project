<?php
$stylesheets[] = '/css/logs.css';
$scripts[] = '/js/local-time.js';

// an icon for each action, so the list can be skimmed by its left edge
$actionIcons = ['flag' => 'fa-flag', 'delete' => 'fa-trash-can', 'timeout' => 'fa-volume-xmark', 'ban' => 'fa-ban'];
?>

<p class="panel-content--description"><?= $t('logs.lead') ?></p>

<?php if ($log['events'] === null): ?>
    <p class="logs-error">
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
            $createdAt = $event->getCreatedAt();
            ?>
            <li class="logs-event is-<?= htmlspecialchars($action, ENT_QUOTES, 'UTF-8') ?>">
                <span class="logs-event--icon" aria-hidden="true">
                    <i class="fa-solid <?= $actionIcons[$action] ?? 'fa-circle' ?>"></i>
                </span>
                <div class="logs-event--main">
                    <p class="logs-event--title">
                        <span class="logs-event--action"><?= $t('logs.action.' . $action) ?></span>
                        <span class="logs-event--reason"><?= $t('category.' . $event->getCategory()) ?></span>
                        <span class="logs-event--level"><?= $t('level.' . $event->getLevel()) ?></span>
                    </p>
                    <p class="logs-event--meta">
                        <span title="<?= htmlspecialchars($event->getAuthorPlatformId(), ENT_QUOTES, 'UTF-8') ?>">
                            <i class="fa-solid fa-user" aria-hidden="true"></i>
                            <?= htmlspecialchars($event->getAuthorName(), ENT_QUOTES, 'UTF-8') ?>
                        </span>
                        <span>
                            <i class="fa-solid fa-hashtag" aria-hidden="true"></i>
                            <?= htmlspecialchars($event->getChannelName(), ENT_QUOTES, 'UTF-8') ?>
                        </span>
                        <?php if ($event->getScore() !== null): ?>
                            <span>
                                <i class="fa-solid fa-gauge-high" aria-hidden="true"></i>
                                <?= sprintf($t('logs.score'), (int) round($event->getScore() * 100)) ?>
                            </span>
                        <?php endif; ?>
                    </p>
                    <?php if ($event->getContent() === null): ?>
                        <p class="logs-event--gone"><?= $t('logs.no_content') ?></p>
                    <?php else: ?>
                        <?php // hidden at first: it is often the very insult the bot removed ?>
                        <details class="logs-event--content">
                            <summary><?= $t('logs.show_content') ?></summary>
                            <p><?= htmlspecialchars($event->getContent(), ENT_QUOTES, 'UTF-8') ?></p>
                        </details>
                    <?php endif; ?>
                </div>
                <?php if ($createdAt !== null): ?>
                    <?php // in UTC here; local-time.js shows it in the viewer's own time zone ?>
                    <time class="logs-event--time" datetime="<?= $createdAt->format(DATE_ATOM) ?>" data-local>
                        <?= $createdAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i') ?> UTC
                    </time>
                <?php endif; ?>
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
