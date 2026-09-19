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
            // Fără cache limiter: implicit PHP trimite `Expires: Thu, 19 Nov 1981`
            // + `Pragma: no-cache` pe TOATE răspunsurile cu sesiune, inclusiv pe
            // paginile publice. Cache-ul îl controlăm noi, pe rută.
            session_cache_limiter('');
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
            // `X-Powered-By` vine din `expose_php` în php.ini (nu din PSR-7), deci
            // se scoate din lista de headere deja programate ale SAPI-ului.
            if (PHP_SAPI !== 'cli' && !headers_sent()) {
                header_remove('X-Powered-By');
            }
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
        $container['miniatura']      = new Fisiere\Miniatura($container['settings']['upload']['dir']);
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

        self::functiiTwig($env, $container['settings'], $container['context']);

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
    public static function functiiTwig(\Twig\Environment $env, array $settings, Public\Context $ctx): void
    {
        $base      = (string) $settings['app']['base_path'];
        $fisiere   = (string) $settings['upload']['url'];

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
        // Conținutul din editor are `src`/`href` absolute față de rădăcina sitului
        // (`/fisiere/2017/08/x.jpg`). Pe staging (BASE_PATH=/nou) ele ar cădea pe
        // situl vechi, deci prefixăm baza la randare; nu la salvare, ca HTML-ul din
        // bază să rămână portabil între medii. `//cdn` (protocol-relative) și căile
        // care poartă deja baza rămân neatinse.
        $env->addFilter(new \Twig\TwigFilter('cu_baza', static function (?string $html) use ($base): string {
            $html = (string) $html;
            if ($base === '' || $html === '') {
                return $html;
            }
            return (string) preg_replace(
                // `(?<![\w-])`: nu și `data-src="/…"`.
                '#(?<![\w-])(src|href)=(["\'])/(?!/|' . preg_quote(ltrim($base, '/'), '#') . '/)#',
                '$1=$2' . $base . '/',
                $html
            );
        }));
        // „12 iunie 2019" din `publicat_la`, altfel „august 2026" din calea fișierului
        // (`AAAA/LL/...`); gol dacă nu avem nimic.
        $env->addFunction(new \Twig\TwigFunction('data_publicare', static function (?string $data, ?string $cale): string {
            $luni = ['ianuarie', 'februarie', 'martie', 'aprilie', 'mai', 'iunie', 'iulie', 'august', 'septembrie', 'octombrie', 'noiembrie', 'decembrie'];
            if ($data !== null && preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $data, $m)) {
                return (int) $m[3] . ' ' . $luni[(int) $m[2] - 1] . ' ' . $m[1];
            }
            if ($cale !== null && preg_match('#^(\d{4})/(0[1-9]|1[0-2])/#', $cale, $m)) {
                return $luni[(int) $m[2] - 1] . ' ' . $m[1];
            }
            return '';
        }));
        // URL absolut, pentru canonical/OG/JSON-LD. Regula (APP_URL include deja
        // BASE_PATH) stă o singură dată, în `Context::urlPublic()`.
        $env->addFunction(new \Twig\TwigFunction(
            'url_public',
            static fn(?string $cale): string => $ctx->urlPublic((string) $cale)
        ));
    }
}
