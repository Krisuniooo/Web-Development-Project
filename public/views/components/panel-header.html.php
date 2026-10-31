<?php $stylesheets[] = '/css/panel-header.css'; ?>

<header class="panel-header">
    <a href="/" class="panel-header--back">
        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
        Back to home
    </a>
    <div class="panel-header--brand">
        <img src="/assets/icons/logo.svg" alt="" class="panel-header--logo" width="56" height="28" />
        <span class="panel-header--name">APPNAME</span>
    </div>
    <?php include __DIR__ . '/nav-settings.html.php'; ?>
</header>
