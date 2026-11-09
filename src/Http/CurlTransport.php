<?php

declare(strict_types=1);

namespace GateForm\Http;

use GateForm\Exception\TransportException;

/**
 * HTTP-транспорт на cURL. Рекомендуемый вариант.
 */
final class CurlTransport implements TransportInterface
{
    public const USER_AGENT = 'GateForm-PHP-SDK/1.0';

    public function postJson(
        string $url,
        array $payload,
        array $headers,
        float $connectTimeout,
        float $timeout
    ): Response {
        if (!function_exists('curl_init')) {
            throw new TransportException('Расширение cURL не установлено.');
        }

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            throw new TransportException('Не удалось сериализовать запрос в JSON.');
        }

        $handle = curl_init($url);

        if ($handle === false) {
            throw new TransportException('Не удалось инициализировать cURL.');
        }

        $httpHeaders = array_merge(
            ['Content-Type: application/json', 'Accept: application/json'],
            $headers
        );

        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => $httpHeaders,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT_MS => max(1, (int) round($connectTimeout * 1000)),
            CURLOPT_TIMEOUT_MS => max(1, (int) round($timeout * 1000)),
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => self::USER_AGENT,
        ]);

        $body = curl_exec($handle);
        $errno = curl_errno($handle);
        $error = curl_error($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);

        if ($errno !== 0 || $body === false) {
            throw new TransportException(sprintf(
                'Ошибка HTTP-запроса: %s',
                $error !== '' ? $error : 'неизвестная ошибка cURL'
            ));
        }

        return new Response($status, (string) $body);
    }
}
