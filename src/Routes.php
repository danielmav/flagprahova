<?php
declare(strict_types=1);

use Slim\App;
use Slim\Views\Twig;

return function (App $app, Twig $twig, array $container): void {
    $app->get('/', function ($request, $response) use ($twig) {
        return $twig->render($response, 'home.twig', ['titlu' => 'FLAG Prahova']);
    })->setName('home');

    $app->get('/health', function ($request, $response) use ($container) {
        $ok = true;
        try { $container['db']->pdo()->query('SELECT 1'); } catch (\Throwable) { $ok = false; }
        $response->getBody()->write(json_encode(['ok' => $ok], JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($ok ? 200 : 500);
    });
};
