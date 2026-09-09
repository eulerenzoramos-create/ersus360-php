<?php

declare(strict_types=1);

namespace Ersus360\Services;

use Ersus360\Repositories\FolhaRepository;
use Ersus360\Exceptions\HttpException;

/**
 * Lógica de negócio do módulo Folha de Pagamento.
 *
 * folha_referencia.json (sistema legado Python) contém 284 funcionários.
 * O PHP lê esse arquivo como referência estática e persiste presença no banco.
 */
final class FolhaService
{
    /** Cache em memória do arquivo de referência (carregado uma vez por request) */
    private ?array $referenciaCache = null;

    public function __construct(private readonly FolhaRepository $repo) {}

    /**
     * Retorna todos os funcionários da referência com dados de presença do mês.
     * Funcionários sem registro de presença recebem valores zerados.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listarFuncionariosComPresenca(int $municipioId, string $mesReferencia): array
    {
        $referencia = $this->carregarReferencia();
        $presencas  = $this->repo->funcionariosComPresenca($municipioId, $mesReferencia);

        // Indexa presença por matrícula para lookup O(1)
        $presencaIdx = [];
        foreach ($presencas as $p) {
            $presencaIdx[$p['matricula']] = $p;
        }

        $resultado = [];
        foreach ($referencia as $func) {
            $matricula = (string) ($func['matricula'] ?? '');
            $presenca  = $presencaIdx[$matricula] ?? null;

            $resultado[] = [
                'matricula'       => $matricula,
                'nome'            => $func['nome']            ?? '',
                'cargo'           => $func['cargo']           ?? '',
                'lotacao'         => $func['lotacao']         ?? '',
                'vinculo'         => $func['vinculo']         ?? '',
                'carga_horaria'   => $func['carga_horaria']   ?? null,
                'status'          => $presenca['status']       ?? ($func['status'] ?? 'ativo'),
                'dias_uteis'      => (int) ($presenca['dias_uteis']    ?? 0),
                'dias_presentes'  => (int) ($presenca['dias_presentes'] ?? 0),
                'dias_ausentes'   => (int) ($presenca['dias_ausentes']  ?? 0),
                'observacao'      => $presenca['observacao']   ?? null,
                'tem_presenca'    => $presenca !== null,
                'atualizado_em'   => $presenca['atualizado_em'] ?? null,
            ];
        }

        return $resultado;
    }

    /**
     * Adiciona um funcionário extra (fora da folha_referencia.json).
     * Cria um registro de presença inicial zerado.
     */
    public function adicionarFuncionario(int $municipioId, string $mesReferencia, array $dados): void
    {
        $matricula = trim((string) ($dados['matricula'] ?? ''));

        if ($matricula === '') {
            throw new HttpException(422, 'Matrícula é obrigatória.');
        }

        $existe = $this->repo->buscarPresenca($municipioId, $mesReferencia, $matricula);
        if ($existe) {
            throw new HttpException(409, 'Funcionário já existe neste mês de referência.');
        }

        $this->repo->salvarPresenca([
            'municipio_id'    => $municipioId,
            'mes_referencia'  => $mesReferencia,
            'matricula'       => $matricula,
            'nome_funcionario' => trim((string) ($dados['nome'] ?? 'Funcionário')),
            'cargo'           => $dados['cargo']   ?? null,
            'lotacao'         => $dados['lotacao'] ?? null,
            'dias_uteis'      => 0,
            'dias_presentes'  => 0,
            'dias_ausentes'   => 0,
            'status'          => 'ativo',
        ]);
    }

    /**
     * Importa presença de um JSON legado (estrutura do /tmp Python).
     * Usado na migração inicial: POST /api/folha/importar-legacy
     *
     * @param array<int, array<string, mixed>> $dadosJson
     */
    public function importarLegacy(int $municipioId, string $mesReferencia, array $dadosJson): int
    {
        return $this->repo->salvarLote($municipioId, $mesReferencia, $dadosJson);
    }

    /** Carrega folha_referencia.json — lança exceção se não encontrar */
    private function carregarReferencia(): array
    {
        if ($this->referenciaCache !== null) {
            return $this->referenciaCache;
        }

        // Caminho configurável via env; padrão relativo ao projeto
        $path = $_ENV['FOLHA_REFERENCIA_PATH']
            ?? dirname(__DIR__, 2) . '/storage/data/folha_referencia.json';

        if (!file_exists($path)) {
            throw new HttpException(500, 'folha_referencia.json não encontrado em: ' . $path);
        }

        $json = file_get_contents($path);
        $data = json_decode((string) $json, true, 512, JSON_THROW_ON_ERROR);

        // Aceita tanto array direto quanto {"funcionarios": [...]}
        $this->referenciaCache = isset($data[0]) ? $data : ($data['funcionarios'] ?? []);

        return $this->referenciaCache;
    }
}
