<?php
declare(strict_types=1);

namespace App\Admin;

use App\Database;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

final class MesajeController
{
    use Helpers;

    private Auth $auth;
    private array $settings;
    private Database $db;

    public function __construct(private Twig $twig, array $container)
    {
        $this->auth     = $container['auth'];
        $this->settings = $container['settings'];
        $this->db       = $container['db'];
    }

    public function index(Request $request, Response $response): Response
    {
        $p   = max(1, (int) ($request->getQueryParams()['p'] ?? 1));
        $pdo = $this->db->pdo();
        $total = (int) $pdo->query('SELECT COUNT(*) FROM mesaje_contact')->fetchColumn();
        $rows  = $pdo->query('SELECT m.*, s.slug AS sectiune FROM mesaje_contact m LEFT JOIN sectiuni s ON s.id = m.sectiune_id ORDER BY m.trimis_la DESC, m.id DESC LIMIT 100 OFFSET ' . (($p - 1) * 100))->fetchAll();
        return $this->render($response, 'admin/mesaje.twig', ['mesaje' => $rows, 'pagina' => $p, 'pagini' => (int) ceil($total / 100)]);
    }
}
