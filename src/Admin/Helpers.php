<?php
declare(strict_types=1);

namespace App\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/** Comun tuturor controllerelor de admin. Cere proprietățile $twig (Slim\Views\Twig), $settings și $auth. */
trait Helpers
{
    protected function adminPath(): string
    {
        return (string) $this->settings['admin']['path'];
    }

    protected function redirect(Response $response, string $cale = ''): Response
    {
        $base = (string) $this->settings['app']['base_path'];
        return $response->withHeader('Location', $base . $this->adminPath() . $cale)->withStatus(302);
    }

    protected function csrfOk(Request $request): bool
    {
        $in = (array) $request->getParsedBody();
        $tok = (string) ($in['_csrf'] ?? $request->getHeaderLine('X-CSRF'));
        return $tok !== '' && hash_equals((string) ($_SESSION['csrf'] ?? ''), $tok);
    }

    protected function flash(string $tip, string $mesaj): void
    {
        $_SESSION['flash'] = ['tip' => $tip, 'mesaj' => $mesaj];
    }

    protected function preiaFlash(): ?array
    {
        $f = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        return is_array($f) ? $f : null;
    }

    /** @param array<string,mixed> $vars */
    protected function render(Response $response, string $template, array $vars = []): Response
    {
        return $this->twig->render($response, $template, $vars + [
            'utilizator' => $this->auth->user(),
            'flash'      => $this->preiaFlash(),
        ]);
    }

    protected function json(Response $response, array $date, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($date, JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus($status);
    }
}
