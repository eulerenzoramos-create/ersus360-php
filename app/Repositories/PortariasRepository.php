<?php

declare(strict_types=1);

namespace Ersus360\Repositories;

use Ersus360\Core\Database;

final class PortariasRepository
{
    public function __construct(private readonly Database $db) {}

    /**
     * @return array{0: int, 1: array<int, array<string, mixed>>}
     */
    public function listar(
        int    $municipioId,
        ?string $busca,
        bool   $apenasNaoLidas,
        int    $pagina,
        int    $porPagina,
    ): array {
        $where  = ['municipio_id = :mid'];
        $params = ['mid' => $municipioId];

        if ($busca) {
            $where[]       = '(titulo LIKE :b OR orgao LIKE :b OR resumo LIKE :b)';
            $params['b']   = '%' . $busca . '%';
        }

        if ($apenasNaoLidas) {
            $where[] = 'lida = 0';
        }

        $cond  = implode(' AND ', $where);
        $total = (int) $this->db->scalar("SELECT COUNT(*) FROM portarias WHERE {$cond}", $params);

        $params['limit']  = $porPagina;
        $params['offset'] = ($pagina - 1) * $porPagina;

        $rows = $this->db->fetchAll(
            "SELECT id, titulo, orgao, secao, data_publicacao, lida, notificada, url_dou, resumo, relevancia, criado_em
             FROM portarias
             WHERE {$cond}
             ORDER BY data_publicacao DESC, id DESC
             LIMIT :limit OFFSET :offset",
            $params,
        );

        return [$total, $rows];
    }

    /** @return array<string, mixed>|null */
    public function buscarPorId(int $id): ?array
    {
        return $this->db->fetchOne('SELECT * FROM portarias WHERE id = :id', ['id' => $id]);
    }

    public function existePorUrl(int $municipioId, string $url): bool
    {
        if ($url === '') return false;
        return (int) $this->db->scalar(
            'SELECT COUNT(*) FROM portarias WHERE municipio_id = :mid AND url_dou = :url',
            ['mid' => $municipioId, 'url' => $url],
        ) > 0;
    }

    /** @param array<string, mixed> $dados */
    public function inserir(array $dados): int
    {
        return $this->db->insert(
            'INSERT INTO portarias
                (municipio_id, numero, data_publicacao, secao, pagina, orgao,
                 titulo, resumo, url_dou, texto_completo, palavras_chave, relevancia)
             VALUES
                (:municipio_id, :numero, :data_publicacao, :secao, :pagina, :orgao,
                 :titulo, :resumo, :url_dou, :texto_completo, :palavras_chave, :relevancia)',
            [
                'municipio_id'    => $dados['municipio_id'],
                'numero'          => $dados['numero']          ?? null,
                'data_publicacao' => $dados['data_publicacao'] ?? date('Y-m-d'),
                'secao'           => $dados['secao']           ?? 1,
                'pagina'          => $dados['pagina']          ?? null,
                'orgao'           => $dados['orgao']           ?? null,
                'titulo'          => $dados['titulo']          ?? '',
                'resumo'          => $dados['resumo']          ?? null,
                'url_dou'         => $dados['url_dou']         ?? null,
                'texto_completo'  => $dados['texto_completo']  ?? null,
                'palavras_chave'  => isset($dados['palavras_chave'])
                                     ? json_encode($dados['palavras_chave'], JSON_UNESCAPED_UNICODE)
                                     : null,
                'relevancia'      => $dados['relevancia']      ?? 0,
            ],
        );
    }

    public function marcarLida(int $id): void
    {
        $this->db->execute(
            'UPDATE portarias SET lida = 1 WHERE id = :id',
            ['id' => $id],
        );
    }

    public function marcarNotificada(int $id): void
    {
        $this->db->execute(
            'UPDATE portarias SET notificada = 1 WHERE id = :id',
            ['id' => $id],
        );
    }
}
