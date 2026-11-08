<?php $stylesheets[] = '/css/channels.css'; ?>

<p class="panel-content--description"><?= $t('channels.lead') ?></p>
<?php include __DIR__ . '/../components/panel-notice.html.php'; ?>

<?php if ($channelSlots === null): ?>
    <p class="channels-error">
        <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
        <?= $t('channels.load_error') ?>
    </p>
<?php else: ?>
    <ul class="channels">
        <?php foreach ($channelSlots as $slot => $item): ?>
            <?php if ($item === null): ?>
                <li class="channels-card is-free">
                    <div class="channels-card--head">
                        <span class="channels-card--icon" aria-hidden="true"><i class="fa-solid fa-plus"></i></span>
                        <div class="channels-card--identity">
                            <h2 class="channels-card--name"><?= $t('channels.free') ?></h2>
                            <p class="channels-card--meta">
                                <span><?= sprintf($t('channels.slot'), $slot) ?></span>
                                <span><?= $t('channels.free_meta') ?></span>
                            </p>
                        </div>
                    </div>
                    <?php if ($channelChoice['channels'] === null || $channelChoice['channels'] === []): ?>
                        <div class="channels-card--body">
                            <p class="channels-card--message<?= $channelChoice['channels'] === null ? ' is-error' : '' ?>">
                                <i class="fa-solid <?= $channelChoice['channels'] === null ? 'fa-circle-exclamation' : 'fa-eye-slash' ?>" aria-hidden="true"></i>
                                <span><?= $t($channelChoice['channels'] === null ? 'channels.discord_error' : 'channels.none_visible') ?></span>
                            </p>
                        </div>
                    <?php else: ?>
                        <form method="post" action="/app/channels/connect" class="channels-card--body">
                            <input type="hidden" name="slot" value="<?= (int) $slot ?>" />
                            <div class="channels-setting">
                                <label for="channels-choice-<?= (int) $slot ?>" class="channels-setting--label"><?= $t('channels.choose') ?></label>
                                <select id="channels-choice-<?= (int) $slot ?>" name="channel" class="channels-setting--select">
                                    <?php foreach ($channelChoice['channels'] as $choice): ?>
                                        <option value="<?= htmlspecialchars($choice['id'], ENT_QUOTES, 'UTF-8') ?>">#<?= htmlspecialchars($choice['name'], ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="channels-setting">
                                <label for="channels-new-language-<?= (int) $slot ?>" class="channels-setting--label"><?= $t('channels.language') ?></label>
                                <select id="channels-new-language-<?= (int) $slot ?>" name="language" class="channels-setting--select">
                                    <?php foreach ($channelChoice['languages'] as $code): ?>
                                        <option value="<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>"<?= $code === $channelChoice['language'] ? ' selected' : '' ?>><?= $t('language.' . $code) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="channels-card--actions">
                                <button type="submit" class="channels-button is-primary">
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
                $status = htmlspecialchars($channel->getStatus(), ENT_QUOTES, 'UTF-8');
                $name = htmlspecialchars($channel->getName(), ENT_QUOTES, 'UTF-8');
                ?>
                <li class="channels-card<?= $isChanging ? ' is-changing' : '' ?>">
                    <div class="channels-card--head">
                        <span class="channels-card--icon" aria-hidden="true"><i class="fa-solid fa-hashtag"></i></span>
                        <div class="channels-card--identity">
                            <h2 class="channels-card--name" title="<?= $name ?>"><?= $name ?></h2>
                            <p class="channels-card--meta">
                                <span><?= sprintf($t('channels.slot'), $slot) ?></span>
                                <span class="channels-card--status is-<?= $status ?>"><?= $t('channels.status.' . $channel->getStatus()) ?></span>
                            </p>
                        </div>
                        <?php if (!$isChanging): ?>
                            <a href="/app/channels?change=<?= (int) $slot ?>" class="channels-button is-quiet">
                                <i class="fa-solid fa-arrow-right-arrow-left" aria-hidden="true"></i>
                                <span><?= $t('channels.change') ?></span>
                            </a>
                        <?php endif; ?>
                    </div>
                    <?php if ($isChanging): ?>
                        <?php // while changing, the card only asks for the new channel; the language stays as it is ?>
                        <?php if ($channelChoice['channels'] === null || $channelChoice['channels'] === []): ?>
                            <div class="channels-card--body">
                                <p class="channels-card--message<?= $channelChoice['channels'] === null ? ' is-error' : '' ?>">
                                <i class="fa-solid <?= $channelChoice['channels'] === null ? 'fa-circle-exclamation' : 'fa-eye-slash' ?>" aria-hidden="true"></i>
                                <span><?= $t($channelChoice['channels'] === null ? 'channels.discord_error' : 'channels.none_visible') ?></span>
                            </p>
                                <div class="channels-card--actions">
                                    <a href="/app/channels" class="channels-button is-quiet"><?= $t('channels.cancel') ?></a>
                                </div>
                            </div>
                        <?php else: ?>
                            <form method="post" action="/app/channels/change" class="channels-card--body">
                                <input type="hidden" name="slot" value="<?= (int) $slot ?>" />
                                <div class="channels-setting">
                                    <div>
                                        <label for="channels-change-<?= (int) $slot ?>" class="channels-setting--label"><?= $t('channels.change_choose') ?></label>
                                        <p class="channels-setting--hint"><?= $t('channels.change_hint') ?></p>
                                    </div>
                                    <select id="channels-change-<?= (int) $slot ?>" name="channel" class="channels-setting--select">
                                        <?php foreach ($channelChoice['channels'] as $choice): ?>
                                            <option value="<?= htmlspecialchars($choice['id'], ENT_QUOTES, 'UTF-8') ?>">#<?= htmlspecialchars($choice['name'], ENT_QUOTES, 'UTF-8') ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="channels-card--actions">
                                    <a href="/app/channels" class="channels-button is-quiet"><?= $t('channels.cancel') ?></a>
                                    <button type="submit" class="channels-button is-primary"><?= $t('channels.change_submit') ?></button>
                                </div>
                            </form>
                        <?php endif; ?>
                    <?php elseif ($item['languages'] === []): ?>
                        <?php // a model without settings has nothing to choose from, so only the saved language is shown ?>
                        <div class="channels-card--body">
                            <div class="channels-setting">
                                <span class="channels-setting--label"><?= $t('channels.language') ?></span>
                                <span class="channels-setting--value"><?= $t('language.' . $channel->getLanguage()) ?></span>
                            </div>
                        </div>
                    <?php else: ?>
                        <form method="post" action="/app/channels/language" class="channels-card--body">
                            <input type="hidden" name="channel" value="<?= (int) $channel->getId() ?>" />
                            <div class="channels-setting">
                                <label for="channels-language-<?= (int) $slot ?>" class="channels-setting--label"><?= $t('channels.language') ?></label>
                                <div class="channels-setting--control">
                                    <select id="channels-language-<?= (int) $slot ?>" name="language" class="channels-setting--select">
                                        <?php foreach ($item['languages'] as $code): ?>
                                            <option value="<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>"<?= $code === $channel->getLanguage() ? ' selected' : '' ?>><?= $t('language.' . $code) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php $isSaved = $channel->getId() === $savedChannelId; ?>
                                    <?php // just saved: the text moves aside for a tick for a moment, the button keeps its width ?>
                                    <button type="submit" class="channels-button is-tonal<?= $isSaved ? ' is-saved' : '' ?>">
                                        <span class="channels-button--text"><?= $t('channels.save') ?></span>
                                        <i class="fa-solid fa-check channels-button--check" aria-hidden="true"></i>
                                    </button>
                                </div>
                                <?php if ($isSaved): ?>
                                    <span class="channels-saved" role="status"><?= $t('channels.saved') ?></span>
                                <?php endif; ?>
                            </div>
                        </form>
                    <?php endif; ?>
                </li>
            <?php endif; ?>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
