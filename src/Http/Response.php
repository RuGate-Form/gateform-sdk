<?php

declare(strict_types=1);

namespace GateForm\Http;

/**
 * Ответ HTTP-транспорта.
 */
final class Response
{
    public function __construct(
        public readonly int $status,
        public readonly string $body
    ) {
    }
}
