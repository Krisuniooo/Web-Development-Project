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

    public function switchAccount(): void
    {
        $this->startSession();

        $accountId = (int) filter_input(INPUT_POST, 'account', FILTER_VALIDATE_INT);

        if (isset($this->loadAccounts()[$accountId])) {
            $_SESSION['accountId'] = $accountId;
        }

        $section = (string) filter_input(INPUT_POST, 'section');
        $navigation = $this->loadNavigation();

        header('Location: ' . ($navigation[$section]['path'] ?? '/app'), true, 303);
    }

    private function renderSection(string $section): void
    {
        $this->startSession();

        $navigation = $this->loadNavigation();
        $accounts = $this->loadAccounts();

        $accountId = $_SESSION['accountId'] ?? 0;
        if (!isset($accounts[$accountId])) {
            $accountId = array_key_first($accounts);
        }

        $currentAccount = $accounts[$accountId];
        unset($accounts[$accountId]);

        $this->render('panel', [
            'pageTitle' => $navigation[$section]['name'],
            'navigation' => $navigation,
            'activeSection' => $section,
            'currentAccount' => $currentAccount,
            'otherAccounts' => $accounts,
        ]);
    }

    private function startSession(): void
    {
        session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Lax']);
    }

    private function loadNavigation(): array
    {
        return require dirname(__DIR__, 2) . '/config/panel-navigation.php';
    }

    private function loadAccounts(): array
    {
        $configPath = dirname(__DIR__, 2) . '/config';
        $platformNames = array_column(require $configPath . '/platforms.php', 'name', 'slug');

        return array_map(fn(array $account): array => [
            ...$account,
            'platformName' => $platformNames[$account['platform']],
        ], require $configPath . '/panel-accounts.php');
    }
}
