<?php $stylesheets[] = '/css/channels.css'; ?>

<p class="panel-content--description"><?= $t('channels.lead') ?></p>

<?php if ($channelSlots === null): ?>
    <p class="channels-error"><?= $t('channels.load_error') ?></p>
<?php else: ?>
    <ul class="channels">
        <?php foreach ($channelSlots as $slot => $item): ?>
            <?php if ($item === null): ?>
                <?php // connecting a channel comes in a later step ?>
                <li class="channels-card is-free">
                    <span class="channels-card--slot"><?= sprintf($t('channels.slot'), $slot) ?></span>
                    <span class="channels-card--free"><?= $t('channels.free') ?></span>
                    <span class="channels-card--hint"><?= $t('channels.free_hint') ?></span>
                </li>
            <?php else: ?>
                <?php $channel = $item['channel']; ?>
                <li class="channels-card">
                    <div class="channels-card--header">
                        <span class="channels-card--slot"><?= sprintf($t('channels.slot'), $slot) ?></span>
                        <span class="channels-card--status is-<?= htmlspecialchars($channel->getStatus(), ENT_QUOTES, 'UTF-8') ?>"><?= $t('channels.status.' . $channel->getStatus()) ?></span>
                    </div>
                    <h2 class="channels-card--name">
                        <i class="fa-solid fa-hashtag" aria-hidden="true"></i>
                        <?= htmlspecialchars($channel->getName(), ENT_QUOTES, 'UTF-8') ?>
                    </h2>
                    <?php if ($item['languages'] === []): ?>
                        <?php // a model without settings has nothing to choose from, so only the saved language is shown ?>
                        <p class="channels-card--language"><?= $t('language.' . $channel->getLanguage()) ?></p>
                    <?php else: ?>
                        <form method="post" action="/app/channels/language" class="channels-card--form">
                            <input type="hidden" name="channel" value="<?= (int) $channel->getId() ?>" />
                            <label class="channels-card--field">
                                <span class="channels-card--label"><?= $t('channels.language') ?></span>
                                <select name="language" class="channels-card--select">
                                    <?php foreach ($item['languages'] as $code): ?>
                                        <option value="<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>"<?= $code === $channel->getLanguage() ? ' selected' : '' ?>><?= $t('language.' . $code) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <button type="submit" class="channels-card--save"><?= $t('channels.save') ?></button>
                        </form>
                    <?php endif; ?>
                </li>
            <?php endif; ?>
        <?php endforeach; ?>
    </ul>
    <p class="channels-hint"><?= $t('channels.hint') ?></p>
<?php endif; ?>
