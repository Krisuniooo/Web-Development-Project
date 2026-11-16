<?php
// why a slot has no channel to choose: Discord did not answer ($channelChoice['channels'] is null),
// or the bot sees no channel that is not connected yet
$isDiscordError = $channelChoice['channels'] === null;
?>
<p class="panel-card--message<?= $isDiscordError ? ' is-error' : '' ?>">
    <i class="fa-solid <?= $isDiscordError ? 'fa-circle-exclamation' : 'fa-eye-slash' ?>" aria-hidden="true"></i>
    <span><?= $t($isDiscordError ? 'channels.discord_error' : 'channels.none_visible') ?></span>
</p>
