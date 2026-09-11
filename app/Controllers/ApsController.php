<?php

declare(strict_types=1);

namespace Ersus360\Controllers;

use Ersus360\Core\Request;
use Ersus360\Core\Response;
use Ersus360\Core\Database;
use Ersus360\Exceptions\HttpException;
use Ersus360\Services\EGestorService;

final class ApsController
{
    public function __construct(private readonly Database $db) {}

    public function index(Request $request): Response
    {
        $municipioId      = $request->municipioId();
        $competenciaInicio = $request->query('competencia_inicio');
        $competenciaFim    = $request->query('competencia_fim');
        $bloco             = $request->query('bloco');
        $pagina            = max(1, (int) $request->query('pagina', 1));
        $porPagina         = min(200, max(10, (int) $request->query('por_pagina', 50)));

        $where  = ['municipio_id = :mid'];
        $params = ['mid' => $municipioId];

        if ($competenciaInicio) {
            $where[]      = 'competencia >= :ci';
            $params['ci'] = $competenciaInicio . '-01';
        }
        if ($competenciaFim) {
            $where[]      = 'competencia <= :cf';
            $params['cf'] = $competenciaFim . '-01';
        }
        if ($bloco) {
            $where[]          = 'bloco = :bloco';
            $params['bloco']  = $bloco;
        }

        $cond  = implode(' AND ', $where);
        $total = (int) $this->db->scalar("SELECT COUNT(*) FROM repasses_aps WHERE {$cond}", $params);

        $params['limit']  = $porPagina;
        $params['offset'] = ($pagina - 1) * $porPagina;

        $repasses = $this->db->fetchAll(
            "SELECT id, competencia, bloco, componente, subcomponente,
                    valor_federal, valor_estadual, valor_municipal, valor_total,
                    situacao, data_credito
             FROM repasses_aps
             WHERE {$cond}
             ORDER BY competencia DESC, bloco
             LIMIT :limit OFFSET :offset",
            $params,
        );

        return Response::paginated($repasses, $total, $pagina, $porPagina);
    }

    public function resumo(Request $request): Response
    {
        $municipioId = $request->municipioId();
        $ano         = (int) $request->query('ano', (int) date('Y'));

        $totalAnual = $this->db->fetchOne(
            'SELECT
                COALESCE(SUM(valor_federal), 0)   AS federal,
                COALESCE(SUM(valor_estadual), 0)  AS estadual,
                COALESCE(SUM(valor_municipal), 0) AS municipal,
                COALESCE(SUM(valor_total), 0)     AS total
             FROM repasses_aps
             WHERE municipio_id = :mid AND YEAR(competencia) = :ano',
            ['mid' => $municipioId, 'ano' => $ano],
        );

        $porBloco = $this->db->fetchAll(
            'SELECT bloco, COALESCE(SUM(valor_total), 0) AS total
             FROM repasses_aps
             WHERE municipio_id = :mid AND YEAR(competencia) = :ano
             GROUP BY bloco
             ORDER BY total DESC',
            ['mid' => $municipioId, 'ano' => $ano],
        );

        return Response::json([
            'ano'       => $ano,
            'totais'    => $totalAnual,
            'por_bloco' => $porBloco,
        ]);
    }

    public function competencias(Request $request): Response
    {
        $municipioId = $request->municipioId();

        $comps = $this->db->fetchAll(
            "SELECT DISTINCT DATE_FORMAT(competencia, '%Y-%m') AS mes,
                    COALESCE(SUM(valor_total), 0) AS total
             FROM repasses_aps
             WHERE municipio_id = :mid
             GROUP BY mes
             ORDER BY mes DESC
             LIMIT 36",
            ['mid' => $municipioId],
        );

        return Response::json(['competencias' => $comps]);
    }

    public function sincronizar(Request $request): Response
    {
        return Response::json(['mensagem' => 'Sincronização APS via e-Gestor agendada.']);
    }

    public function diagnostico(Request $request): Response
    {
        $municipioId = $request->municipioId();
        $municipio   = $this->db->fetchOne(
            'SELECT codigo_ibge FROM municipios WHERE id = :id LIMIT 1',
            ['id' => $municipioId],
        );

        if (!$municipio || empty($municipio['codigo_ibge'])) {
            throw new HttpException(422, 'Município sem código IBGE cadastrado.');
        }

        $ibge        = (string) $municipio['codigo_ibge'];
        $competencia = $request->query('competencia') ?: date('Y-m', strtotime('-1 month'));

        try {
            $service     = EGestorService::fromEnv($ibge);
            $diagnostico = $service->diagnosticoEmulti($competencia);
            return Response::json($diagnostico);
        } catch (HttpException $e) {
            $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 502;
            return Response::json([
                'erro'    => $e->getMessage(),
                'codigo'  => $code,
                'passos'  => [
                    'Acesse railway.app → seu projeto → Variables',
                    'Adicione EGESTOR_TOKEN com o token da API do e-Gestor APS',
                    '  — OU — adicione ESUS_USUARIO e ESUS_SENHA (login/senha do e-Gestor)',
                    'Faça um novo deploy e tente novamente',
                ],
            ], $code);
        }
    }

    public function exportar(Request $request): Response
    {
        $municipioId = $request->municipioId();
        $ano         = (int) $request->query('ano', (int) date('Y'));

        $repasses = $this->db->fetchAll(
            "SELECT competencia, bloco, componente, valor_federal, valor_estadual, valor_municipal, valor_total
             FROM repasses_aps
             WHERE municipio_id = :mid AND YEAR(competencia) = :ano
             ORDER BY competencia, bloco",
            ['mid' => $municipioId, 'ano' => $ano],
        );

        $csv = fopen('php://temp', 'r+b');
        fputcsv($csv, ['Competência','Bloco','Componente','Federal','Estadual','Municipal','Total'], ';');

        foreach ($repasses as $r) {
            fputcsv($csv, [
                substr((string)($r['competencia'] ?? ''), 0, 7),
                $r['bloco']         ?? '',
                $r['componente']    ?? '',
                number_format((float)($r['valor_federal']   ?? 0), 2, ',', '.'),
                number_format((float)($r['valor_estadual']  ?? 0), 2, ',', '.'),
                number_format((float)($r['valor_municipal'] ?? 0), 2, ',', '.'),
                number_format((float)($r['valor_total']     ?? 0), 2, ',', '.'),
            ], ';');
        }

        rewind($csv);
        $conteudo = stream_get_contents($csv);
        fclose($csv);

        header('Content-Type: text/csv; charset=UTF-8');
        header("Content-Disposition: attachment; filename=\"aps_{$municipioId}_{$ano}.csv\"");
        echo "\xEF\xBB\xBF" . $conteudo;
        exit;
    }
}
