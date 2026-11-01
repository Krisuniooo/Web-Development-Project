<?php $stylesheets[] = '/css/footer.css'; ?>

<footer class="footer">
    <span>&copy; <?= date('Y') ?> <?= $t('app.name') ?></span>
    <div class="footer-info">
        <span><?= $t('footer.note') ?></span>
        <a href="https://github.com/Krisuniooo/Web-Development-Project" class="footer-link" target="_blank" rel="noopener noreferrer">GitHub</a>
        <a href="/status" class="footer-status"><?= $t('status.title') ?></a>
    </div>
</footer>
