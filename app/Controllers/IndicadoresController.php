<?php

declare(strict_types=1);

namespace Ersus360\Controllers;

use Ersus360\Core\Request;
use Ersus360\Core\Response;
use Ersus360\Core\Database;
use Ersus360\Exceptions\HttpException;

final class IndicadoresController
{
    public function __construct(private readonly Database $db) {}

    public function index(Request $request): Response
    {
        $municipioId = $request->municipioId();
        $competencia = (string) $request->query('competencia', date('Y-m') . '-01');
        $categoria   = (string) $request->query('categoria', '');

        $where  = ['municipio_id = :mid', 'competencia = :comp'];
        $params = ['mid' => $municipioId, 'comp' => $competencia];

        if ($categoria !== '') {
            $where[]            = 'categoria = :cat';
            $params['cat']      = $categoria;
        }

        $cond        = implode(' AND ', $where);
        $indicadores = $this->db->fetchAll(
            "SELECT codigo, nome, categoria, valor_absoluto, valor_meta,
                    percentual, situacao, fonte, atualizado_em
             FROM indicadores
             WHERE {$cond}
             ORDER BY situacao DESC, nome",
            $params,
        );

        $resumo = $this->db->fetchOne(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN situacao = 'adequado' THEN 1 ELSE 0 END) AS adequados,
                SUM(CASE WHEN situacao = 'critico'  THEN 1 ELSE 0 END) AS criticos,
                SUM(CASE WHEN situacao = 'alerta'   THEN 1 ELSE 0 END) AS alertas,
                SUM(CASE WHEN situacao = 'sem_dado' THEN 1 ELSE 0 END) AS sem_dado
             FROM indicadores
             WHERE {$cond}",
            $params,
        );

        return Response::json([
            'competencia' => $competencia,
            'resumo'      => $resumo,
            'indicadores' => $indicadores,
        ]);
    }

    public function dashboard(Request $request): Response
    {
        $municipioId = $request->municipioId();

        // Última competência com dados
        $ultimaComp = $this->db->scalar(
            'SELECT MAX(competencia) FROM indicadores WHERE municipio_id = :mid',
            ['mid' => $municipioId],
        );

        if (!$ultimaComp) {
            return Response::json(['mensagem' => 'Nenhum indicador disponível.', 'indicadores' => []]);
        }

        $criticos = $this->db->fetchAll(
            "SELECT codigo, nome, categoria, valor_absoluto, valor_meta, percentual, situacao
             FROM indicadores
             WHERE municipio_id = :mid AND competencia = :comp AND situacao IN ('critico','alerta')
             ORDER BY situacao DESC, nome
             LIMIT 10",
            ['mid' => $municipioId, 'comp' => $ultimaComp],
        );

        return Response::json([
            'ultima_competencia' => substr($ultimaComp, 0, 7),
            'criticos_alerta'    => $criticos,
        ]);
    }

    public function sincronizar(Request $request): Response
    {
        // Placeholder — implementação real via SiapsClient + eSUSClient
        return Response::json(['mensagem' => 'Sincronização de indicadores agendada.']);
    }
}
