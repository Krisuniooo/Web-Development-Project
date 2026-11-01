<?php

declare(strict_types=1);

namespace App\Controller;

use App\Lang;

final class DefaultController extends AppController
{
    public function index(): void
    {
        $this->render('home', [
            'pageTitle' => 'Moja aplikacja',
            'models' => require dirname(__DIR__, 2) . '/config/models.php',
            'chatMessages' => require dirname(__DIR__, 2) . '/config/chat-messages.php',
            'eyeFrames' => $this->loadEyeFrames(),
            'platforms' => require dirname(__DIR__, 2) . '/config/platforms.php',
            'howItWorks' => $this->loadHowItWorks(),
        ]);
    }

    public function notFound(): void
    {
        $this->render('not-found', [
            'pageTitle' => 'Nie znaleziono strony',
        ]);
    }

    public function setLanguage(): void
    {
        Lang::remember($_POST['lang'] ?? null);

        header('Location: ' . $this->localPathOrHome($_POST['back'] ?? null), true, 303);
    }

    private function localPathOrHome(mixed $path): string
    {
        // browsers read /\ and a slash after a tab or newline as another host
        $isLocal = is_string($path) && preg_match('~^/(?![/\\\\])[^\x00-\x1F\x7F]*\z~', $path) === 1;

        return $isLocal ? $path : '/';
    }

    private function loadHowItWorks(): array
    {
        $howItWorks = require dirname(__DIR__, 2) . '/config/how-it-works.php';

        $howItWorks['limits'] = array_map(fn(array $limit): array => [
            ...$limit,
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