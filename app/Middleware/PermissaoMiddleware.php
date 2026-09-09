<?php

declare(strict_types=1);

namespace Ersus360\Middleware;

use Ersus360\Core\Request;
use Ersus360\Core\Response;
use Ersus360\Services\PermissaoService;

/**
 * Middleware de autorização por módulo.
 * Uso: new PermissaoMiddleware('fns') — verifica se o perfil do usuário
 * autenticado tem acesso ao módulo 'fns'.
 *
 * Deve ser usado APÓS AuthMiddleware na cadeia.
 */
final class PermissaoMiddleware
{
    public function __construct(
        private readonly PermissaoService $permissao,
        private readonly string           $modulo = '',
    ) {}

    /**
     * Factory para uso nas rotas.
     * Exemplo: PermissaoMiddleware::para('fns')
     * Retorna o nome de classe com o módulo embutido via binding no container.
     */
    public static function para(string $modulo): string
    {
        return self::class . ':' . $modulo;
    }

    public function handle(Request $request, callable $next): Response
    {
        if ($this->modulo !== '') {
            $this->permissao->exigir($request->perfil(), $this->modulo);
        }

        return $next($request);
    }
}
