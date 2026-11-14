<?php
// the choice and its button look like the language choice on the channels, so they share those styles
$stylesheets[] = '/css/panel/channels.css';
$stylesheets[] = '/css/panel/models.css';
?>

<p class="panel-content--description"><?= $t('models.lead') ?></p>
<?php include __DIR__ . '/../components/panel-notice.html.php'; ?>

<?php if ($modelChannels === null): ?>
    <p class="panel-error">
        <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
        <?= $t('channels.load_error') ?>
    </p>
<?php elseif ($modelChannels === []): ?>
    <p class="models-empty">
        <?= $t('limits.empty') ?>
        <a href="/app/channels"><?= $t('limits.empty_link') ?></a>
    </p>
<?php else: ?>
    <ul class="panel-cards">
        <?php foreach ($modelChannels as $item): ?>
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
                        </p>
                    </div>
                </div>
                <div class="panel-card--body">
                    <?php // with one model there is nothing to choose, so its name alone shows in the list below ?>
                    <?php if (count($item['choices']) > 1): ?>
                        <form method="post" action="/app/models/model" class="channels-setting">
                            <input type="hidden" name="channel" value="<?= $channelId ?>" />
                            <label for="models-model-<?= $channelId ?>" class="channels-setting--label"><?= $t('models.choose') ?></label>
                            <div class="channels-setting--control">
                                <select id="models-model-<?= $channelId ?>" name="model" class="channels-setting--select">
                                    <?php foreach ($item['choices'] as $choice): ?>
                                        <?php // a model without questions in the channel's language cannot be chosen, and says why ?>
                                        <option value="<?= htmlspecialchars($choice['slug'], ENT_QUOTES, 'UTF-8') ?>"<?= $choice['slug'] === $channel->getModel() ? ' selected' : '' ?><?= $choice['isForLanguage'] ? '' : ' disabled' ?>>
                                            <?= htmlspecialchars($choice['label'], ENT_QUOTES, 'UTF-8') ?><?= $choice['isForLanguage'] ? '' : ' (' . $t('models.other_language') . ')' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php // just saved: the text moves aside for a tick for a moment, the button keeps its width ?>
                                <button type="submit" class="panel-button is-tonal<?= $isSaved ? ' is-saved' : '' ?>">
                                    <span class="panel-button--text"><?= $t('channels.save') ?></span>
                                    <i class="fa-solid fa-check panel-button--check" aria-hidden="true"></i>
                                </button>
                            </div>
                            <?php if ($isSaved): ?>
                                <span class="panel-hidden" role="status"><?= $t('models.saved') ?></span>
                            <?php endif; ?>
                        </form>
                    <?php endif; ?>
                    <dl class="models-details">
                        <div class="models-detail">
                            <dt><?= $t('models.model') ?></dt>
                            <dd>
                                <?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?>
                                <?php if ($item['size'] !== null): ?>
                                    <span class="models-detail--size"><?= htmlspecialchars($item['size'], ENT_QUOTES, 'UTF-8') ?></span>
                                <?php endif; ?>
                            </dd>
                        </div>
                        <div class="models-detail">
                            <dt><?= $t('models.languages') ?></dt>
                            <dd><?= implode(', ', array_map(fn(string $code): string => $t('language.' . $code), $item['languages'])) ?></dd>
                        </div>
                        <div class="models-detail">
                            <dt><?= $t('models.channel_language') ?></dt>
                            <dd><?= $t('language.' . $channel->getLanguage()) ?></dd>
                        </div>
                    </dl>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
