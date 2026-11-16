<?php
// the choice and its button look like the language choice on the channels, so they share those styles
$stylesheets[] = '/css/panel/channels.css';
$stylesheets[] = '/css/panel/models.css';
?>

<p class="panel-content--description"><?= $t('models.lead') ?></p>
<?php include __DIR__ . '/../components/panel-notice.html.php'; ?>

<?php if ($modelChannels === null): ?>
    <?php $errorText = 'channels.load_error'; ?>
    <?php include __DIR__ . '/../components/panel-error.html.php'; ?>
<?php elseif ($modelChannels === []): ?>
    <?php include __DIR__ . '/../components/no-channels.html.php'; ?>
<?php else: ?>
    <ul class="panel-cards">
        <?php foreach ($modelChannels as $item): ?>
            <?php
            $channel = $item['channel'];
            $channelId = $channel->getId();
            $name = $e($channel->getName());
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
                                        <option value="<?= $e($choice['slug']) ?>"<?= $choice['slug'] === $channel->getModel() ? ' selected' : '' ?><?= $choice['isForLanguage'] ? '' : ' disabled' ?>>
                                            <?= $e($choice['label']) ?><?= $choice['isForLanguage'] ? '' : ' (' . $t('models.other_language') . ')' ?>
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
                                <?= $e($item['label']) ?>
                                <?php if ($item['size'] !== null): ?>
                                    <span class="models-detail--size"><?= $e($item['size']) ?></span>
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
