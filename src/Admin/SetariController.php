<?php
declare(strict_types=1);

namespace App\Admin;

use App\Setari\Repository as Setari;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

final class SetariController
{
    use Helpers;

    private Auth $auth;
    private array $settings;
    private Setari $setari;

    public function __construct(private Twig $twig, array $container)
    {
        $this->auth     = $container['auth'];
        $this->settings = $container['settings'];
        $this->setari   = $container['setari'];
    }

    public function form(Request $request, Response $response): Response
    {
        return $this->render($response, 'admin/setari.twig', ['valori' => $this->setari->toate(), 'chei' => Setari::CHEI]);
    }

    public function salveaza(Request $request, Response $response): Response
    {
        if ($this->csrfOk($request)) {
            $in = (array) $request->getParsedBody();
            foreach (Setari::CHEI as $c) {
                if (array_key_exists($c, $in)) {
                    $this->setari->set($c, trim((string) $in[$c]));
                }
            }
            $this->flash('ok', 'Setări salvate.');
        }
        return $this->redirect($response, '/setari');
    }
}
