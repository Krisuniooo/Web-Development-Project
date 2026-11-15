<?php $stylesheets[] = '/css/components/footer.css'; ?>

<footer class="footer">
    <span>&copy; <?= date('Y') ?> <?= $t('app.name') ?></span>
    <div class="footer-info">
        <span><?= $t('footer.note') ?></span>
        <a href="/privacy" class="footer-link"><?= $t('footer.privacy') ?></a>
        <a href="/terms" class="footer-link"><?= $t('footer.terms') ?></a>
        <a href="https://github.com/Krisuniooo/Web-Development-Project" class="footer-link" target="_blank" rel="noopener noreferrer">GitHub</a>
        <a href="/status" class="footer-status"><?= $t('status.title') ?></a>
    </div>
</footer>
