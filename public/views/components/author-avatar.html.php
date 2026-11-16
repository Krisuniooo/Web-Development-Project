<?php // the picture of $author from Discord, or without one their initials in a colour of their own ?>
<?php if ($author['avatarUrl'] !== null): ?>
    <img src="<?= $e($author['avatarUrl']) ?>" alt="" class="panel-avatar is-photo" width="36" height="36" loading="lazy" />
<?php else: ?>
    <span class="panel-avatar is-tone-<?= (int) $author['tone'] ?>" aria-hidden="true"><?= $e($author['initials']) ?></span>
<?php endif; ?>
