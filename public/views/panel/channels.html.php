<?php $stylesheets[] = '/css/channels.css'; ?>

<p class="panel-content--description"><?= $t('channels.lead') ?></p>

<?php if ($channelSlots === null): ?>
    <p class="channels-error"><?= $t('channels.load_error') ?></p>
<?php else: ?>
    <ul class="channels">
        <?php foreach ($channelSlots as $slot => $item): ?>
            <?php if ($item === null): ?>
                <li class="channels-card is-free">
                    <div class="channels-card--header">
                        <span class="channels-card--slot"><?= sprintf($t('channels.slot'), $slot) ?></span>
                    </div>
                    <h2 class="channels-card--name"><?= $t('channels.free') ?></h2>
                    <?php if ($channelChoice['channels'] === null): ?>
                        <p class="channels-card--hint"><?= $t('channels.discord_error') ?></p>
                    <?php elseif ($channelChoice['channels'] === []): ?>
                        <p class="channels-card--hint"><?= $t('channels.none_visible') ?></p>
                    <?php else: ?>
                        <form method="post" action="/app/channels/connect" class="channels-card--form">
                            <input type="hidden" name="slot" value="<?= (int) $slot ?>" />
                            <label class="channels-card--field is-wide">
                                <span class="channels-card--label"><?= $t('channels.choose') ?></span>
                                <select name="channel" class="channels-card--select">
                                    <?php foreach ($channelChoice['channels'] as $choice): ?>
                                        <option value="<?= htmlspecialchars($choice['id'], ENT_QUOTES, 'UTF-8') ?>">#<?= htmlspecialchars($choice['name'], ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label class="channels-card--field">
                                <span class="channels-card--label"><?= $t('channels.language') ?></span>
                                <select name="language" class="channels-card--select">
                                    <?php foreach ($channelChoice['languages'] as $code): ?>
                                        <option value="<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>"<?= $code === $channelChoice['language'] ? ' selected' : '' ?>><?= $t('language.' . $code) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <button type="submit" class="channels-card--save"><?= $t('channels.connect') ?></button>
                        </form>
                    <?php endif; ?>
                </li>
            <?php else: ?>
                <?php $channel = $item['channel']; ?>
                <?php $isChanging = $slot === $changingSlot; ?>
                <li class="channels-card<?= $isChanging ? ' is-changing' : '' ?>">
                    <div class="channels-card--header">
                        <span class="channels-card--slot"><?= sprintf($t('channels.slot'), $slot) ?></span>
                        <span class="channels-card--status is-<?= htmlspecialchars($channel->getStatus(), ENT_QUOTES, 'UTF-8') ?>"><?= $t('channels.status.' . $channel->getStatus()) ?></span>
                    </div>
                    <div class="channels-card--title">
                        <h2 class="channels-card--name">
                            <i class="fa-solid fa-hashtag" aria-hidden="true"></i>
                            <?= htmlspecialchars($channel->getName(), ENT_QUOTES, 'UTF-8') ?>
                        </h2>
                        <?php if (!$isChanging): ?>
                            <a href="/app/channels?change=<?= (int) $slot ?>" class="channels-card--change">
                                <i class="fa-solid fa-arrow-right-arrow-left" aria-hidden="true"></i>
                                <?= $t('channels.change') ?>
                            </a>
                        <?php endif; ?>
                    </div>
                    <?php if ($isChanging): ?>
                        <?php // while changing, the card only asks for the new channel; the language stays as it is ?>
                        <?php if ($channelChoice['channels'] === null || $channelChoice['channels'] === []): ?>
                            <p class="channels-card--hint"><?= $t($channelChoice['channels'] === null ? 'channels.discord_error' : 'channels.none_visible') ?></p>
                            <div class="channels-card--actions">
                                <a href="/app/channels" class="channels-card--cancel"><?= $t('channels.cancel') ?></a>
                            </div>
                        <?php else: ?>
                            <form method="post" action="/app/channels/change" class="channels-card--form">
                                <input type="hidden" name="slot" value="<?= (int) $slot ?>" />
                                <label class="channels-card--field is-wide">
                                    <span class="channels-card--label"><?= $t('channels.change_choose') ?></span>
                                    <select name="channel" class="channels-card--select">
                                        <?php foreach ($channelChoice['channels'] as $choice): ?>
                                            <option value="<?= htmlspecialchars($choice['id'], ENT_QUOTES, 'UTF-8') ?>">#<?= htmlspecialchars($choice['name'], ENT_QUOTES, 'UTF-8') ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <div class="channels-card--actions">
                                    <a href="/app/channels" class="channels-card--cancel"><?= $t('channels.cancel') ?></a>
                                    <button type="submit" class="channels-card--save"><?= $t('channels.change_submit') ?></button>
                                </div>
                            </form>
                        <?php endif; ?>
                    <?php elseif ($item['languages'] === []): ?>
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
