<?php

declare(strict_types=1);

namespace Ersus360\Controllers;

use Ersus360\Core\Request;
use Ersus360\Core\Response;
use Ersus360\Core\Database;
use Ersus360\Exceptions\HttpException;

final class AlertasController
{
    public function __construct(private readonly Database $db) {}

    public function index(Request $request): Response
    {
        $municipioId = $request->municipioId();
        $apenasNaoLidos = (bool) $request->query('nao_lidos', false);
        $tipo           = (string) $request->query('tipo', '');

        $where  = ['municipio_id = :mid'];
        $params = ['mid' => $municipioId];

        if ($apenasNaoLidos) {
            $where[] = 'lido = 0';
        }

        if ($tipo !== '') {
            $where[]      = 'tipo = :tipo';
            $params['tipo'] = $tipo;
        }

        $cond   = implode(' AND ', $where);
        $total  = (int) $this->db->scalar("SELECT COUNT(*) FROM alertas WHERE {$cond}", $params);

        $params['limit'] = 50;
        $alertas = $this->db->fetchAll(
            "SELECT * FROM alertas WHERE {$cond} ORDER BY criado_em DESC LIMIT :limit",
            $params,
        );

        $naoLidos = (int) $this->db->scalar(
            'SELECT COUNT(*) FROM alertas WHERE municipio_id = :mid AND lido = 0',
            ['mid' => $municipioId],
        );

        return Response::json([
            'total'     => $total,
            'nao_lidos' => $naoLidos,
            'alertas'   => $alertas,
        ]);
    }

    public function marcarLido(Request $request): Response
    {
        $municipioId = $request->municipioId();
        $id          = $request->paramInt('id');

        $alerta = $this->db->fetchOne(
            'SELECT id, municipio_id FROM alertas WHERE id = :id',
            ['id' => $id],
        );

        if (!$alerta || (int) $alerta['municipio_id'] !== $municipioId) {
            throw new HttpException(404, 'Alerta não encontrado.');
        }

        $this->db->execute(
            'UPDATE alertas SET lido = 1, lido_em = NOW(), lido_por = :uid WHERE id = :id',
            ['uid' => $request->userId(), 'id' => $id],
        );

        return Response::json(['mensagem' => 'Alerta marcado como lido.']);
    }

    public function marcarTodosLidos(Request $request): Response
    {
        $municipioId = $request->municipioId();

        $total = $this->db->execute(
            'UPDATE alertas SET lido = 1, lido_em = NOW(), lido_por = :uid
             WHERE municipio_id = :mid AND lido = 0',
            ['uid' => $request->userId(), 'mid' => $municipioId],
        );

        return Response::json(['mensagem' => "Marcados {$total} alertas como lidos.", 'total' => $total]);
    }
}
