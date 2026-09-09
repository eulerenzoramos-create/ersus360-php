<?php

declare(strict_types=1);

namespace Ersus360\Middleware;

use Ersus360\Core\Request;
use Ersus360\Core\Response;
use Ersus360\Services\JwtService;
use Ersus360\Exceptions\HttpException;

/**
 * Middleware de autenticação JWT.
 * Extrai o token do header Authorization: Bearer <token>,
 * valida e injeta os dados do usuário no Request.
 */
final class AuthMiddleware
{
    public function __construct(private readonly JwtService $jwt) {}

    public function handle(Request $request, callable $next): Response
    {
        $token = $request->bearerToken();

        if ($token === null) {
            throw new HttpException(401, 'Autenticação necessária. Forneça o token Bearer.');
        }

        $payload = $this->jwt->validar($token);

        $request->setUser([
            'id'             => (int) ($payload['id'] ?? 0),
            'nome'           => (string) ($payload['nome'] ?? ''),
            'email'          => (string) ($payload['email'] ?? ''),
            'perfil'         => (string) ($payload['perfil'] ?? ''),
            'municipio_id'   => isset($payload['municipio_id']) ? (int) $payload['municipio_id'] : null,
            'codigo_ibge'    => $payload['codigo_ibge'] ?? null,
            'municipio_nome' => $payload['municipio_nome'] ?? null,
        ]);

        return $next($request);
    }
}
