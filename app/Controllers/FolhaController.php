<?php

declare(strict_types=1);

namespace Ersus360\Controllers;

use Ersus360\Core\Request;
use Ersus360\Core\Response;
use Ersus360\Repositories\FolhaRepository;
use Ersus360\Services\FolhaService;
use Ersus360\Services\PermissaoService;
use Ersus360\Services\AuditService;
use Ersus360\Exceptions\HttpException;

final class FolhaController
{
    public function __construct(
        private readonly FolhaRepository  $repo,
        private readonly FolhaService     $folhaService,
        private readonly PermissaoService $permissao,
        private readonly AuditService     $audit,
    ) {}

    /** GET /api/folha/funcionarios?mes=2025-10 */
    public function funcionarios(Request $request): Response
    {
        $municipioId  = $request->municipioId();
        $mesReferencia = $this->resolverMes($request);

        $funcionarios = $this->folhaService->listarFuncionariosComPresenca($municipioId, $mesReferencia);
        $resumo       = $this->repo->resumoMensal($municipioId, $mesReferencia);
        $meses        = $this->repo->mesesComDados($municipioId);

        return Response::json([
            'mes_referencia' => $mesReferencia,
            'total'          => count($funcionarios),
            'resumo'         => $resumo,
            'meses_com_dados' => $meses,
            'funcionarios'   => $funcionarios,
        ]);
    }

    /** GET /api/folha/presenca?mes=2025-10 */
    public function presenca(Request $request): Response
    {
        $municipioId   = $request->municipioId();
        $mesReferencia = $this->resolverMes($request);

        $presencas = $this->repo->funcionariosComPresenca($municipioId, $mesReferencia);
        $resumo    = $this->repo->resumoMensal($municipioId, $mesReferencia);

        return Response::json([
            'mes_referencia' => $mesReferencia,
            'resumo'         => $resumo,
            'presencas'      => $presencas,
        ]);
    }

    /** POST /api/folha/presenca — salva presença de um funcionário */
    public function salvarPresenca(Request $request): Response
    {
        $municipioId = $request->municipioId();
        $dados       = $request->all();
        $erros       = [];

        if (empty($dados['matricula']))        $erros[] = 'matricula é obrigatória';
        if (empty($dados['nome_funcionario'])) $erros[] = 'nome_funcionario é obrigatório';
        if (!isset($dados['mes_referencia']))  $erros[] = 'mes_referencia é obrigatório';
        if (!isset($dados['dias_uteis']))      $erros[] = 'dias_uteis é obrigatório';
        if (!isset($dados['dias_presentes']))  $erros[] = 'dias_presentes é obrigatório';

        if (!empty($erros)) {
            throw new HttpException(422, implode('; ', $erros));
        }

        $mes = substr((string) $dados['mes_referencia'], 0, 7);

        $this->repo->salvarPresenca([
            'municipio_id'    => $municipioId,
            'mes_referencia'  => $mes,
            'matricula'       => $dados['matricula'],
            'nome_funcionario' => $dados['nome_funcionario'],
            'cargo'           => $dados['cargo']   ?? null,
            'lotacao'         => $dados['lotacao'] ?? null,
            'dias_uteis'      => (int) $dados['dias_uteis'],
            'dias_presentes'  => (int) $dados['dias_presentes'],
            'dias_ausentes'   => (int) ($dados['dias_ausentes'] ?? max(0, (int)$dados['dias_uteis'] - (int)$dados['dias_presentes'])),
            'observacao'      => $dados['observacao'] ?? null,
            'status'          => $dados['status'] ?? 'ativo',
            'registrado_por'  => $request->userId(),
        ]);

        $this->audit->log($request->userId(), 'UPSERT', 'folha_presenca', $municipioId,
            "Presença {$dados['matricula']} em {$mes}");

        return Response::json(['mensagem' => 'Presença salva com sucesso.']);
    }

    /** POST /api/folha/funcionarios — adiciona funcionário extra */
    public function adicionarFuncionario(Request $request): Response
    {
        $this->permissao->exigir($request->perfil(), 'folha');

        $municipioId   = $request->municipioId();
        $dados         = $request->all();
        $mesReferencia = $this->resolverMes($request);

        $this->folhaService->adicionarFuncionario($municipioId, $mesReferencia, $dados);

        $this->audit->log($request->userId(), 'CREATE', 'folha_presenca', $municipioId,
            "Adicionou funcionário: {$dados['matricula']} em {$mesReferencia}");

        return Response::json(['mensagem' => 'Funcionário adicionado.'], 201);
    }

    /** DELETE /api/folha/funcionarios/{id} — inativa (status=rescisao) */
    public function removerFuncionario(Request $request): Response
    {
        $this->permissao->exigir($request->perfil(), 'folha');

        $municipioId   = $request->municipioId();
        $matricula     = $request->param('id'); // matricula no campo id da rota
        $mesReferencia = $this->resolverMes($request);

        $this->repo->atualizarStatus($municipioId, $mesReferencia, $matricula, 'rescisao');

        $this->audit->log($request->userId(), 'DELETE', 'folha_presenca', $municipioId,
            "Removeu funcionário: {$matricula}");

        return Response::noContent();
    }

    /** PUT /api/folha/funcionarios/{id}/status */
    public function atualizarStatus(Request $request): Response
    {
        $municipioId   = $request->municipioId();
        $matricula     = $request->param('id');
        $mesReferencia = $this->resolverMes($request);
        $status        = (string) $request->input('status', '');

        $this->repo->atualizarStatus($municipioId, $mesReferencia, $matricula, $status);

        $this->audit->log($request->userId(), 'UPDATE', 'folha_presenca', $municipioId,
            "Status {$matricula} → {$status} em {$mesReferencia}");

        return Response::json(['mensagem' => 'Status atualizado.']);
    }

    /**
     * POST /api/folha/importar-legacy
     * Recebe JSON do /tmp legado e importa para o banco.
     * Endpoint de uso único na migração.
     */
    public function importarLegacy(Request $request): Response
    {
        $this->permissao->exigir($request->perfil(), 'admin');

        $municipioId   = $request->municipioId();
        $mesReferencia = $this->resolverMes($request);
        $dados         = $request->input('presencas', []);

        if (!is_array($dados) || empty($dados)) {
            throw new HttpException(422, 'presencas deve ser um array não vazio.');
        }

        $total = $this->folhaService->importarLegacy($municipioId, $mesReferencia, $dados);

        $this->audit->log($request->userId(), 'IMPORT', 'folha_presenca', $municipioId,
            "Importou {$total} registros de presença legados para {$mesReferencia}");

        return Response::json(['mensagem' => "Importados {$total} registros.", 'total' => $total]);
    }

    /** GET /api/folha/exportar?mes=2025-10 — CSV */
    public function exportar(Request $request): Response
    {
        $municipioId   = $request->municipioId();
        $mesReferencia = $this->resolverMes($request);

        $funcionarios = $this->folhaService->listarFuncionariosComPresenca($municipioId, $mesReferencia);

        $csv = fopen('php://temp', 'r+b');
        fputcsv($csv, ['Matrícula','Nome','Cargo','Lotação','Status','Dias Úteis','Dias Presentes','Dias Ausentes','Observação'], ';');

        foreach ($funcionarios as $f) {
            fputcsv($csv, [
                $f['matricula'],
                $f['nome'],
                $f['cargo']          ?? '',
                $f['lotacao']        ?? '',
                $f['status'],
                $f['dias_uteis'],
                $f['dias_presentes'],
                $f['dias_ausentes'],
                $f['observacao']     ?? '',
            ], ';');
        }

        rewind($csv);
        $conteudo = stream_get_contents($csv);
        fclose($csv);

        header('Content-Type: text/csv; charset=UTF-8');
        header("Content-Disposition: attachment; filename=\"presenca_{$municipioId}_{$mesReferencia}.csv\"");
        echo "\xEF\xBB\xBF" . $conteudo;
        exit;
    }

    private function resolverMes(Request $request): string
    {
        $mes = (string) $request->query('mes', date('Y-m'));
        if (!preg_match('/^\d{4}-\d{2}$/', $mes)) {
            throw new HttpException(422, 'mes deve estar no formato YYYY-MM.');
        }
        return $mes;
    }
}
