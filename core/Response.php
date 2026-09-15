<?php

declare(strict_types=1);

namespace Ersus360\Core;

/**
 * HTTP Response com helpers para JSON e erros padronizados.
 */
final class Response
{
    /** @param array<string, string> $headers */
    private function __construct(
        private readonly int    $status,
        private readonly string $body,
        private readonly array  $headers = [],
    ) {}

    // ── Factories ────────────────────────────────────────────

    /**
     * Resposta JSON padrão de sucesso.
     * @param mixed $data
     */
    public static function json(mixed $data, int $status = 200): self
    {
        $body = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return new self($status, $body ?: '{}', ['Content-Type' => 'application/json; charset=utf-8']);
    }

    /**
     * Resposta de erro padronizada ERSUS360.
     */
    public static function error(string $mensagem, int $status = 400, ?string $campo = null): self
    {
        $payload = ['erro' => $mensagem, 'codigo' => $status];
        if ($campo !== null) {
            $payload['campo'] = $campo;
        }
        return self::json($payload, $status);
    }

    /**
     * Lista paginada.
     * @param array<mixed>         $items
     * @param array<string, mixed> $extra Campos extras mesclados na resposta.
     */
    public static function paginated(array $items, int $total, int $pagina, int $porPagina, array $extra = []): self
    {
        return self::json(array_merge([
            'dados'      => $items,
            'total'      => $total,
            'pagina'     => $pagina,
            'por_pagina' => $porPagina,
            'paginas'    => (int) ceil($total / max(1, $porPagina)),
        ], $extra));
    }

    /** 201 Created com Location header. */
    public static function created(mixed $data, string $location = ''): self
    {
        $headers = ['Content-Type' => 'application/json; charset=utf-8'];
        if ($location !== '') {
            $headers['Location'] = $location;
        }
        $body = json_encode($data, JSON_UNESCAPED_UNICODE) ?: '{}';
        return new self(201, $body, $headers);
    }

    /** 204 No Content. */
    public static function noContent(): self
    {
        return new self(204, '');
    }

    // ── Envio ─────────────────────────────────────────────────

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }
        if ($this->body !== '') {
            echo $this->body;
        }
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getBody(): string
    {
        return $this->body;
    }
}
