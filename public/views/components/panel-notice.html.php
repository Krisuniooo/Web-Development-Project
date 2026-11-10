<?php // what the last form did, as a popup in the corner of the screen ?>
<?php if ($notice !== null): ?>
    <p class="panel-notice<?= $notice['isError'] ? ' is-error' : '' ?>" role="status">
        <i class="fa-solid <?= $notice['isError'] ? 'fa-circle-exclamation' : 'fa-circle-check' ?>" aria-hidden="true"></i>
        <?= $t($notice['text']) ?>
    </p>
<?php endif; ?>
