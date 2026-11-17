<?php

declare(strict_types=1);

use App\Controller\AuthController;
use App\Controller\ChannelController;
use App\Controller\DefaultController;
use App\Controller\MessageCheckController;
use App\Controller\PanelController;
use App\Routing;

require_once dirname(__DIR__) . '/vendor/autoload.php';

$routing = new Routing();

$routing->get('/', DefaultController::class, 'index');
$routing->post('/language', DefaultController::class, 'setLanguage');
$routing->get('/status', DefaultController::class, 'status');
$routing->get('/privacy', DefaultController::class, 'privacy');
$routing->get('/terms', DefaultController::class, 'terms');
$routing->get('/login', DefaultController::class, 'login');
$routing->get('/auth/discord', AuthController::class, 'discord');
$routing->get('/auth/discord/callback', AuthController::class, 'discordCallback');
$routing->get('/auth/twitch', AuthController::class, 'twitch');
$routing->get('/auth/twitch/callback', AuthController::class, 'twitchCallback');
$routing->get('/auth/twitch/bot', AuthController::class, 'twitchBot');
$routing->get('/auth/twitch/bot/result', AuthController::class, 'twitchBotResult');
$routing->post('/logout', AuthController::class, 'logout');
$routing->get('/app', PanelController::class, 'overview');
$routing->get('/app/servers', PanelController::class, 'servers');
$routing->post('/app/manage', PanelController::class, 'manage');
$routing->post('/app/servers/connect', PanelController::class, 'connect');
$routing->get('/app/channels', PanelController::class, 'channels');
$routing->post('/app/channels/language', ChannelController::class, 'setLanguage');
$routing->post('/app/channels/status', ChannelController::class, 'setStatus');
$routing->post('/app/channels/connect', ChannelController::class, 'connect');
$routing->post('/app/channels/change', ChannelController::class, 'change');
$routing->get('/app/models', PanelController::class, 'models');
$routing->post('/app/models/model', ChannelController::class, 'setModel');
$routing->get('/app/limits', PanelController::class, 'limits');
$routing->post('/app/limits/rules', ChannelController::class, 'saveRules');
$routing->post('/app/limits/exempt-admins', ChannelController::class, 'setAdminsExempt');
$routing->post('/app/limits/exempt-role', ChannelController::class, 'setRoleExempt');
$routing->get('/app/model-status', MessageCheckController::class, 'modelStatus');
$routing->post('/app/check-message', MessageCheckController::class, 'check');
$routing->get('/app/logs', PanelController::class, 'logs');
$routing->get('/app/logs/{id}', PanelController::class, 'logEvent');

// what a request prints waits here, so an error in the middle of a page leaves no half of it behind
ob_start();

try {
    $routeFound = $routing->dispatch(
        $_SERVER['REQUEST_METHOD'],
        $_SERVER['REQUEST_URI'],
    );

    if (!$routeFound) {
        (new DefaultController())->notFound();
    }
} catch (Throwable $error) {
    // the log gets what happened and where; only the first line, since a database error can quote a row after it
    error_log(sprintf(
        'Uncaught %s: %s in %s:%d',
        $error::class,
        strtok($error->getMessage(), "\n"),
        $error->getFile(),
        $error->getLine(),
    ));

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    http_response_code(500);

    try {
        (new DefaultController())->serverError();
    } catch (Throwable) {
        // the error page failed too, so the visitor gets the plainest answer
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: text/plain; charset=utf-8');
        echo '500 Internal Server Error';
    }
}