<?php $scripts[] = '/js/how-it-works.js'; ?>
<?php $modelCarousel = array_merge($models, $models); ?>

<section class="how-it-works">
    <h2>How it works</h2>
    <ol class="how-it-works-list">
        <li class="how-it-works--item">
            <div class="how-it-works--scene" aria-hidden="true">
                <div class="how-it-works-platform">
                    <div class="how-it-works-platform--endpoint">
                        <span class="how-it-works-platform--tile is-app">
                            <img src="/assets/icons/logo.svg" alt="" width="56" height="28" />
                        </span>
                        <span class="how-it-works-platform--name">My-App</span>
                    </div>
                    <svg class="how-it-works-platform--arrow" viewBox="0 0 64 24" fill="none">
                        <path pathLength="1" d="M2 16C18 7 40 5 60 11L52 5L60 11L53 18" />
                    </svg>
                    <div class="how-it-works-platform--targets">
                        <?php foreach ($howItWorks['platforms'] as $index => $platform): ?>
                            <div class="how-it-works-platform--endpoint<?= $index === 0 ? ' is-active' : '' ?>">
                                <span class="how-it-works-platform--tile is-<?= htmlspecialchars($platform['slug'], ENT_QUOTES, 'UTF-8') ?>">
                                    <img src="/assets/icons/<?= htmlspecialchars($platform['slug'], ENT_QUOTES, 'UTF-8') ?>.svg" alt="" width="24" height="24" />
                                </span>
                                <span class="how-it-works-platform--name"><?= htmlspecialchars($platform['name'], ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <h3>Choose a platform</h3>
            <span class="how-it-works--number">1</span>
        </li>
        <li class="how-it-works--item">
            <div class="how-it-works--scene" aria-hidden="true">
                <?php foreach ($modelCarousel as $index => $model): ?>
                    <div class="how-it-works-models--item<?= !empty($model['pro']) ? ' is-pro' : '' ?>" data-slot="<?= min(($index + 2) % count($modelCarousel) - 2, 3) ?>">
                        <span class="how-it-works-models--disc">
                            <img src="<?= htmlspecialchars($model['logo'], ENT_QUOTES, 'UTF-8') ?>" alt="" class="how-it-works-models--logo" width="64" height="64" />
                            <?php if (!empty($model['pro'])): ?>
                                <span class="how-it-works-models--pro">PRO</span>
                            <?php endif; ?>
                        </span>
                        <span class="how-it-works-models--label">
                            <?= htmlspecialchars($model['label'], ENT_QUOTES, 'UTF-8') ?>
                            <small><?= htmlspecialchars($model['size'], ENT_QUOTES, 'UTF-8') ?></small>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
            <h3>Pick a model</h3>
            <span class="how-it-works--number">2</span>
        </li>
        <li class="how-it-works--item">
            <div class="how-it-works--scene" aria-hidden="true">
                <div class="how-it-works-limits">
                    <div class="how-it-works-limits--bar">
                        <span class="how-it-works-limits--dot"></span>
                        <span class="how-it-works-limits--dot"></span>
                        <span class="how-it-works-limits--dot"></span>
                        <span class="how-it-works-limits--tab">
                            <img src="/assets/icons/discord.svg" alt="" width="12" height="12" />
                            #general
                        </span>
                    </div>
                    <div class="how-it-works-limits--body">
                        <div class="how-it-works-limits--sidebar">
                            <img src="/assets/icons/logo.svg" alt="" class="how-it-works-limits--logo" width="56" height="28" />
                            <ul class="how-it-works-limits--nav">
                                <li>Overview</li>
                                <li>Channels</li>
                                <li>Models</li>
                                <li class="is-active">Limits</li>
                                <li>Settings</li>
                            </ul>
                        </div>
                        <div class="how-it-works-limits--main">
                            <div class="how-it-works-limits--status">Watching #general</div>
                            <?php foreach ($howItWorks['limits'] as $limit): ?>
                                <div class="how-it-works-limits--slider<?= $limit['value'] === 0 ? ' is-off' : '' ?>" style="--value: <?= (int) $limit['value'] ?>">
                                    <span class="how-it-works-limits--name"><?= htmlspecialchars($limit['name'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="how-it-works-limits--level"><?= htmlspecialchars($limit['level'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="how-it-works-limits--track"><span class="how-it-works-limits--fill"></span></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <h3>Set your limits</h3>
            <span class="how-it-works--number">3</span>
        </li>
    </ol>
</section>
