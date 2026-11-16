<?php

declare(strict_types=1);

namespace App\Controller;

use App\Lang;

final class DefaultController extends AppController
{
    public function index(): void
    {
        $this->render('home', [
            'pageTitle' => $this->lang()->text('home.title'),
            'models' => require dirname(__DIR__, 2) . '/config/models.php',
            'chatMessages' => $this->loadChatMessages(),
            'eyeFrames' => $this->loadEyeFrames(),
            'platforms' => require dirname(__DIR__, 2) . '/config/platforms.php',
            'howItWorks' => $this->loadHowItWorks(),
        ]);
    }

    /** For an error nobody caught: the page, or JSON for a request sent by a script. */
    public function serverError(): void
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';

        if (str_contains($accept, 'application/json') && !str_contains($accept, 'text/html')) {
            $this->json(['error' => $this->lang()->text('servererror.text')], 500);
            return;
        }

        $this->render('errors/server-error', [
            'pageTitle' => $this->lang()->text('servererror.title'),
        ]);
    }

    public function privacy(): void
    {
        $this->renderLegal('privacy', 'legal.privacy');
    }

    public function terms(): void
    {
        $this->renderLegal('terms', 'legal.terms');
    }

    /** A legal page from config/legal/, in the page's language, with the name, operator and contact filled in. */
    private function renderLegal(string $document, string $titleKey): void
    {
        $configPath = dirname(__DIR__, 2) . '/config';
        $legal = require "$configPath/legal/$document.php";
        $app = require "$configPath/app.php";
        // a value not set yet stands out on the page instead of leaving a gap
        $missing = '[' . $this->lang()->text('legal.missing') . ']';
        $fill = fn(string $text): string => strtr($text, [
            '{app}' => $app['name'],
            '{operator}' => $app['operator'] ?? $missing,
            '{contact}' => $app['contact'] ?? $missing,
        ]);

        $sections = array_map(fn(array $section): array => [
            'heading' => $fill($section['heading']),
            // a block is a paragraph, or a list of points
            'blocks' => array_map(
                fn(string|array $block): string|array => is_array($block) ? array_map($fill, $block) : $fill($block),
                $section['blocks'],
            ),
        ], $legal[$this->lang()->code()]);

        $this->render('legal', [
            'pageTitle' => $this->lang()->text($titleKey),
            'updated' => $legal['updated'],
            'sections' => $sections,
            // for the header, which lists them
            'platforms' => require "$configPath/platforms.php",
        ]);
    }

    public function status(): void
    {
        $this->render('status', [
            'pageTitle' => $this->lang()->text('status.title'),
            'platforms' => require dirname(__DIR__, 2) . '/config/platforms.php',
        ]);
    }

    public function login(): void
    {
        if ($this->loggedInUser() !== null) {
            $this->redirect('/app');
        }

        $this->render('login', [
            'pageTitle' => $this->lang()->text('login.title'),
            'loginError' => $this->loginErrorKey(),
        ]);
    }

    public function setLanguage(): void
    {
        // like every other form: another site cannot change the language for the visitor
        if (!$this->isSameOriginRequest()) {
            http_response_code(403);
            return;
        }

        Lang::remember($_POST['lang'] ?? null);

        $this->redirect($this->localPathOrHome($_POST['back'] ?? null));
    }

    private function loginErrorKey(): ?string
    {
        $errors = [
            'cancelled' => 'login.error.cancelled',
            'failed' => 'login.error.failed',
            'not_configured' => 'login.error.not_configured',
        ];
        $error = $_GET['error'] ?? null;

        return is_string($error) ? ($errors[$error] ?? null) : null;
    }

    private function localPathOrHome(mixed $path): string
    {
        // browsers read /\ and a slash after a tab or newline as another host
        $isLocal = is_string($path) && preg_match('~^/(?![/\\\\])[^\x00-\x1F\x7F]*\z~', $path) === 1;

        return $isLocal ? $path : '/';
    }

    private function loadChatMessages(): array
    {
        $messages = require dirname(__DIR__, 2) . '/config/chat-messages.php';

        return array_map(fn(array $message): array => isset($message['category'])
            ? [...$message, 'category' => $this->lang()->text($message['category'])]
            : $message, $messages[$this->lang()->code()]);
    }

    private function loadHowItWorks(): array
    {
        $howItWorks = require dirname(__DIR__, 2) . '/config/how-it-works.php';

        $translateName = fn(array $item): array => [...$item, 'name' => $this->lang()->text($item['name'])];

        $howItWorks['levels'] = array_map($translateName, $howItWorks['levels']);
        $howItWorks['limits'] = array_map(fn(array $limit): array => [
            ...$translateName($limit),
            'level' => $this->findLevelName($howItWorks['levels'], $limit['value']),
        ], $howItWorks['limits']);

        return $howItWorks;
    }

    private function findLevelName(array $levels, int $value): string
    {
        foreach ($levels as $level) {
            if ($value <= $level['max']) {
                return $level['name'];
            }
        }

        return '';
    }

    private function loadEyeFrames(): array
    {
        $configPath = dirname(__DIR__, 2) . '/config';
        $frames = require $configPath . '/eye.php';

        return array_map(fn(array $frame): array => [
            'duration' => $frame['duration'],
            'closed' => $frame['closed'] ?? false,
            'lines' => array_map('rtrim', file($configPath . '/eye/' . $frame['file'])),
        ], $frames);
    }
}