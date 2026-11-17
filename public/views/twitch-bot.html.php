<?php $stylesheets[] = '/css/login.css'; ?>

<main class="login">
    <div class="login-box">
        <a href="/" class="login-brand">
            <img src="/assets/icons/logo.svg" alt="" class="login-brand--logo" width="56" height="28" />
            <span class="login-brand--name"><?= $t('app.name') ?></span>
        </a>
        <h1 class="login-title"><?= $t('twitchbot.title') ?></h1>
        <?php if ($status === 'ok'): ?>
            <p class="login-description" role="status"><?= sprintf($t('twitchbot.ok'), $e($botName), $e($botName)) ?></p>
        <?php else: ?>
            <p class="login-error" role="alert"><?= $t('twitchbot.' . $status) ?></p>
            <a href="/auth/twitch/bot" class="login-platform">
                <img src="/assets/icons/twitch.svg" alt="" class="login-platform--icon" width="20" height="20" />
                <?= $t('twitchbot.retry') ?>
            </a>
        <?php endif; ?>
        <?php include __DIR__ . '/components/nav-settings.html.php'; ?>
    </div>
</main>
