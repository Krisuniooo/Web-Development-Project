<?php

declare(strict_types=1);

use App\Controller\AuthController;
use App\Controller\DefaultController;
use App\Controller\PanelController;
use App\Routing;

require_once dirname(__DIR__) . '/vendor/autoload.php';

$routing = new Routing();

$routing->get('/', DefaultController::class, 'index');
$routing->post('/language', DefaultController::class, 'setLanguage');
$routing->get('/status', DefaultController::class, 'status');
$routing->get('/login', DefaultController::class, 'login');
$routing->get('/auth/discord', AuthController::class, 'discord');
$routing->get('/auth/discord/callback', AuthController::class, 'discordCallback');
$routing->post('/logout', AuthController::class, 'logout');
$routing->get('/app', PanelController::class, 'overview');
$routing->post('/app/manage', PanelController::class, 'manage');
$routing->get('/app/channels', PanelController::class, 'channels');
$routing->post('/app/channels/language', PanelController::class, 'setChannelLanguage');
$routing->post('/app/channels/status', PanelController::class, 'setChannelStatus');
$routing->post('/app/channels/connect', PanelController::class, 'connectChannel');
$routing->post('/app/channels/change', PanelController::class, 'changeChannel');
$routing->get('/app/models', PanelController::class, 'models');
$routing->get('/app/limits', PanelController::class, 'limits');
$routing->post('/app/limits/rules', PanelController::class, 'saveRules');
$routing->get('/app/model-status', PanelController::class, 'modelStatus');
$routing->post('/app/check-message', PanelController::class, 'checkMessage');
$routing->get('/app/logs', PanelController::class, 'logs');

// what a request prints waits here, so an error in the middle of a page leaves no half of it behind
ob_start();

try {
    $routeFound = $routing->dispatch(
        $_SERVER['REQUEST_METHOD'],
        $_SERVER['REQUEST_URI'],
    );

    if (!$routeFound) {
        http_response_code(404);
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