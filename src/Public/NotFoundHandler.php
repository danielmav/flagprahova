<?php
declare(strict_types=1);

namespace App\Public;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Views\Twig;
use Throwable;

/** 404/405 cu layout-ul sitului; dacă primul segment e o secțiune, cu meniul ei. */
final class NotFoundHandler
{
    public function __construct(
        private Twig $twig,
        private Context $ctx,
        private ResponseFactoryInterface $factory
    ) {
    }

    public function __invoke(Request $request, Throwable $e, bool $afiseaza, bool $log, bool $logDetalii): Response
    {
        $cale = $request->getUri()->getPath();
        $base = $this->ctx->base();
        if ($base !== '' && str_starts_with($cale, $base)) {
            $cale = substr($cale, strlen($base));
        }
        $seg      = explode('/', trim($cale, '/'))[0] ?? '';
        $sectiune = $seg !== '' ? $this->ctx->sectiune($seg) : null;
        $status   = $e instanceof HttpMethodNotAllowedException ? 405 : 404;
        $response = $this->factory->createResponse($status);
        return $this->twig->render($response, 'eroare.twig', $this->ctx->variabile($sectiune, $cale, ['status' => $status]));
    }
}
