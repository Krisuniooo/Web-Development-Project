<?php $stylesheets[] = '/css/nav-settings.css'; ?>

<div class="nav-settings">
    <form method="post" action="/language" class="available-languages" role="group" aria-label="<?= $t('language.label') ?>">
        <input type="hidden" name="back" value="<?= htmlspecialchars($currentPath, ENT_QUOTES, 'UTF-8') ?>" />
        <?php foreach (\App\Lang::SUPPORTED as $code): ?>
            <button type="submit" name="lang" value="<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>" class="available-language" aria-pressed="<?= $code === $lang->code() ? 'true' : 'false' ?>"><?= htmlspecialchars(strtoupper($code), ENT_QUOTES, 'UTF-8') ?></button>
        <?php endforeach; ?>
    </form>
    <div class="toggle-light-mode">
        <button type="button" class="switch-mode" aria-label="Switch to light mode">
            <i class="fa-regular fa-sun switch-mode-icon switch-mode-icon--sun" aria-hidden="true"></i>
            <i class="fa-regular fa-moon switch-mode-icon switch-mode-icon--moon" aria-hidden="true"></i>
        </button>
    </div>
</div>
