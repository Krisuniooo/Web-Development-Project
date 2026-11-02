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
$routing->get('/app/channels', PanelController::class, 'channels');
$routing->get('/app/models', PanelController::class, 'models');
$routing->get('/app/limits', PanelController::class, 'limits');
$routing->get('/app/logs', PanelController::class, 'logs');
$routing->get('/app/settings', PanelController::class, 'settings');

$routeFound = $routing->dispatch(
    $_SERVER['REQUEST_METHOD'],
    $_SERVER['REQUEST_URI'],
);

if (!$routeFound) {
    http_response_code(404);
    (new DefaultController())->notFound();
}