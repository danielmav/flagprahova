<?php
declare(strict_types=1);

namespace App\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

final class LoginController
{
    use Helpers;

    private Auth $auth;
    private LoginThrottle $throttle;
    private array $settings;

    public function __construct(private Twig $twig, array $container)
    {
        $this->auth     = $container['auth'];
        $this->throttle = $container['login_throttle'];
        $this->settings = $container['settings'];
    }

    public function dashboard(Request $request, Response $response): Response
    {
        return $this->render($response, 'admin/dashboard.twig');
    }

    public function form(Request $request, Response $response): Response
    {
        if ($this->auth->check()) {
            return $this->redirect($response);
        }
        return $this->render($response, 'admin/login.twig', ['eroare' => null, 'email' => '']);
    }

    public function submit(Request $request, Response $response): Response
    {
        $in     = (array) $request->getParsedBody();
        $email  = trim((string) ($in['email'] ?? ''));
        $parola = (string) ($in['parola'] ?? '');
        $ip     = ip_hash($request->getServerParams()['REMOTE_ADDR'] ?? null);

        if (!$this->csrfOk($request)) {
            return $this->render($response, 'admin/login.twig', ['eroare' => 'Sesiunea a expirat. Încearcă din nou.', 'email' => $email]);
        }
        if ($this->throttle->tooMany($ip)) {
            return $this->render($response, 'admin/login.twig', ['eroare' => 'Prea multe încercări. Așteaptă 15 minute.', 'email' => $email]);
        }
        if ($email === '' || $parola === '' || !$this->auth->attempt($email, $parola)) {
            $this->throttle->record($ip);
            return $this->render($response, 'admin/login.twig', ['eroare' => 'Email sau parolă greșite.', 'email' => $email]);
        }
        $this->throttle->clear($ip);
        return $this->redirect($response);
    }

    public function logout(Request $request, Response $response): Response
    {
        if ($this->csrfOk($request)) {
            $this->auth->logout();
        }
        return $this->redirect($response, '/login');
    }
}
