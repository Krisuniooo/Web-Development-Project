<?php $stylesheets[] = '/css/panel/limits.css'; ?>

<p class="panel-content--description"><?= $t('limits.lead') ?></p>
<?php include __DIR__ . '/../components/panel-notice.html.php'; ?>

<?php if ($limits['channels'] === null): ?>
    <p class="panel-error">
        <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
        <?= $t('channels.load_error') ?>
    </p>
<?php elseif ($limits['channels'] === []): ?>
    <p class="limits-empty">
        <?= $t('limits.empty') ?>
        <a href="/app/channels"><?= $t('limits.empty_link') ?></a>
    </p>
<?php else: ?>
    <ul class="panel-cards">
        <?php foreach ($limits['channels'] as $item): ?>
            <?php
            $channel = $item['channel'];
            $channelId = (int) $channel->getId();
            $name = htmlspecialchars($channel->getName(), ENT_QUOTES, 'UTF-8');
            $isSaved = $channel->getId() === $savedChannelId;
            ?>
            <li class="panel-card">
                <div class="panel-card--head">
                    <span class="panel-card--icon" aria-hidden="true"><i class="fa-solid fa-hashtag"></i></span>
                    <div class="panel-card--identity">
                        <h2 class="panel-card--name" title="<?= $name ?>"><?= $name ?></h2>
                        <p class="panel-card--meta">
                            <span><?= sprintf($t('channels.slot'), $channel->getSlot()) ?></span>
                            <span class="limits-language">
                                <i class="fa-solid fa-language" aria-hidden="true"></i>
                                <?= $t('language.' . $channel->getLanguage()) ?>
                            </span>
                        </p>
                    </div>
                </div>
                <form method="post" action="/app/limits/rules" class="panel-card--body">
                    <input type="hidden" name="channel" value="<?= $channelId ?>" />
                    <?php // the column names, for the eye only: every group of choices has its own name for screen readers ?>
                    <div class="limits-columns" aria-hidden="true">
                        <span></span>
                        <span><?= $t('limits.level') ?></span>
                        <span><?= $t('limits.action') ?></span>
                    </div>
                    <?php foreach ($item['rules'] as $category => $rule): ?>
                        <?php $field = 'rules[' . htmlspecialchars($category, ENT_QUOTES, 'UTF-8') . ']'; ?>
                        <div class="limits-rule">
                            <span class="limits-rule--name"><?= $t('category.' . $category) ?></span>
                            <div class="limits-choices is-level" role="radiogroup" aria-label="<?= $t('category.' . $category) ?>: <?= $t('limits.level') ?>">
                                <?php foreach ($limits['levels'] as $level): ?>
                                    <label class="limits-choice">
                                        <input type="radio" name="<?= $field ?>[level]" value="<?= htmlspecialchars($level, ENT_QUOTES, 'UTF-8') ?>"<?= $rule->getLevel() === $level ? ' checked' : '' ?> required />
                                        <span><?= $t('level.' . $level) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <div class="limits-choices is-action" role="radiogroup" aria-label="<?= $t('category.' . $category) ?>: <?= $t('limits.action') ?>">
                                <?php foreach ($limits['actions'] as $action): ?>
                                    <label class="limits-choice">
                                        <input type="radio" name="<?= $field ?>[action]" value="<?= htmlspecialchars($action, ENT_QUOTES, 'UTF-8') ?>"<?= $rule->getAction() === $action ? ' checked' : '' ?> required />
                                        <span><?= $t('action.' . $action) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <div class="limits-footer">
                        <p class="limits-hint"><?= $t('check.levels_hint') ?></p>
                        <?php // just saved: the text moves aside for a tick for a moment, the button keeps its width ?>
                        <button type="submit" class="panel-button is-tonal<?= $isSaved ? ' is-saved' : '' ?>">
                            <span class="panel-button--text"><?= $t('limits.save') ?></span>
                            <i class="fa-solid fa-check panel-button--check" aria-hidden="true"></i>
                        </button>
                    </div>
                    <?php if ($isSaved): ?>
                        <span class="panel-hidden" role="status"><?= $t('limits.saved') ?></span>
                    <?php endif; ?>
                </form>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<?php include __DIR__ . '/message-check.html.php'; ?>
