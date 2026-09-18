<?php
declare(strict_types=1);

namespace App;

use Dotenv\Dotenv;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;

final class Bootstrap
{
    public static function create(): App
    {
        $root = dirname(__DIR__);
        if (is_file($root . '/.env')) {
            Dotenv::createImmutable($root)->safeLoad();
        }
        /** @var array<string,mixed> $settings */
        $settings = require $root . '/config/settings.php';

        if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
            $https = (($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off')
                || ($_SERVER['SERVER_PORT'] ?? '') === '443';
            session_set_cookie_params([
                'lifetime' => 0,
                'path'     => ($settings['app']['base_path'] ?: '/'),
                'httponly' => true,
                'samesite' => 'Lax',
                'secure'   => $https,
            ]);
            session_name('fp_session');
            session_start();
        }
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }

        $app = AppFactory::create();
        if ($settings['app']['base_path'] !== '') {
            $app->setBasePath($settings['app']['base_path']);
        }
        $app->addRoutingMiddleware();
        $app->addBodyParsingMiddleware();

        $twig = Twig::create($settings['twig']['templates'], [
            'cache'       => $settings['twig']['cache'],
            'auto_reload' => true,
        ]);
        $env = $twig->getEnvironment();
        $env->addGlobal('app', $settings['app']);
        $env->addGlobal('base', $settings['app']['base_path']);
        $env->addGlobal('admin_path', $settings['admin']['path']);
        $env->addGlobal('csrf', $_SESSION['csrf']);
        $env->addGlobal('fisiere_url', $settings['upload']['url']);
        $app->add(TwigMiddleware::create($app, $twig));

        $app->add(function (Request $request, RequestHandler $handler): Response {
            $response = $handler->handle($request);
            return $response
                ->withHeader('X-Content-Type-Options', 'nosniff')
                ->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
                ->withHeader('X-Frame-Options', 'SAMEORIGIN');
        });

        $db = new Database($settings['db']);
        $container = [
            'settings' => $settings,
            'db'       => $db,
        ];
        self::extinde($container, $root, $env);

        $app->addErrorMiddleware((bool) $settings['app']['debug'], true, true);
        (require $root . '/src/Routes.php')($app, $twig, $container);
        return $app;
    }

    /**
     * Punctul în care task-urile următoare adaugă servicii în container și
     * funcții Twig. Ținut separat ca `create()` să rămână lizibil.
     * @param array<string,mixed> $container
     */
    private static function extinde(array &$container, string $root, \Twig\Environment $env): void
    {
        $container['auth']           = new Admin\Auth($container['db']);
        $container['login_throttle'] = new Admin\LoginThrottle($container['db'], 'admin');
        $container['meniu']          = new Meniu\Repository($container['db']);
    }
}
