<?php
declare(strict_types=1);

use App\Admin\AuthMiddleware;
use App\Admin\FisiereController;
use App\Admin\LoginController;
use App\Admin\MeniuController;
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

        $fc = fn() => new FisiereController($twig, $container);
        $g->get('/fisiere',                    fn($rq, $rs) => $fc()->index($rq, $rs));
        $g->post('/fisiere/incarca',           fn($rq, $rs) => $fc()->incarca($rq, $rs));
        $g->post('/fisiere/editor',            fn($rq, $rs) => $fc()->editor($rq, $rs));
        $g->post('/fisiere/{id:[0-9]+}/redenumeste', fn($rq, $rs, $a) => $fc()->redenumeste($rq, $rs, $a));
        $g->post('/fisiere/{id:[0-9]+}/sterge',      fn($rq, $rs, $a) => $fc()->sterge($rq, $rs, $a));

        $mc = fn() => new MeniuController($twig, $container);
        $g->get('/meniu',                     fn($rq, $rs) => $mc()->index($rq, $rs));
        $g->get('/meniu/nou',                 fn($rq, $rs) => $mc()->nou($rq, $rs));
        $g->post('/meniu/salveaza',           fn($rq, $rs) => $mc()->salveaza($rq, $rs));
        $g->post('/meniu/reordoneaza',        fn($rq, $rs) => $mc()->reordoneaza($rq, $rs));
        $g->get('/meniu/{id:[0-9]+}',         fn($rq, $rs, $a) => $mc()->editeaza($rq, $rs, $a));
        $g->post('/meniu/{id:[0-9]+}/sterge', fn($rq, $rs, $a) => $mc()->sterge($rq, $rs, $a));
        // Task 8+: setari, utilizatori, mesaje
    })->add(new AuthMiddleware($container['auth'], $adminPath, $basePath));
};
