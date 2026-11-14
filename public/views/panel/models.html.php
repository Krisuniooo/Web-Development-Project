<?php $stylesheets[] = '/css/panel/models.css'; ?>

<p class="panel-content--description"><?= $t('models.lead') ?></p>

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
            $name = htmlspecialchars($channel->getName(), ENT_QUOTES, 'UTF-8');
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
                <dl class="panel-card--body models-details">
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
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
