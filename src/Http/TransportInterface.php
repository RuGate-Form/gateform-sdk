<?php

declare(strict_types=1);

namespace GateForm\Http;

use GateForm\Exception\TransportException;

/**
 * Транспорт HTTP-запросов. Реализация подменяема — это удобно для тестов.
 */
interface TransportInterface
{
    /**
     * @param array<string, mixed> $payload
     * @param list<string>         $headers
     *
     * @throws TransportException
     */
    public function postJson(
        string $url,
        array $payload,
        array $headers,
        float $connectTimeout,
        float $timeout
    ): Response;
}
