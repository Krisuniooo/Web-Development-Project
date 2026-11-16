<!doctype html>
<html lang="<?= $e($lang->code()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?= $e($pageTitle ?? $lang->text('app.name')) ?>
    </title>

    <?php // the icons are served from here, so no script from another site runs on the pages ?>
    <link rel="stylesheet" href="/assets/fontawesome/css/fontawesome.min.css">
    <link rel="stylesheet" href="/assets/fontawesome/css/solid.min.css">
    <link rel="stylesheet" href="/assets/fontawesome/css/regular.min.css">
    <link rel="stylesheet" href="/css/style.css">
    <?php foreach ($stylesheets ?? [] as $stylesheet): ?>
        <link rel="stylesheet" href="<?= $e($stylesheet) ?>">
    <?php endforeach; ?>
    <script src="/js/themeInit.js"></script>
    <script src="/js/theme.js" defer></script>
</head>
<body>
    <?= $content ?>
    <?php foreach ($scripts ?? [] as $script): ?>
        <script src="<?= $e($script) ?>"></script>
    <?php endforeach; ?>
</body>
</html>