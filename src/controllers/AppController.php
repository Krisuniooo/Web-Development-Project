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

    /**
     * The platform account the session holds. Looks at the session only if the browser already has one,
     * so other visitors get no cookie.
     *
     * @return array{platform: string, id: string, name: string, avatarUrl: ?string}|null
     */
    protected function loggedInUser(): ?array
    {
        if (!isset($_COOKIE[session_name()])) {
            return null;
        }

        $this->startSession();
        $user = $_SESSION['user'] ?? null;

        $isValid = is_array($user)
            && is_string($user['platform'] ?? null)
            && is_string($user['id'] ?? null)
            && is_string($user['name'] ?? null);

        if (!$isValid) {
            return null;
        }

        // a session unused for longer than PHP keeps sessions ends now, even if the garbage collector,
        // which runs only now and then, has not removed it yet
        $lastActivity = $_SESSION['lastActivity'] ?? null;

        if (is_int($lastActivity) && $lastActivity < time() - (int) ini_get('session.gc_maxlifetime')) {
            $this->endSession();

            return null;
        }

        $_SESSION['lastActivity'] = time();

        return [
            'platform' => $user['platform'],
            'id' => $user['id'],
            'name' => $user['name'],
            // a session from before profile pictures has none
            'avatarUrl' => is_string($user['avatarUrl'] ?? null) ? $user['avatarUrl'] : null,
        ];
    }

    /** Whether a form came from this site. Every change sent by POST is checked with it. */
    protected function isSameOriginRequest(): bool
    {
        $fetchSite = $_SERVER['HTTP_SEC_FETCH_SITE'] ?? null;

        if ($fetchSite !== null) {
            return $fetchSite === 'same-origin';
        }

        $origin = $_SERVER['HTTP_ORIGIN'] ?? null;

        // browsers send one of these two headers on a form, and a request with neither carries no visitor's cookies
        return $origin === null || preg_replace('~^https?://~', '', $origin) === ($_SERVER['HTTP_HOST'] ?? '');
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

    /** Answers a request sent by the page's script; an error answer has its text in "error". */
    protected function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    public function notFound(): void
    {
        http_response_code(404);
        $this->render('errors/not-found', [
            'pageTitle' => $this->lang()->text('notfound.title'),
        ]);
    }

    /** For a logged-in visitor asking for something that belongs to a server they are not managing now. */
    public function forbidden(): void
    {
        http_response_code(403);
        $this->render('errors/forbidden', [
            'pageTitle' => $this->lang()->text('forbidden.title'),
        ]);
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

        // what every view has: $t for a text in the page's language and $e for any other value, both safe in HTML,
        // and $app with the settings of the app, such as the address of its repository
        $lang = $this->lang();
        $t = $lang->html(...);
        $e = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        $app = require dirname(__DIR__, 2) . '/config/app.php';
        $currentPath = $_SERVER['REQUEST_URI'];

        extract($variables, EXTR_SKIP);

        ob_start();
        require $templatePath;
        $content = (string) ob_get_clean();

        require $layoutPath;
    }
}