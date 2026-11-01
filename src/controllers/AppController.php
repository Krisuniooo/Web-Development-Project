<?php

declare(strict_types=1);

namespace App\Controller;

use App\Lang;

abstract class AppController
{
    private ?Lang $lang = null;

    protected function lang(): Lang
    {
        return $this->lang ??= Lang::fromRequest();
    }

    protected function render(string $template, array $variables = []): void
    {
        $viewsPath = dirname(__DIR__, 2) . '/public/views';
        $templatePath = $viewsPath . '/' . $template . '.html.php';
        $layoutPath = $viewsPath . '/layout.html.php';

        if (!is_file($templatePath)) {
            throw new \RuntimeException('View not found: ' . $template);
        }

        $lang = $this->lang();
        $t = $lang->html(...);
        $currentPath = $_SERVER['REQUEST_URI'];

        extract($variables, EXTR_SKIP);

        ob_start();
        require $templatePath;
        $content = (string) ob_get_clean();

        require $layoutPath;
    }
}