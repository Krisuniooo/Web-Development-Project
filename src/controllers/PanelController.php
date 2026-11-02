<?php

declare(strict_types=1);

namespace App\Controller;

use App\AccountRepository;
use App\Database;
use App\Model\Account;
use RuntimeException;

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
        $account = $this->requireAccount();
        $navigation = require dirname(__DIR__, 2) . '/config/panel-navigation.php';
        $platforms = require dirname(__DIR__, 2) . '/config/platforms.php';

        $this->render('panel', [
            'pageTitle' => $this->lang()->text($navigation[$section]['name']),
            'navigation' => $navigation,
            'activeSection' => $section,
            'currentAccount' => $account,
            'currentPlatformName' => array_column($platforms, 'name', 'slug')[$account->getPlatform()] ?? $account->getPlatform(),
        ]);
    }

    /** Returns the logged-in account, or sends the visitor to the login page. */
    private function requireAccount(): Account
    {
        $accountId = $this->loggedInAccountId();

        if ($accountId === null) {
            $this->redirect('/login');
        }

        try {
            $account = (new AccountRepository((new Database())->connect()))->find($accountId);
        } catch (RuntimeException $exception) {
            error_log('Cannot read the logged-in account: ' . $exception->getMessage());
            // ended, or /login would send a logged-in visitor straight back here
            $this->endSession();
            $this->redirect('/login?error=failed');
        }

        if ($account === null) {
            $this->endSession();
            $this->redirect('/login');
        }

        return $account;
    }
}
