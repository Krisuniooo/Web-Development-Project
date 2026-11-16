<?php

declare(strict_types=1);

namespace App\Controller;

use App\Config;
use App\Lang;

final class DefaultController extends AppController
{
    public function index(): void
    {
        $this->render('home', [
            'pageTitle' => $this->lang()->text('home.title'),
            'models' => Config::load('models'),
            'chatMessages' => $this->loadChatMessages(),
            'eyeFrames' => $this->loadEyeFrames(),
            'platforms' => Config::load('platforms'),
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
        $legal = Config::load("legal/$document");
        $app = Config::load('app');
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
            'platforms' => Config::load('platforms'),
        ]);
    }

    public function status(): void
    {
        $this->render('status', [
            'pageTitle' => $this->lang()->text('status.title'),
            'platforms' => Config::load('platforms'),
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
        // another site cannot change the visitor's language
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
        $messages = Config::load('chat-messages');

        return array_map(fn(array $message): array => isset($message['category'])
            ? [...$message, 'category' => $this->lang()->text($message['category'])]
            : $message, $messages[$this->lang()->code()]);
    }

    private function loadHowItWorks(): array
    {
        $howItWorks = Config::load('how-it-works');

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
        return array_map(fn(array $frame): array => [
            'duration' => $frame['duration'],
            'closed' => $frame['closed'] ?? false,
            'lines' => array_map('rtrim', file(Config::path('eye/' . $frame['file']))),
        ], Config::load('eye'));
    }
}
