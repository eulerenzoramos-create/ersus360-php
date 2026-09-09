<?php

declare(strict_types=1);

namespace Ersus360\Repositories;

use Ersus360\Core\Database;

final class FnsRepository
{
    public function __construct(private readonly Database $db) {}

    /**
     * @return array{0: int, 1: array<int, array<string, mixed>>}
     */
    public function listar(
        int     $municipioId,
        ?string $competenciaInicio,
        ?string $competenciaFim,
        ?string $bloco,
        ?string $busca,
        int     $pagina,
        int     $porPagina,
    ): array {
        $where  = ['municipio_id = :mid'];
        $params = ['mid' => $municipioId];

        if ($competenciaInicio) {
            $where[]                  = 'competencia >= :ci';
            $params['ci']             = $competenciaInicio . '-01';
        }
        if ($competenciaFim) {
            $where[]                  = 'competencia <= :cf';
            $params['cf']             = $competenciaFim . '-01';
        }
        if ($bloco) {
            $where[]                  = 'bloco = :bloco';
            $params['bloco']          = $bloco;
        }
        if ($busca) {
            $where[]                  = '(programa LIKE :busca OR componente LIKE :busca OR subprograma LIKE :busca)';
            $params['busca']          = '%' . $busca . '%';
        }

        $cond  = implode(' AND ', $where);
        $total = (int) $this->db->scalar("SELECT COUNT(*) FROM transferencias_fns WHERE {$cond}", $params);

        $params['limit']  = $porPagina;
        $params['offset'] = ($pagina - 1) * $porPagina;

        $rows = $this->db->fetchAll(
            "SELECT id, competencia, bloco, componente, subprograma, programa,
                    valor_bruto, valor_desconto, valor_liquido,
                    situacao, data_credito, fonte, numero_banco, agencia, conta_corrente
             FROM transferencias_fns
             WHERE {$cond}
             ORDER BY competencia DESC, valor_liquido DESC
             LIMIT :limit OFFSET :offset",
            $params,
        );

        return [$total, $rows];
    }

    /** @return array<string, mixed>|null */
    public function buscarPorId(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM transferencias_fns WHERE id = :id',
            ['id' => $id],
        );
    }

    /** Verifica se chave_unica já existe (evita duplicata antes do INSERT) */
    public function chaveExiste(string $chave): bool
    {
        return (int) $this->db->scalar(
            'SELECT COUNT(*) FROM transferencias_fns WHERE chave_unica = :c',
            ['c' => $chave],
        ) > 0;
    }

    /** @param array<string, mixed> $dados */
    public function inserir(array $dados): int
    {
        return $this->db->insert(
            'INSERT INTO transferencias_fns
                (municipio_id, chave_unica, competencia, numero_parcela, numero_banco,
                 agencia, conta_corrente, acao, programa, bloco, subprograma, componente,
                 detalhe_url, valor_bruto, valor_desconto, valor_liquido,
                 tipo_operacao, situacao, data_credito, fonte)
             VALUES
                (:municipio_id, :chave_unica, :competencia, :numero_parcela, :numero_banco,
                 :agencia, :conta_corrente, :acao, :programa, :bloco, :subprograma, :componente,
                 :detalhe_url, :valor_bruto, :valor_desconto, :valor_liquido,
                 :tipo_operacao, :situacao, :data_credito, :fonte)',
            $dados,
        );
    }

    /** Resumo financeiro por ano */
    /** @return array<string, mixed> */
    public function resumoAnual(int $municipioId, int $ano): array
    {
        $row = $this->db->fetchOne(
            'SELECT
                COUNT(*)                         AS total_registros,
                COALESCE(SUM(valor_bruto), 0)    AS total_bruto,
                COALESCE(SUM(valor_desconto), 0) AS total_desconto,
                COALESCE(SUM(valor_liquido), 0)  AS total_liquido,
                MIN(competencia)                 AS primeira_competencia,
                MAX(competencia)                 AS ultima_competencia
             FROM transferencias_fns
             WHERE municipio_id = :mid AND YEAR(competencia) = :ano',
            ['mid' => $municipioId, 'ano' => $ano],
        );

        $porBloco = $this->db->fetchAll(
            'SELECT bloco, COALESCE(SUM(valor_liquido), 0) AS total
             FROM transferencias_fns
             WHERE municipio_id = :mid AND YEAR(competencia) = :ano
             GROUP BY bloco
             ORDER BY total DESC',
            ['mid' => $municipioId, 'ano' => $ano],
        );

        return array_merge($row ?? [], ['por_bloco' => $porBloco]);
    }

    /** Série histórica mensal para gráfico */
    /** @return array<int, array<string, mixed>> */
    public function serieMensal(int $municipioId, int $meses = 24): array
    {
        return $this->db->fetchAll(
            "SELECT DATE_FORMAT(competencia, '%Y-%m') AS mes,
                    COALESCE(SUM(valor_liquido), 0) AS total
             FROM transferencias_fns
             WHERE municipio_id = :mid
               AND competencia >= DATE_SUB(CURDATE(), INTERVAL :meses MONTH)
             GROUP BY mes
             ORDER BY mes ASC",
            ['mid' => $municipioId, 'meses' => $meses],
        );
    }

    /** Blocos distintos para filtro */
    /** @return array<int, string> */
    public function blocos(int $municipioId): array
    {
        return array_column(
            $this->db->fetchAll(
                'SELECT DISTINCT bloco FROM transferencias_fns WHERE municipio_id = :mid AND bloco IS NOT NULL ORDER BY bloco',
                ['mid' => $municipioId],
            ),
            'bloco',
        );
    }

    public function registrarColeta(
        int    $municipioId,
        string $competencia,
        int    $novos,
        int    $total,
        int    $duracaoSeg,
        string $status,
        string $mensagem = '',
    ): void {
        $this->db->execute(
            'INSERT INTO coleta_fns (municipio_id, competencia, registros_novos, registros_total, duracao_segundos, status, mensagem)
             VALUES (:mid, :comp, :novos, :total, :dur, :status, :msg)',
            [
                'mid'    => $municipioId,
                'comp'   => $competencia . '-01',
                'novos'  => $novos,
                'total'  => $total,
                'dur'    => $duracaoSeg,
                'status' => $status,
                'msg'    => $mensagem,
            ],
        );
    }
}
