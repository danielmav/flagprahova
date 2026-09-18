<?php
declare(strict_types=1);

use App\Admin\AuthMiddleware;
use App\Admin\FisiereController;
use App\Admin\LoginController;
use App\Admin\MeniuController;
use App\Admin\MesajeController;
use App\Admin\SetariController;
use App\Admin\UtilizatoriController;
use App\Public\PaginiController;
use Slim\App;
use Slim\Views\Twig;

return function (App $app, Twig $twig, array $container): void {
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

    // În afara grupului, ca login-ul: cine și-a uitat parola nu are sesiune.
    $app->map(['GET', 'POST'], $adminPath . '/parola-uitata', fn($rq, $rs) => (new UtilizatoriController($twig, $container))->parolaUitata($rq, $rs));
    $app->map(['GET', 'POST'], $adminPath . '/parola/{token:[a-f0-9]{64}}', fn($rq, $rs, $a) => (new UtilizatoriController($twig, $container))->parola($rq, $rs, $a));

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

        $sc = fn() => new SetariController($twig, $container);
        $g->get('/setari',  fn($rq, $rs) => $sc()->form($rq, $rs));
        $g->post('/setari', fn($rq, $rs) => $sc()->salveaza($rq, $rs));

        $uc = fn() => new UtilizatoriController($twig, $container);
        $g->get('/utilizatori',                           fn($rq, $rs) => $uc()->index($rq, $rs));
        $g->post('/utilizatori/adauga',                   fn($rq, $rs) => $uc()->adauga($rq, $rs));
        $g->post('/utilizatori/{id:[0-9]+}/trimite-link', fn($rq, $rs, $a) => $uc()->trimiteLinkActiune($rq, $rs, $a));
        $g->post('/utilizatori/{id:[0-9]+}/sterge',       fn($rq, $rs, $a) => $uc()->sterge($rq, $rs, $a));

        $msg = fn() => new MesajeController($twig, $container);
        $g->get('/mesaje', fn($rq, $rs) => $msg()->index($rq, $rs));
    })->add(new AuthMiddleware($container['auth'], $adminPath, $basePath));

    // Situl public. Ultimele, ca grupul de admin să rămână grupat deasupra.
    $pc = fn() => new PaginiController($twig, $container);
    $app->get('/', fn($rq, $rs) => $pc()->landing($rq, $rs))->setName('home');
    $app->get('/{perioada:[0-9]{4}-[0-9]{4}}',  fn($rq, $rs, $a) => $pc()->slash($rq, $rs, $a));
    $app->get('/{perioada:[0-9]{4}-[0-9]{4}}/', fn($rq, $rs, $a) => $pc()->acasa($rq, $rs, $a));
    // Regexul de perioadă garantează că ruta nu umbrește /sitemap.xml, /robots.txt,
    // /admin/... sau /fisiere/... .
    $app->get('/{perioada:[0-9]{4}-[0-9]{4}}/{slug:[a-z0-9-]+}', fn($rq, $rs, $a) => $pc()->pagina($rq, $rs, $a));
};
