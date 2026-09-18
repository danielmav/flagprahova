<?php
declare(strict_types=1);

use App\Admin\AuthMiddleware;
use App\Admin\LoginController;
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

    $adminPath = (string) $container['settings']['admin']['path'];
    $basePath  = (string) $container['settings']['app']['base_path'];

    $app->get($adminPath . '/login', fn($rq, $rs) => (new LoginController($twig, $container))->form($rq, $rs));
    $app->post($adminPath . '/login', fn($rq, $rs) => (new LoginController($twig, $container))->submit($rq, $rs));
    $app->post($adminPath . '/logout', fn($rq, $rs) => (new LoginController($twig, $container))->logout($rq, $rs));

    $app->group($adminPath, function ($g) use ($twig, $container) {
        $g->get('', fn($rq, $rs) => (new LoginController($twig, $container))->dashboard($rq, $rs));
        // Task 5+: meniu, fisiere, setari, utilizatori, mesaje
    })->add(new AuthMiddleware($container['auth'], $adminPath, $basePath));
};
