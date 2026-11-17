<?php $stylesheets[] = '/css/panel/channels.css'; ?>

<p class="panel-content--description"><?= $t('channels.lead') ?></p>
<?php if ($botName !== null && $botIsModerator === false): ?>
    <?php // someone took the moderator away from the bot, or never gave it: the bot does not listen to the chat until then ?>
    <p class="panel-error" role="alert">
        <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
        <?= sprintf($t('channels.bot_not_moderator'), $e($botName)) ?>
    </p>
<?php elseif ($botName !== null): ?>
    <?php // the bot is an account: it deletes only where a moderator of the channel made it one ?>
    <p class="panel-content--description"><?= sprintf($t('channels.bot_hint'), $e($botName)) ?></p>
<?php endif; ?>
<?php include __DIR__ . '/../components/panel-notice.html.php'; ?>

<?php if ($channelSlots === null): ?>
    <?php $errorText = 'channels.load_error'; ?>
    <?php include __DIR__ . '/../components/panel-error.html.php'; ?>
<?php else: ?>
    <ul class="panel-cards">
        <?php foreach ($channelSlots as $slot => $item): ?>
            <?php if ($item === null): ?>
                <li class="panel-card is-free">
                    <div class="panel-card--head">
                        <span class="panel-card--icon" aria-hidden="true"><i class="fa-solid fa-plus"></i></span>
                        <div class="panel-card--identity">
                            <h2 class="panel-card--name"><?= $t('channels.free') ?></h2>
                            <p class="panel-card--meta">
                                <span><?= sprintf($t('channels.slot'), $slot) ?></span>
                                <span><?= $t('channels.free_meta') ?></span>
                            </p>
                        </div>
                    </div>
                    <?php if ($channelChoice['channels'] === null || $channelChoice['channels'] === []): ?>
                        <div class="panel-card--body">
                            <?php include __DIR__ . '/../components/no-channel-choice.html.php'; ?>
                        </div>
                    <?php else: ?>
                        <form method="post" action="/app/channels/connect" class="panel-card--body">
                            <input type="hidden" name="slot" value="<?= (int) $slot ?>" />
                            <div class="channels-setting">
                                <label for="channels-choice-<?= (int) $slot ?>" class="channels-setting--label"><?= $t('channels.choose') ?></label>
                                <select id="channels-choice-<?= (int) $slot ?>" name="channel" class="channels-setting--select">
                                    <?php foreach ($channelChoice['channels'] as $choice): ?>
                                        <option value="<?= $e($choice['id']) ?>">#<?= $e($choice['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="channels-setting">
                                <label for="channels-new-language-<?= (int) $slot ?>" class="channels-setting--label"><?= $t('channels.language') ?></label>
                                <select id="channels-new-language-<?= (int) $slot ?>" name="language" class="channels-setting--select">
                                    <?php foreach ($channelChoice['languages'] as $code): ?>
                                        <option value="<?= $e($code) ?>"<?= $code === $channelChoice['language'] ? ' selected' : '' ?>><?= $t('language.' . $code) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="panel-card--actions">
                                <button type="submit" class="panel-button is-primary">
                                    <i class="fa-solid fa-plus" aria-hidden="true"></i>
                                    <?= $t('channels.connect') ?>
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </li>
            <?php else: ?>
                <?php
                $channel = $item['channel'];
                $isChanging = $slot === $changingSlot;
                $status = $e($channel->getStatus());
                $name = $e($channel->getName());
                ?>
                <li class="panel-card<?= $isChanging ? ' is-changing' : '' ?>">
                    <div class="panel-card--head">
                        <span class="panel-card--icon" aria-hidden="true"><i class="fa-solid fa-hashtag"></i></span>
                        <div class="panel-card--identity">
                            <h2 class="panel-card--name" title="<?= $name ?>"><?= $name ?></h2>
                            <p class="panel-card--meta">
                                <span><?= sprintf($t('channels.slot'), $slot) ?></span>
                                <span class="panel-card--status is-<?= $status ?>"><?= $t('channels.status.' . $channel->getStatus()) ?></span>
                            </p>
                        </div>
                        <?php if (!$isChanging): ?>
                            <?php $isPaused = $channel->isPaused(); ?>
                            <form method="post" action="/app/channels/status">
                                <input type="hidden" name="channel" value="<?= $channel->getId() ?>" />
                                <input type="hidden" name="status" value="<?= $isPaused ? \App\Model\Channel::STATUS_ACTIVE : \App\Model\Channel::STATUS_PAUSED ?>" />
                                <button type="submit" class="panel-button is-quiet">
                                    <i class="fa-solid <?= $isPaused ? 'fa-play' : 'fa-pause' ?>" aria-hidden="true"></i>
                                    <span><?= $t($isPaused ? 'channels.resume' : 'channels.pause') ?></span>
                                </button>
                            </form>
                            <?php if ($canChange): ?>
                                <a href="/app/channels?change=<?= (int) $slot ?>" class="panel-button is-quiet">
                                    <i class="fa-solid fa-arrow-right-arrow-left" aria-hidden="true"></i>
                                    <span><?= $t('channels.change') ?></span>
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <?php if ($isChanging): ?>
                        <?php // while changing, the card only asks for the new channel; the language stays as it is ?>
                        <?php if ($channelChoice['channels'] === null || $channelChoice['channels'] === []): ?>
                            <div class="panel-card--body">
                                <?php include __DIR__ . '/../components/no-channel-choice.html.php'; ?>
                                <div class="panel-card--actions">
                                    <a href="/app/channels" class="panel-button is-quiet"><?= $t('channels.cancel') ?></a>
                                </div>
                            </div>
                        <?php else: ?>
                            <form method="post" action="/app/channels/change" class="panel-card--body">
                                <input type="hidden" name="slot" value="<?= (int) $slot ?>" />
                                <div class="channels-setting">
                                    <div>
                                        <label for="channels-change-<?= (int) $slot ?>" class="channels-setting--label"><?= $t('channels.change_choose') ?></label>
                                        <p class="channels-setting--hint"><?= $t('channels.change_hint') ?></p>
                                    </div>
                                    <select id="channels-change-<?= (int) $slot ?>" name="channel" class="channels-setting--select">
                                        <?php foreach ($channelChoice['channels'] as $choice): ?>
                                            <option value="<?= $e($choice['id']) ?>">#<?= $e($choice['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="panel-card--actions">
                                    <a href="/app/channels" class="panel-button is-quiet"><?= $t('channels.cancel') ?></a>
                                    <button type="submit" class="panel-button is-primary"><?= $t('channels.change_submit') ?></button>
                                </div>
                            </form>
                        <?php endif; ?>
                    <?php elseif ($item['languages'] === []): ?>
                        <?php // a model without settings has nothing to choose from, so only the saved language is shown ?>
                        <div class="panel-card--body">
                            <div class="channels-setting">
                                <span class="channels-setting--label"><?= $t('channels.language') ?></span>
                                <span class="channels-setting--value"><?= $t('language.' . $channel->getLanguage()) ?></span>
                            </div>
                        </div>
                    <?php else: ?>
                        <form method="post" action="/app/channels/language" class="panel-card--body">
                            <input type="hidden" name="channel" value="<?= $channel->getId() ?>" />
                            <div class="channels-setting">
                                <label for="channels-language-<?= (int) $slot ?>" class="channels-setting--label"><?= $t('channels.language') ?></label>
                                <div class="channels-setting--control">
                                    <select id="channels-language-<?= (int) $slot ?>" name="language" class="channels-setting--select">
                                        <?php foreach ($item['languages'] as $code): ?>
                                            <option value="<?= $e($code) ?>"<?= $code === $channel->getLanguage() ? ' selected' : '' ?>><?= $t('language.' . $code) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php $isSaved = $channel->getId() === $savedChannelId; ?>
                                    <?php // just saved: a tick replaces the text for a moment ?>
                                    <button type="submit" class="panel-button is-tonal<?= $isSaved ? ' is-saved' : '' ?>">
                                        <span class="panel-button--text"><?= $t('channels.save') ?></span>
                                        <i class="fa-solid fa-check panel-button--check" aria-hidden="true"></i>
                                    </button>
                                </div>
                                <?php if ($isSaved): ?>
                                    <span class="panel-hidden" role="status"><?= $t('channels.saved') ?></span>
                                <?php endif; ?>
                            </div>
                        </form>
                    <?php endif; ?>
                </li>
            <?php endif; ?>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
