<?php
declare(strict_types=1);

namespace App\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Slim\Psr7\Response as SlimResponse;

final class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(private Auth $auth, private string $adminPath, private string $basePath) {}

    public function process(Request $request, Handler $handler): Response
    {
        if ($this->auth->utilizatorCurent() === null) {
            return (new SlimResponse())
                ->withHeader('Location', $this->basePath . $this->adminPath . '/login')
                ->withStatus(302);
        }
        return $handler->handle($request);
    }
}
