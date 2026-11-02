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

    protected function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_start([
            'use_strict_mode' => true,
            'cookie_httponly' => true,
            // Strict would drop the cookie when Discord sends the browser back
            'cookie_samesite' => 'Lax',
            'cookie_secure' => !empty($_SERVER['HTTPS']),
        ]);
    }

    /** Looks at the session only if the browser already has one, so other visitors get no cookie. */
    protected function loggedInAccountId(): ?int
    {
        if (!isset($_COOKIE[session_name()])) {
            return null;
        }

        $this->startSession();
        $accountId = $_SESSION['account_id'] ?? null;

        return is_int($accountId) ? $accountId : null;
    }

    protected function endSession(): void
    {
        $this->startSession();
        $_SESSION = [];

        $cookie = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => 1,
            'path' => $cookie['path'],
            'secure' => $cookie['secure'],
            'httponly' => $cookie['httponly'],
            'samesite' => $cookie['samesite'],
        ]);

        session_destroy();
    }

    protected function redirect(string $location): never
    {
        header('Location: ' . $location, true, 303);
        exit;
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