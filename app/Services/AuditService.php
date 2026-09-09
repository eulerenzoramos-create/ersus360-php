<?php

declare(strict_types=1);

namespace Ersus360\Services;

use Ersus360\Core\Database;

/**
 * Trilha de auditoria ERSUS 360.
 * Registra CREATE, UPDATE, DELETE, LOGIN, LOGOUT, EXPORT, IMPORT.
 * Nunca lança exceção — falha silenciosa para não bloquear a operação principal.
 */
final class AuditService
{
    public function __construct(private readonly Database $db) {}

    public function log(
        int     $usuarioId,
        string  $acao,
        ?string $tabela     = null,
        ?int    $registroId = null,
        ?string $detalhe    = null,
        ?string $ip         = null,
    ): void {
        try {
            $ipFinal = $ip ?? $this->resolverIp();
            // Truncar detalhe para caber no campo TEXT sem ultrapassar 64KB
            $detalheFinal = $detalhe !== null ? mb_substr($detalhe, 0, 65535) : null;

            $this->db->execute(
                'INSERT INTO audit_log
                    (usuario_id, acao, tabela, registro_id, detalhe, ip_origem, criado_em)
                 VALUES (:uid, :acao, :tabela, :rid, :detalhe, :ip, NOW())',
                [
                    'uid'     => $usuarioId,
                    'acao'    => $acao,
                    'tabela'  => $tabela,
                    'rid'     => $registroId,
                    'detalhe' => $detalheFinal,
                    'ip'      => $ipFinal,
                ],
            );
        } catch (\Throwable $e) {
            // Falha na auditoria não deve derrubar a operação principal
            error_log('[ERSUS360][AUDIT] ' . $e->getMessage());
        }
    }

    private function resolverIp(): string
    {
        // Suporte a proxy reverso (Railway / Nginx)
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $header) {
            $ip = $_SERVER[$header] ?? '';
            if ($ip !== '') {
                // X-Forwarded-For pode ter lista; pega o primeiro
                return trim(explode(',', $ip)[0]);
            }
        }
        return '0.0.0.0';
    }
}
