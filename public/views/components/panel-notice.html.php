<?php // what the last form did; a section shows it under its lead, close to what it is about ?>
<?php if ($notice !== null): ?>
    <p class="panel-notice<?= $notice['isError'] ? ' is-error' : '' ?>" role="status">
        <i class="fa-solid <?= $notice['isError'] ? 'fa-circle-exclamation' : 'fa-circle-check' ?>" aria-hidden="true"></i>
        <?= $t($notice['text']) ?>
    </p>
<?php endif; ?>
