<?php

declare(strict_types=1);

namespace Ersus360\Services;

use Ersus360\Core\Database;
use Ersus360\Exceptions\HttpException;

/**
 * Autenticação ERSUS 360.
 *
 * Dois planos:
 * 1. BD — tabela `usuarios` (fonte primária, todos os perfis municipais)
 * 2. Bootstrap — conta superadmin da assessoria definida via env var ADMIN_EMAIL/ADMIN_SENHA
 *    (existe antes de qualquer BD ser populado)
 *
 * Senhas: bcrypt PASSWORD_BCRYPT com cost 12.
 * Nunca armazenar senha em texto claro.
 */
final class AuthService
{
    public function __construct(
        private readonly Database   $db,
        private readonly JwtService $jwt,
        private readonly AuditService $audit,
    ) {}

    /**
     * Autentica o usuário e retorna o token JWT.
     *
     * @return array{token: string, usuario: array<string, mixed>}
     */
    public function login(string $email, string $senha, string $ip): array
    {
        $email = mb_strtolower(trim($email));

        // ── Plano 1: usuários do BD ───────────────────────────
        $usuario = $this->db->fetchOne(
            'SELECT u.id, u.nome, u.email, u.senha_hash, u.perfil, u.ativo,
                    u.municipio_id, m.codigo_ibge, m.nome AS municipio_nome
             FROM usuarios u
             LEFT JOIN municipios m ON m.id = u.municipio_id
             WHERE u.email = :email
             LIMIT 1',
            ['email' => $email],
        );

        if ($usuario !== null) {
            $this->validarAtivo($usuario);
            $this->verificarSenha($senha, (string) $usuario['senha_hash']);
            $this->registrarAcesso($usuario['id'], $ip);
            return $this->emitirToken($usuario);
        }

        // ── Plano 2: bootstrap admin via env var (fallback para e-mails não cadastrados) ──
        $adminEmail = mb_strtolower($_ENV['ADMIN_EMAIL'] ?? '');
        $adminSenha = $_ENV['ADMIN_SENHA'] ?? '';

        if ($email === $adminEmail && $adminSenha !== '' && hash_equals($adminSenha, $senha)) {
            $bootstrap = [
                'id'             => 0,
                'nome'           => $_ENV['ADMIN_NOME'] ?? 'Administrador',
                'email'          => $adminEmail,
                'perfil'         => 'superadmin',
                'municipio_id'   => null,
                'codigo_ibge'    => null,
                'municipio_nome' => 'Assessoria',
            ];
            return $this->emitirToken($bootstrap);
        }

        // Rate-limit simbólico: 100ms de delay em falha para dificultar força bruta
        usleep(100_000);
        throw new HttpException(401, 'E-mail ou senha incorretos.');
    }

    /**
     * Troca a senha do usuário autenticado.
     * Exige senha atual para confirmar identidade.
     */
    public function trocarSenha(int $usuarioId, string $senhaAtual, string $novaSenha): void
    {
        $usuario = $this->db->fetchOne(
            'SELECT id, senha_hash FROM usuarios WHERE id = :id',
            ['id' => $usuarioId],
        );

        if ($usuario === null) {
            throw new HttpException(404, 'Usuário não encontrado.');
        }

        $this->verificarSenha($senhaAtual, (string) $usuario['senha_hash']);
        $this->validarForcaSenha($novaSenha);

        $hash = password_hash($novaSenha, PASSWORD_BCRYPT, ['cost' => 12]);

        $this->db->execute(
            'UPDATE usuarios SET senha_hash = :hash, atualizado_em = NOW() WHERE id = :id',
            ['hash' => $hash, 'id' => $usuarioId],
        );

        $this->audit->log(
            usuarioId: $usuarioId,
            acao:      'TROCA_SENHA',
            tabela:    'usuarios',
            registroId: $usuarioId,
        );
    }

    /**
     * Cria hash bcrypt de uma senha. Use para criar/redefinir senhas.
     */
    public function hashSenha(string $senha): string
    {
        $this->validarForcaSenha($senha);
        return password_hash($senha, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    // ── Interno ──────────────────────────────────────────────

    /** @param array<string, mixed> $usuario */
    private function validarAtivo(array $usuario): void
    {
        if (!(bool) $usuario['ativo']) {
            throw new HttpException(403, 'Usuário inativo. Contate o administrador.');
        }
    }

    private function verificarSenha(string $senha, string $hash): void
    {
        if (!password_verify($senha, $hash)) {
            usleep(100_000); // 100ms delay
            throw new HttpException(401, 'E-mail ou senha incorretos.');
        }
    }

    /**
     * @param array<string, mixed> $usuario
     * @return array{token: string, usuario: array<string, mixed>}
     */
    private function emitirToken(array $usuario): array
    {
        $payload = [
            'id'             => $usuario['id'],
            'nome'           => $usuario['nome'],
            'email'          => $usuario['email'],
            'perfil'         => $usuario['perfil'],
            'municipio_id'   => $usuario['municipio_id'] ?? null,
            'codigo_ibge'    => $usuario['codigo_ibge'] ?? null,
            'municipio_nome' => $usuario['municipio_nome'] ?? null,
        ];

        $token = $this->jwt->gerar($payload);

        return [
            'token'   => $token,
            'usuario' => $payload,
        ];
    }

    private function registrarAcesso(int $usuarioId, string $ip): void
    {
        $this->db->execute(
            'UPDATE usuarios SET ultimo_acesso = NOW() WHERE id = :id',
            ['id' => $usuarioId],
        );

        $this->audit->log(
            usuarioId:  $usuarioId,
            acao:       'LOGIN',
            tabela:     'usuarios',
            registroId: $usuarioId,
            detalhe:    "IP: {$ip}",
            ip:         $ip,
        );
    }

    private function validarForcaSenha(string $senha): void
    {
        if (mb_strlen($senha) < 8) {
            throw new HttpException(422, 'A senha deve ter pelo menos 8 caracteres.', );
        }
    }
}
