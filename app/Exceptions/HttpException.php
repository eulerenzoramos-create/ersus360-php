<?php

declare(strict_types=1);

namespace Ersus360\Exceptions;

use RuntimeException;

/**
 * Exceção HTTP semântica.
 * Lançada pelos serviços e controllers; capturada pelo App e convertida em JSON.
 */
final class HttpException extends RuntimeException
{
    /** @param array<string, mixed> $extra Campos extras no payload de erro. */
    public function __construct(
        private readonly int    $statusCode,
        string                  $message    = '',
        private readonly array  $extra      = [],
    ) {
        parent::__construct($message);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /** @return array<string, mixed> */
    public function getExtra(): array
    {
        return $this->extra;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_merge(
            ['erro' => $this->getMessage(), 'status' => $this->statusCode],
            $this->extra,
        );
    }
}
