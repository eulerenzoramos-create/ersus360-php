<?php

declare(strict_types=1);

namespace Ersus360\Repositories;

use Ersus360\Core\Database;
use Ersus360\Exceptions\HttpException;

final class MunicipioRepository
{
    public function __construct(private readonly Database $db) {}

    /** @return array<int, array<string, mixed>> */
    public function listar(): array
    {
        return $this->db->fetchAll(
            'SELECT id, nome, codigo_ibge, estado, populacao, competencia_aps,
                    latitude, longitude, ativo, criado_em
             FROM municipios
             WHERE ativo = 1
             ORDER BY nome',
        );
    }

    /** @return array<string, mixed>|null */
    public function buscarPorId(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT m.*,
                    (SELECT COUNT(*) FROM usuarios u WHERE u.municipio_id = m.id AND u.ativo = 1) AS total_usuarios
             FROM municipios m
             WHERE m.id = :id',
            ['id' => $id],
        );
    }

    /** @return array<string, mixed>|null */
    public function buscarPorIbge(string $ibge): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM municipios WHERE codigo_ibge = :ibge LIMIT 1',
            ['ibge' => $ibge],
        );
    }

    /** @param array<string, mixed> $dados */
    public function criar(array $dados): int
    {
        $existe = $this->db->scalar(
            'SELECT COUNT(*) FROM municipios WHERE codigo_ibge = :ibge',
            ['ibge' => $dados['codigo_ibge']],
        );

        if ((int) $existe > 0) {
            throw new HttpException(409, 'Município com este código IBGE já existe.');
        }

        return $this->db->insert(
            'INSERT INTO municipios
                (nome, codigo_ibge, estado, populacao, competencia_aps, latitude, longitude, ativo, criado_em, atualizado_em)
             VALUES
                (:nome, :codigo_ibge, :estado, :populacao, :competencia_aps, :latitude, :longitude, 1, NOW(), NOW())',
            $dados,
        );
    }

    /** @param array<string, mixed> $dados */
    public function atualizar(int $id, array $dados): void
    {
        $sets   = [];
        $params = ['id' => $id];

        foreach ($dados as $campo => $valor) {
            $sets[]        = "{$campo} = :{$campo}";
            $params[$campo] = $valor;
        }

        $sets[] = 'atualizado_em = NOW()';

        $this->db->execute(
            'UPDATE municipios SET ' . implode(', ', $sets) . ' WHERE id = :id',
            $params,
        );
    }

    /** Dados do painel do município (resumo dashboard) */
    /** @return array<string, mixed> */
    public function resumoDashboard(int $municipioId): array
    {
        $municipio = $this->buscarPorId($municipioId);

        if ($municipio === null) {
            throw new HttpException(404, 'Município não encontrado.');
        }

        $totalTransferencias = $this->db->scalar(
            'SELECT COALESCE(SUM(valor_liquido), 0)
             FROM transferencias_fns
             WHERE municipio_id = :id AND YEAR(competencia) = YEAR(NOW())',
            ['id' => $municipioId],
        );

        $totalEmendas = $this->db->scalar(
            'SELECT COALESCE(SUM(valor_empenhado), 0)
             FROM emendas
             WHERE municipio_id = :id AND YEAR(criado_em) = YEAR(NOW())',
            ['id' => $municipioId],
        );

        $alertasAtivos = $this->db->scalar(
            'SELECT COUNT(*) FROM alertas WHERE municipio_id = :id AND lido = 0',
            ['id' => $municipioId],
        );

        return array_merge($municipio, [
            'total_transferencias_ano' => $totalTransferencias,
            'total_emendas_ano'        => $totalEmendas,
            'alertas_ativos'           => (int) $alertasAtivos,
        ]);
    }
}
