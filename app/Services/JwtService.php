<?php

declare(strict_types=1);

namespace Ersus360\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use Ersus360\Exceptions\HttpException;

/**
 * Geração e validação de tokens JWT.
 * HS256. Expiração configurável (padrão 480 min = 8 horas).
 */
final class JwtService
{
    public function __construct(
        private readonly string $secret,
        private readonly string $algorithm,
        private readonly int    $expireMin,
    ) {}

    /**
     * Gera token JWT para um usuário autenticado.
     * @param array<string, mixed> $payload
     */
    public function gerar(array $payload): string
    {
        $now  = time();
        $data = array_merge($payload, [
            'iat' => $now,
            'exp' => $now + ($this->expireMin * 60),
            'iss' => 'ersus360',
        ]);

        return JWT::encode($data, $this->secret, $this->algorithm);
    }

    /**
     * Valida e decodifica um token JWT.
     * Lança HttpException 401 em caso de token inválido ou expirado.
     *
     * @return array<string, mixed>
     */
    public function validar(string $token): array
    {
        if ($token === '') {
            throw new HttpException(401, 'Token não fornecido.');
        }

        try {
            $decoded = JWT::decode($token, new Key($this->secret, $this->algorithm));
            return (array) $decoded;
        } catch (ExpiredException) {
            throw new HttpException(401, 'Token expirado. Faça login novamente.');
        } catch (SignatureInvalidException) {
            throw new HttpException(401, 'Token inválido.');
        } catch (\Exception $e) {
            throw new HttpException(401, 'Token inválido: ' . $e->getMessage());
        }
    }
}
