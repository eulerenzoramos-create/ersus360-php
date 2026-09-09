<?php

declare(strict_types=1);

namespace Ersus360\Repositories;

use Ersus360\Core\Database;
use Ersus360\Exceptions\HttpException;

/**
 * Repository de usuários. Toda consulta usa prepared statements.
 * Nunca retorna senha_hash para o cliente.
 */
final class UsuarioRepository
{
    public function __construct(private readonly Database $db) {}

    /**
     * @return array{0: int, 1: array<int, array<string, mixed>>}
     */
    public function listar(
        ?int   $municipioId,
        string $busca,
        string $perfil,
        int    $pagina,
        int    $porPagina,
    ): array {
        $where  = ['1=1'];
        $params = [];

        if ($municipioId !== null) {
            $where[]               = 'u.municipio_id = :municipio_id';
            $params['municipio_id'] = $municipioId;
        }

        if ($busca !== '') {
            $where[]        = '(u.nome LIKE :busca OR u.email LIKE :busca)';
            $params['busca'] = '%' . $busca . '%';
        }

        if ($perfil !== '') {
            $where[]         = 'u.perfil = :perfil';
            $params['perfil'] = $perfil;
        }

        $cond  = implode(' AND ', $where);
        $total = (int) $this->db->scalar(
            "SELECT COUNT(*) FROM usuarios u WHERE {$cond}",
            $params,
        );

        $offset = ($pagina - 1) * $porPagina;
        $params['limit']  = $porPagina;
        $params['offset'] = $offset;

        $rows = $this->db->fetchAll(
            "SELECT u.id, u.nome, u.email, u.perfil, u.ativo,
                    u.municipio_id, m.nome AS municipio_nome, u.ultimo_acesso, u.criado_em
             FROM usuarios u
             LEFT JOIN municipios m ON m.id = u.municipio_id
             WHERE {$cond}
             ORDER BY u.nome
             LIMIT :limit OFFSET :offset",
            $params,
        );

        return [$total, $rows];
    }

    /** @return array<string, mixed>|null */
    public function buscarPorId(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT u.id, u.nome, u.email, u.perfil, u.ativo,
                    u.municipio_id, m.nome AS municipio_nome, u.ultimo_acesso, u.criado_em, u.atualizado_em
             FROM usuarios u
             LEFT JOIN municipios m ON m.id = u.municipio_id
             WHERE u.id = :id',
            ['id' => $id],
        );
    }

    /** @return array<string, mixed>|null */
    public function buscarPorEmail(string $email): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM usuarios WHERE email = :email LIMIT 1',
            ['email' => mb_strtolower($email)],
        );
    }

    /** @param array<string, mixed> $dados */
    public function criar(array $dados): int
    {
        $emailExiste = $this->db->scalar(
            'SELECT COUNT(*) FROM usuarios WHERE email = :email',
            ['email' => $dados['email']],
        );

        if ((int) $emailExiste > 0) {
            throw new HttpException(409, 'Já existe um usuário com este e-mail.');
        }

        return $this->db->insert(
            'INSERT INTO usuarios
                (municipio_id, nome, email, senha_hash, perfil, ativo, criado_em, atualizado_em)
             VALUES (:municipio_id, :nome, :email, :senha_hash, :perfil, :ativo, NOW(), NOW())',
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
            'UPDATE usuarios SET ' . implode(', ', $sets) . ' WHERE id = :id',
            $params,
        );
    }

    public function inativar(int $id): void
    {
        $this->db->execute(
            'UPDATE usuarios SET ativo = 0, atualizado_em = NOW() WHERE id = :id',
            ['id' => $id],
        );
    }
}
