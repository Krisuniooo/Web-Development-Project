<?php

declare(strict_types=1);

namespace App\Controller;

final class PanelController extends AppController
{
    public function overview(): void
    {
        $this->renderSection('overview');
    }

    public function channels(): void
    {
        $this->renderSection('channels');
    }

    public function models(): void
    {
        $this->renderSection('models');
    }

    public function limits(): void
    {
        $this->renderSection('limits');
    }

    public function logs(): void
    {
        $this->renderSection('logs');
    }

    public function settings(): void
    {
        $this->renderSection('settings');
    }

    private function renderSection(string $section): void
    {
        $navigation = require dirname(__DIR__, 2) . '/config/panel-navigation.php';

        $this->render('panel', [
            'pageTitle' => $navigation[$section]['name'],
            'navigation' => $navigation,
            'activeSection' => $section,
        ]);
    }
}
