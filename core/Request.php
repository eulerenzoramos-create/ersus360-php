<?php

declare(strict_types=1);

namespace Ersus360\Core;

/**
 * Encapsula o HTTP request com sanitização de entrada.
 * Nunca retorna dados brutos de superglobais sem passar por aqui.
 */
final class Request
{
    /** @var array<string, mixed> */
    private array $body = [];

    /** @var array<string, string> */
    private array $routeParams = [];

    /** @var array<string, mixed> */
    private array $authUser = [];

    private function __construct(
        private readonly string $method,
        private readonly string $uri,
        /** @var array<string, string> */
        private readonly array  $headers,
        /** @var array<string, mixed> */
        private readonly array  $query,
        private readonly string $rawBody,
    ) {
        $this->parseBody();
    }

    public static function fromGlobals(): self
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name           = str_replace('_', '-', strtolower(substr($key, 5)));
                $headers[$name] = $value;
            }
        }

        return new self(
            method:  strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            uri:     parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/',
            headers: $headers,
            query:   $_GET,
            rawBody: file_get_contents('php://input') ?: '',
        );
    }

    // ── Acesso ───────────────────────────────────────────────

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->uri;
    }

    public function header(string $name, string $default = ''): string
    {
        return $this->headers[strtolower($name)] ?? $default;
    }

    public function bearerToken(): ?string
    {
        $auth = $this->header('authorization');
        if (str_starts_with($auth, 'Bearer ')) {
            return substr($auth, 7);
        }
        return null;
    }

    /**
     * Parâmetro de query string (?key=value), sanitizado.
     */
    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    /**
     * Campo do body JSON (POST/PUT/PATCH), sanitizado.
     */
    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $default;
    }

    /**
     * Retorna todo o body parseado.
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->body;
    }

    /**
     * Parâmetro de rota ({id} na URL), ex: /api/transferencias/42 → id=42
     */
    public function param(string $key, string $default = ''): string
    {
        return $this->routeParams[$key] ?? $default;
    }

    public function paramInt(string $key): int
    {
        return (int) $this->param($key);
    }

    /** @param array<string, string> $params */
    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }

    // ── Usuário autenticado ───────────────────────────────────

    /** @param array<string, mixed> $user */
    public function setUser(array $user): void
    {
        $this->authUser = $user;
    }

    /** @return array<string, mixed> */
    public function user(): array
    {
        return $this->authUser;
    }

    public function userId(): int
    {
        return (int) ($this->authUser['id'] ?? 0);
    }

    public function perfil(): string
    {
        return (string) ($this->authUser['perfil'] ?? '');
    }

    public function municipioId(): int
    {
        return (int) ($this->authUser['municipio_id'] ?? 0);
    }

    // ── Validação rápida ──────────────────────────────────────

    public function isJson(): bool
    {
        return str_contains($this->header('content-type'), 'application/json');
    }

    // ── Interno ──────────────────────────────────────────────

    private function parseBody(): void
    {
        if ($this->rawBody === '') {
            return;
        }

        if ($this->isJson()) {
            $decoded = json_decode($this->rawBody, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $this->body = $decoded;
            }
        } else {
            parse_str($this->rawBody, $parsed);
            $this->body = $parsed;
        }
    }
}
