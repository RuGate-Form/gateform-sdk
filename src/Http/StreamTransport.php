<?php

declare(strict_types=1);

namespace GateForm\Http;

use GateForm\Exception\TransportException;

/**
 * Резервный HTTP-транспорт без cURL (streams).
 *
 * Используется, только если расширение cURL недоступно.
 * Не умеет отдельно задавать таймаут соединения — применяется общий таймаут.
 */
final class StreamTransport implements TransportInterface
{
    public const USER_AGENT = 'GateForm-PHP-SDK/1.0';

    public function postJson(
        string $url,
        array $payload,
        array $headers,
        float $connectTimeout,
        float $timeout
    ): Response {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            throw new TransportException('Не удалось сериализовать запрос в JSON.');
        }

        $httpHeaders = array_merge([
            'Content-Type: application/json',
            'Accept: application/json',
            'Content-Length: ' . strlen($json),
            'User-Agent: ' . self::USER_AGENT,
            'Connection: close',
        ], $headers);

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $httpHeaders),
                'content' => $json,
                'timeout' => max(0.1, $timeout),
                'ignore_errors' => true,
                'follow_location' => 0,
                'max_redirects' => 0,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);

        if ($body === false) {
            $lastError = error_get_last();
            $message = is_array($lastError) && isset($lastError['message'])
                ? (string) $lastError['message']
                : 'нет соединения';

            throw new TransportException('Ошибка HTTP-запроса: ' . $message);
        }

        $status = 0;
        $responseHeaders = $http_response_header ?? [];

        foreach ($responseHeaders as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', (string) $header, $matches) === 1) {
                $status = (int) $matches[1];
            }
        }

        return new Response($status, (string) $body);
    }
}
