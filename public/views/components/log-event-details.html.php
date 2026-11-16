<?php
// the details of one event, in the list and on its own page; uses $event, $actionLabel, $failure and $createdAt,
// and $author, the escaped name, unless $hideAuthor is set
?>
<dl class="logs-event--details">
    <div>
        <dt><?= $t('logs.detail.action') ?></dt>
        <dd><?= $actionLabel ?></dd>
    </div>
    <?php if ($failure !== null): ?>
        <div class="logs-event--failure">
            <dt><?= $t('logs.detail.failure') ?></dt>
            <dd><?= $e($failure) ?></dd>
        </div>
    <?php endif; ?>
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
        <dd>#<?= $e($event->getChannelName()) ?></dd>
    </div>
    <?php if (empty($hideAuthor)): ?>
        <div>
            <dt><?= $t('logs.detail.author') ?></dt>
            <?php if ($author === null): ?>
                <dd><?= $t('logs.no_author') ?></dd>
            <?php else: ?>
                <dd><?= $author ?> <span class="logs-event--id">ID <?= $e($event->getAuthorPlatformId()) ?></span></dd>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <?php if ($createdAt !== null): ?>
        <div>
            <dt><?= $t('logs.detail.time') ?></dt>
            <dd><time datetime="<?= $createdAt->format(DATE_ATOM) ?>" data-local="full"><?= $createdAt->format('Y-m-d H:i:s') ?> UTC</time></dd>
        </div>
    <?php endif; ?>
    <div>
        <dt><?= $t('logs.detail.model') ?></dt>
        <dd><?= $e($event->getModel()) ?></dd>
    </div>
</dl>
