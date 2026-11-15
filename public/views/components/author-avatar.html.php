<?php // the picture of $author from Discord, or without one their initials in a colour of their own ?>
<?php if ($author['avatarUrl'] !== null): ?>
    <img src="<?= htmlspecialchars($author['avatarUrl'], ENT_QUOTES, 'UTF-8') ?>" alt="" class="panel-avatar is-photo" width="36" height="36" loading="lazy" />
<?php else: ?>
    <span class="panel-avatar is-tone-<?= (int) $author['tone'] ?>" aria-hidden="true"><?= htmlspecialchars($author['initials'], ENT_QUOTES, 'UTF-8') ?></span>
<?php endif; ?>
