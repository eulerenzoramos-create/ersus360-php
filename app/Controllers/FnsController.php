<?php

declare(strict_types=1);

namespace Ersus360\Controllers;

use Ersus360\Core\Request;
use Ersus360\Core\Response;
use Ersus360\Repositories\FnsRepository;
use Ersus360\Repositories\MunicipioRepository;
use Ersus360\Services\FnsService;
use Ersus360\Services\PermissaoService;
use Ersus360\Services\AuditService;
use Ersus360\Exceptions\HttpException;

final class FnsController
{
    public function __construct(
        private readonly FnsRepository      $fnsRepo,
        private readonly MunicipioRepository $municipioRepo,
        private readonly FnsService         $fnsService,
        private readonly PermissaoService   $permissao,
        private readonly AuditService       $audit,
    ) {}

    public function index(Request $request): Response
    {
        $municipioId = $this->resolverMunicipio($request);

        [$total, $registros] = $this->fnsRepo->listar(
            municipioId:        $municipioId,
            competenciaInicio:  $request->query('competencia_inicio'),
            competenciaFim:     $request->query('competencia_fim'),
            bloco:              $request->query('bloco'),
            busca:              trim((string) $request->query('busca', '')),
            pagina:             max(1, (int) $request->query('pagina', 1)),
            porPagina:          min(200, max(10, (int) $request->query('por_pagina', 50))),
        );

        $blocos = $this->fnsRepo->blocos($municipioId);

        return Response::paginated($registros, $total,
            (int) $request->query('pagina', 1),
            (int) $request->query('por_pagina', 50),
            ['blocos_disponiveis' => $blocos],
        );
    }

    public function show(Request $request): Response
    {
        $municipioId  = $this->resolverMunicipio($request);
        $transferencia = $this->fnsRepo->buscarPorId($request->paramInt('id'));

        if ($transferencia === null) {
            throw new HttpException(404, 'Transferência não encontrada.');
        }

        if ((int) $transferencia['municipio_id'] !== $municipioId) {
            throw new HttpException(403, 'Acesso negado a este registro.');
        }

        return Response::json($transferencia);
    }

    public function resumo(Request $request): Response
    {
        $municipioId = $this->resolverMunicipio($request);
        $ano         = (int) $request->query('ano', (int) date('Y'));

        $resumo = $this->fnsRepo->resumoAnual($municipioId, $ano);
        return Response::json($resumo);
    }

    public function grafico(Request $request): Response
    {
        $municipioId = $this->resolverMunicipio($request);
        $meses       = min(60, max(3, (int) $request->query('meses', 24)));

        $serie = $this->fnsRepo->serieMensal($municipioId, $meses);
        return Response::json(['serie' => $serie, 'meses' => $meses]);
    }

    public function sincronizar(Request $request): Response
    {
        $this->permissao->exigir($request->perfil(), 'fns');

        $municipioId = $this->resolverMunicipio($request);
        $municipio   = $this->municipioRepo->buscarPorId($municipioId);

        if (!$municipio) {
            throw new HttpException(404, 'Município não encontrado.');
        }

        $competencia = (string) $request->input('competencia', date('Y-m'));

        if (!preg_match('/^\d{4}-\d{2}$/', $competencia)) {
            throw new HttpException(422, 'competencia deve estar no formato YYYY-MM.');
        }

        $resultado = $this->fnsService->sincronizarScraping(
            $municipioId,
            (string) $municipio['codigo_ibge'],
            $competencia,
        );

        $this->audit->log(
            $request->userId(), 'SYNC', 'transferencias_fns', $municipioId,
            "FNS scraping {$competencia}: {$resultado['novos']} novos / {$resultado['total']} total",
        );

        return Response::json([
            'mensagem'    => 'Sincronização concluída.',
            'competencia' => $competencia,
            'novos'       => $resultado['novos'],
            'total'       => $resultado['total'],
        ]);
    }

    public function sincronizarApi(Request $request): Response
    {
        $this->permissao->exigir($request->perfil(), 'fns');

        $municipioId = $this->resolverMunicipio($request);
        $municipio   = $this->municipioRepo->buscarPorId($municipioId);

        if (!$municipio) {
            throw new HttpException(404, 'Município não encontrado.');
        }

        $competencia = (string) $request->input('competencia', date('Y-m'));

        $resultado = $this->fnsService->sincronizarApi(
            $municipioId,
            (string) $municipio['codigo_ibge'],
            $competencia,
        );

        $this->audit->log(
            $request->userId(), 'SYNC_API', 'transferencias_fns', $municipioId,
            "FNS API {$competencia}: {$resultado['novos']} novos",
        );

        return Response::json([
            'mensagem'    => 'Sincronização via API concluída.',
            'competencia' => $competencia,
            'novos'       => $resultado['novos'],
            'total'       => $resultado['total'],
        ]);
    }

    public function exportar(Request $request): Response
    {
        $municipioId = $this->resolverMunicipio($request);
        $ano         = (int) $request->query('ano', (int) date('Y'));

        [, $registros] = $this->fnsRepo->listar(
            municipioId:       $municipioId,
            competenciaInicio: "{$ano}-01",
            competenciaFim:    "{$ano}-12",
            bloco:             null,
            busca:             '',
            pagina:            1,
            porPagina:         9999,
        );

        // Geração de CSV em memória
        $csv = fopen('php://temp', 'r+b');
        fputcsv($csv, ['Competência','Bloco','Componente','Programa','Valor Bruto','Desconto','Valor Líquido','Situação','Data Crédito'], ';');

        foreach ($registros as $r) {
            fputcsv($csv, [
                substr((string)($r['competencia'] ?? ''), 0, 7),
                $r['bloco']        ?? '',
                $r['componente']   ?? '',
                $r['programa']     ?? '',
                number_format((float)($r['valor_bruto']    ?? 0), 2, ',', '.'),
                number_format((float)($r['valor_desconto'] ?? 0), 2, ',', '.'),
                number_format((float)($r['valor_liquido']  ?? 0), 2, ',', '.'),
                $r['situacao']     ?? '',
                $r['data_credito'] ?? '',
            ], ';');
        }

        rewind($csv);
        $conteudo = stream_get_contents($csv);
        fclose($csv);

        header('Content-Type: text/csv; charset=UTF-8');
        header("Content-Disposition: attachment; filename=\"fns_{$municipioId}_{$ano}.csv\"");
        echo "\xEF\xBB\xBF" . $conteudo; // BOM UTF-8 para Excel
        exit;
    }

    /** Resolve municipio_id respeitando isolamento de perfis */
    private function resolverMunicipio(Request $request): int
    {
        if ($this->permissao->isAssessoria($request->perfil())) {
            $id = (int) $request->query('municipio_id', 0);
            if ($id === 0) {
                throw new HttpException(422, 'municipio_id é obrigatório para assessoria.');
            }
            return $id;
        }

        return $request->municipioId();
    }
}
