<?php
$stylesheets[] = '/css/panel/exemptions.css';
$scripts[] = '/js/panel/exemptions.js';
?>

<?php // whom the bot leaves alone: for the whole server, so one card under those of the channels ?>
<section class="panel-card exemptions" id="exemptions" aria-labelledby="exemptions-title">
    <div class="panel-card--head">
        <span class="panel-card--icon" aria-hidden="true"><i class="fa-solid fa-user-shield"></i></span>
        <div class="panel-card--identity">
            <h2 class="panel-card--name" id="exemptions-title"><?= $t('exemptions.title') ?></h2>
            <p class="panel-card--meta"><?= $t('exemptions.lead') ?></p>
        </div>
    </div>
    <div class="panel-card--body">
        <?php if ($exemptions === null): ?>
            <p class="panel-card--message is-error">
                <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                <?= $t('exemptions.load_error') ?>
            </p>
        <?php else: ?>
            <?php // the script sends the form as soon as the box changes; without it there is the button ?>
            <form method="post" action="/app/limits/exempt-admins" class="exemptions-admins">
                <label class="exemptions-checkbox">
                    <input type="checkbox" name="exempt" value="1"<?= $exemptions['admins'] ? ' checked' : '' ?> />
                    <span><?= $t('exemptions.admins') ?></span>
                </label>
                <noscript><button type="submit" class="panel-button is-quiet"><?= $t('limits.save') ?></button></noscript>
            </form>

            <div class="exemptions-roles">
                <h3 class="exemptions-roles--title"><?= $t('exemptions.roles') ?></h3>
                <?php if ($exemptions['roles'] === null): ?>
                    <p class="panel-card--message is-error">
                        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                        <?= $t('exemptions.roles_error') ?>
                    </p>
                <?php else: ?>
                    <?php // as Discord shows the roles of a member: each one with its colour, and a plus that lists the rest ?>
                    <div class="exemptions-roles--list">
                        <?php if ($exemptions['roles']['exempt'] !== []): ?>
                            <form method="post" action="/app/limits/exempt-role" class="exemptions-roles--exempt">
                                <input type="hidden" name="exempt" value="0" />
                                <?php foreach ($exemptions['roles']['exempt'] as $role): ?>
                                    <?php
                                    $name = $role['name'] === null ? $t('exemptions.deleted_role') : $e($role['name']);
                                    $color = $role['color'] === null ? '' : ' style="--role-color: ' . $e($role['color']) . '"';
                                    ?>
                                    <span class="exemptions-role<?= $role['name'] === null ? ' is-deleted' : '' ?>">
                                        <?php // the dot of the role's colour is the button that takes the role off ?>
                                        <button
                                            type="submit"
                                            name="role"
                                            value="<?= $e($role['id']) ?>"
                                            class="exemptions-role--remove"
                                            title="<?= sprintf($t('exemptions.remove'), $name) ?>"
                                            aria-label="<?= sprintf($t('exemptions.remove'), $name) ?>"
                                            <?= $color ?>
                                        ><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                                        <span class="exemptions-role--name"><?= $name ?></span>
                                    </span>
                                <?php endforeach; ?>
                            </form>
                        <?php endif; ?>

                        <details class="exemptions-picker">
                            <summary class="exemptions-role exemptions-picker--open" title="<?= $t('exemptions.add') ?>">
                                <i class="fa-solid fa-plus" aria-hidden="true"></i>
                                <?php // alone on the row it says what it is for; after roles the plus is enough, as on Discord ?>
                                <span<?= $exemptions['roles']['exempt'] === [] ? '' : ' class="panel-hidden"' ?>><?= $t('exemptions.add') ?></span>
                            </summary>
                            <?php if ($exemptions['roles']['choices'] === []): ?>
                                <div class="exemptions-picker--panel">
                                    <p class="exemptions-picker--empty"><?= $t('exemptions.no_choices') ?></p>
                                </div>
                            <?php else: ?>
                                <form method="post" action="/app/limits/exempt-role" class="exemptions-picker--panel">
                                    <input type="hidden" name="exempt" value="1" />
                                    <?php // shown by the script, which narrows the list as someone types ?>
                                    <input
                                        type="search"
                                        class="exemptions-picker--search"
                                        placeholder="<?= $t('exemptions.search') ?>"
                                        aria-label="<?= $t('exemptions.search') ?>"
                                        autocomplete="off"
                                        hidden
                                    />
                                    <ul class="exemptions-picker--choices">
                                        <?php foreach ($exemptions['roles']['choices'] as $role): ?>
                                            <li>
                                                <button type="submit" name="role" value="<?= $e($role['id']) ?>" class="exemptions-picker--choice">
                                                    <span class="exemptions-dot"<?= $role['color'] === null ? '' : ' style="--role-color: ' . $e($role['color']) . '"' ?> aria-hidden="true"></span>
                                                    <span><?= $e($role['name']) ?></span>
                                                </button>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <p class="exemptions-picker--empty" data-no-match hidden><?= $t('exemptions.no_match') ?></p>
                                </form>
                            <?php endif; ?>
                        </details>
                    </div>
                <?php endif; ?>
            </div>

            <p class="exemptions-hint"><?= $t('exemptions.hint') ?></p>
        <?php endif; ?>
    </div>
</section>
