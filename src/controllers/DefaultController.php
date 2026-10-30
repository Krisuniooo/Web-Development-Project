<?php

declare(strict_types=1);

namespace App\Controller;

final class DefaultController extends AppController
{
    public function index(): void
    {
        $this->render('home', [
            'pageTitle' => 'Moja aplikacja',
            'models' => require dirname(__DIR__, 2) . '/config/models.php',
            'chatMessages' => require dirname(__DIR__, 2) . '/config/chat-messages.php',
            'eyeFrames' => $this->loadEyeFrames(),
            'ctaPlatforms' => require dirname(__DIR__, 2) . '/config/cta-platforms.php',
            'howItWorks' => require dirname(__DIR__, 2) . '/config/how-it-works.php',
        ]);
    }

    public function notFound(): void
    {
        $this->render('not-found', [
            'pageTitle' => 'Nie znaleziono strony',
        ]);
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