<?php $stylesheets[] = '/css/login.css'; ?>

<main class="login">
    <div class="login-box">
        <a href="/" class="login-brand">
            <img src="/assets/icons/logo.svg" alt="" class="login-brand--logo" width="56" height="28" />
            <span class="login-brand--name"><?= $t('app.name') ?></span>
        </a>
        <h1 class="login-title"><?= $t('login.title') ?></h1>
        <p class="login-description"><?= $t('login.description') ?></p>
        <?php if ($loginError !== null): ?>
            <p class="login-error" role="alert"><?= $t($loginError) ?></p>
        <?php endif; ?>
        <a href="/auth/discord" class="login-platform">
            <img src="/assets/icons/discord.svg" alt="" class="login-platform--icon" width="20" height="20" />
            <?= $t('login.discord') ?>
        </a>
        <?php include __DIR__ . '/components/nav-settings.html.php'; ?>
    </div>
</main>
