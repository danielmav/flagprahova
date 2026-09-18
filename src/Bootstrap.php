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

        // 404/405 primesc layout-ul public (cu meniul secțiunii, când calea o numește),
        // nu pagina albă a lui Slim. Restul erorilor rămân pe handler-ul implicit.
        $errors   = $app->addErrorMiddleware((bool) $settings['app']['debug'], true, true);
        $notFound = new Public\NotFoundHandler($twig, $container['context'], $app->getResponseFactory());
        $errors->setErrorHandler(\Slim\Exception\HttpNotFoundException::class, $notFound);
        $errors->setErrorHandler(\Slim\Exception\HttpMethodNotAllowedException::class, $notFound);

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
        $container['fisiere']        = new Fisiere\Repository($container['db']);
        $container['galerie']        = new Meniu\GalerieRepository($container['db']);
        $container['upload']         = new Fisiere\Upload($container['settings']['upload']['dir'], $container['settings']['upload']['max_bytes']);
        $container['setari']         = new Setari\Repository($container['db']);
        $container['mailer']         = new Mail\Mailer(
            $container['settings']['mail'],
            $root . '/storage/logs/mail.log',
            (string) $container['settings']['app']['env']
        );
        $container['utilizatori']    = new Admin\UtilizatoriRepository($container['db']);
        $container['parola_tokens']  = new Admin\PasswordTokenRepository($container['db']);
        // Scope separat de 'admin': un atac pe „parolă uitată" nu trebuie să
        // blocheze login-ul normal de pe același IP, și invers.
        $container['parola_throttle'] = new Admin\LoginThrottle($container['db'], 'parola');
        $container['context']        = new Public\Context(
            $container['meniu'],
            $container['fisiere'],
            $container['setari'],
            $container['settings']
        );

        self::functiiTwig($env, $container['settings']);

        if (($container['settings']['db_wp']['name'] ?? '') !== '') {
            $container['legacy'] = static function () use ($container): Migrare\Legacy {
                $c = $container['settings']['db_wp'];
                $pdo = new \PDO(sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $c['host'], $c['port'], $c['name']), $c['user'], $c['pass'], [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC, \PDO::ATTR_EMULATE_PREPARES => false,
                ]);
                return new Migrare\Legacy($pdo, (string) $c['prefix']);
            };
        }
    }

    /**
     * Funcțiile Twig folosite de șabloanele publice (documente, galerii).
     * @param array<string,mixed> $settings
     */
    private static function functiiTwig(\Twig\Environment $env, array $settings): void
    {
        $base      = (string) $settings['app']['base_path'];
        $fisiere   = (string) $settings['upload']['url'];
        $urlPublic = (string) $settings['app']['url'];

        // „1,2 MB" / „340 KB": separatorii românești, o zecimală doar la MB.
        $env->addFunction(new \Twig\TwigFunction('marime', static function (int|string|null $b): string {
            $b = (int) $b;
            if ($b >= 1048576) {
                return number_format($b / 1048576, 1, ',', '.') . ' MB';
            }
            if ($b >= 1024) {
                return (string) round($b / 1024) . ' KB';
            }
            return $b . ' B';
        }));
        $env->addFunction(new \Twig\TwigFunction(
            'ext',
            static fn(?string $cale): string => strtolower(pathinfo((string) $cale, PATHINFO_EXTENSION))
        ));
        // Miniatura WebP generată la cerere (task ulterior); extensia sursei e înlocuită.
        $env->addFunction(new \Twig\TwigFunction(
            'mini',
            static fn(string $cale, int $latime): string => $base . $fisiere . '/mini/' . $latime . '/'
                . preg_replace('/\.[^.\/]+$/', '.webp', $cale)
        ));
        // URL absolut, pentru OG/JSON-LD/sitemap.
        $env->addFunction(new \Twig\TwigFunction(
            'url_public',
            static fn(string $cale): string => $urlPublic . $cale
        ));
    }
}
