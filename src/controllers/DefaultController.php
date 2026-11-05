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

    public function notFound(): void
    {
        $this->render('not-found', [
            'pageTitle' => $this->lang()->text('notfound.title'),
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