<?php

declare(strict_types=1);

namespace Ersus360\Repositories;

use Ersus360\Core\Database;
use Ersus360\Exceptions\HttpException;

/**
 * Repositório de presença e dados de folha de pagamento.
 * Substitui o armazenamento em /tmp (perdido a cada redeploy Railway).
 */
final class FolhaRepository
{
    public function __construct(private readonly Database $db) {}

    /**
     * Lista funcionários da folha_referencia com presença do mês.
     * @return array<int, array<string, mixed>>
     */
    public function funcionariosComPresenca(int $municipioId, string $mesReferencia): array
    {
        return $this->db->fetchAll(
            "SELECT fp.matricula, fp.nome_funcionario, fp.cargo, fp.lotacao,
                    fp.dias_uteis, fp.dias_presentes, fp.dias_ausentes,
                    fp.observacao, fp.status, fp.atualizado_em
             FROM folha_presenca fp
             WHERE fp.municipio_id = :mid
               AND fp.mes_referencia = :mes
             ORDER BY fp.lotacao, fp.nome_funcionario",
            ['mid' => $municipioId, 'mes' => $mesReferencia . '-01'],
        );
    }

    /** @return array<string, mixed>|null */
    public function buscarPresenca(int $municipioId, string $mesReferencia, string $matricula): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM folha_presenca
             WHERE municipio_id = :mid AND mes_referencia = :mes AND matricula = :mat',
            ['mid' => $municipioId, 'mes' => $mesReferencia . '-01', 'mat' => $matricula],
        );
    }

    /** Upsert de presença — INSERT ou UPDATE se já existe */
    /** @param array<string, mixed> $dados */
    public function salvarPresenca(array $dados): void
    {
        $exists = $this->buscarPresenca(
            (int) $dados['municipio_id'],
            substr((string) $dados['mes_referencia'], 0, 7),
            (string) $dados['matricula'],
        );

        if ($exists) {
            $this->db->execute(
                'UPDATE folha_presenca
                 SET dias_uteis = :du, dias_presentes = :dp, dias_ausentes = :da,
                     observacao = :obs, status = :status, registrado_por = :reg,
                     atualizado_em = NOW()
                 WHERE municipio_id = :mid AND mes_referencia = :mes AND matricula = :mat',
                [
                    'du'     => $dados['dias_uteis'],
                    'dp'     => $dados['dias_presentes'],
                    'da'     => $dados['dias_ausentes'],
                    'obs'    => $dados['observacao'] ?? null,
                    'status' => $dados['status'] ?? 'ativo',
                    'reg'    => $dados['registrado_por'] ?? null,
                    'mid'    => $dados['municipio_id'],
                    'mes'    => $dados['mes_referencia'] . '-01',
                    'mat'    => $dados['matricula'],
                ],
            );
        } else {
            $this->db->insert(
                'INSERT INTO folha_presenca
                    (municipio_id, mes_referencia, matricula, nome_funcionario, cargo, lotacao,
                     dias_uteis, dias_presentes, dias_ausentes, observacao, status, registrado_por)
                 VALUES
                    (:mid, :mes, :mat, :nome, :cargo, :lot,
                     :du, :dp, :da, :obs, :status, :reg)',
                [
                    'mid'    => $dados['municipio_id'],
                    'mes'    => $dados['mes_referencia'] . '-01',
                    'mat'    => $dados['matricula'],
                    'nome'   => $dados['nome_funcionario'],
                    'cargo'  => $dados['cargo'] ?? null,
                    'lot'    => $dados['lotacao'] ?? null,
                    'du'     => $dados['dias_uteis'],
                    'dp'     => $dados['dias_presentes'],
                    'da'     => $dados['dias_ausentes'],
                    'obs'    => $dados['observacao'] ?? null,
                    'status' => $dados['status'] ?? 'ativo',
                    'reg'    => $dados['registrado_por'] ?? null,
                ],
            );
        }
    }

    /** Salva em lote (bulk upsert) — ex: importação do JSON /tmp legado */
    /** @param array<int, array<string, mixed>> $lista */
    public function salvarLote(int $municipioId, string $mesReferencia, array $lista): int
    {
        $salvos = 0;
        foreach ($lista as $item) {
            $this->salvarPresenca(array_merge($item, [
                'municipio_id'   => $municipioId,
                'mes_referencia' => $mesReferencia,
            ]));
            $salvos++;
        }
        return $salvos;
    }

    public function atualizarStatus(int $municipioId, string $mesReferencia, string $matricula, string $status): void
    {
        $validos = ['ativo', 'afastado', 'ferias', 'licenca', 'rescisao'];
        if (!in_array($status, $validos, true)) {
            throw new HttpException(422, 'Status inválido: ' . $status);
        }

        $this->db->execute(
            'UPDATE folha_presenca SET status = :s, atualizado_em = NOW()
             WHERE municipio_id = :mid AND mes_referencia = :mes AND matricula = :mat',
            ['s' => $status, 'mid' => $municipioId, 'mes' => $mesReferencia . '-01', 'mat' => $matricula],
        );
    }

    /** Resumo mensal: total de funcionários, dias, presenças */
    /** @return array<string, mixed> */
    public function resumoMensal(int $municipioId, string $mesReferencia): array
    {
        $row = $this->db->fetchOne(
            'SELECT
                COUNT(*)                       AS total_funcionarios,
                SUM(dias_presentes)            AS total_presencas,
                SUM(dias_ausentes)             AS total_ausencias,
                SUM(dias_uteis)                AS total_dias_uteis,
                SUM(CASE WHEN status = "afastado" THEN 1 ELSE 0 END) AS afastados,
                SUM(CASE WHEN status = "ferias"   THEN 1 ELSE 0 END) AS em_ferias,
                SUM(CASE WHEN status = "licenca"  THEN 1 ELSE 0 END) AS em_licenca
             FROM folha_presenca
             WHERE municipio_id = :mid AND mes_referencia = :mes',
            ['mid' => $municipioId, 'mes' => $mesReferencia . '-01'],
        );

        $porLotacao = $this->db->fetchAll(
            'SELECT lotacao, COUNT(*) AS total, SUM(dias_presentes) AS presencas
             FROM folha_presenca
             WHERE municipio_id = :mid AND mes_referencia = :mes
             GROUP BY lotacao
             ORDER BY total DESC',
            ['mid' => $municipioId, 'mes' => $mesReferencia . '-01'],
        );

        return array_merge($row ?? [], ['por_lotacao' => $porLotacao]);
    }

    /** Meses que têm registros de presença */
    /** @return array<int, string> */
    public function mesesComDados(int $municipioId): array
    {
        return array_column(
            $this->db->fetchAll(
                "SELECT DISTINCT DATE_FORMAT(mes_referencia, '%Y-%m') AS mes
                 FROM folha_presenca
                 WHERE municipio_id = :mid
                 ORDER BY mes DESC",
                ['mid' => $municipioId],
            ),
            'mes',
        );
    }
}
