<?php

declare(strict_types=1);

namespace GateForm;

use GateForm\Exception\ConfigurationException;

/**
 * Конфигурация SDK.
 *
 * Секретный ключ (secret_key) должен храниться только на сервере:
 * в переменных окружения или в защищённом конфиге. Не передавайте его
 * в HTML, JavaScript или мобильные приложения.
 */
final class Config
{
    public const DEFAULT_BASE_URL = 'https://gateform.ru';
    public const DEFAULT_TOKEN_FIELD = 'gf_token';
    public const DEFAULT_TIMEOUT = 5.0;
    public const DEFAULT_CONNECT_TIMEOUT = 3.0;

    private string $baseUrl;
    private string $siteKey;
    private string $secretKey;
    private float $timeout;
    private float $connectTimeout;
    private bool $failOpen;
    private string $tokenField;

    public function __construct(
        string $siteKey,
        string $secretKey,
        string $baseUrl = self::DEFAULT_BASE_URL,
        float $timeout = self::DEFAULT_TIMEOUT,
        float $connectTimeout = self::DEFAULT_CONNECT_TIMEOUT,
        bool $failOpen = false,
        string $tokenField = self::DEFAULT_TOKEN_FIELD
    ) {
        $siteKey = trim($siteKey);
        $secretKey = trim($secretKey);
        $baseUrl = rtrim(trim($baseUrl), '/');
        $tokenField = trim($tokenField);

        if ($siteKey === '') {
            throw new ConfigurationException('Не задан публичный ключ сайта (site_key).');
        }

        if (preg_match('/^[A-Za-z0-9_-]{8,100}$/', $siteKey) !== 1) {
            throw new ConfigurationException('Некорректный публичный ключ сайта (site_key).');
        }

        if ($secretKey === '') {
            throw new ConfigurationException('Не задан секретный ключ (secret_key).');
        }

        $secretLength = strlen($secretKey);

        if ($secretLength < 16 || $secretLength > 100) {
            throw new ConfigurationException('Некорректный секретный ключ (secret_key).');
        }

        if ($baseUrl === '') {
            throw new ConfigurationException('Не задан адрес сервиса (base_url).');
        }

        if (preg_match('#^https?://[^\s]+$#i', $baseUrl) !== 1) {
            throw new ConfigurationException('Некорректный адрес сервиса (base_url).');
        }

        if ($timeout <= 0.0 || $timeout > 60.0) {
            throw new ConfigurationException('Таймаут запроса должен быть больше 0 и не больше 60 секунд.');
        }

        if ($connectTimeout <= 0.0 || $connectTimeout > 60.0) {
            throw new ConfigurationException('Таймаут соединения должен быть больше 0 и не больше 60 секунд.');
        }

        if ($connectTimeout > $timeout) {
            $connectTimeout = $timeout;
        }

        if (preg_match('/^[A-Za-z0-9_\-\[\]]{1,64}$/', $tokenField) !== 1) {
            throw new ConfigurationException('Некорректное имя поля с токеном (token_field).');
        }

        $this->siteKey = $siteKey;
        $this->secretKey = $secretKey;
        $this->baseUrl = $baseUrl;
        $this->timeout = $timeout;
        $this->connectTimeout = $connectTimeout;
        $this->failOpen = $failOpen;
        $this->tokenField = $tokenField;
    }

    /**
     * @param array{
     *     site_key?: string,
     *     secret_key?: string,
     *     base_url?: string,
     *     timeout?: float|int,
     *     connect_timeout?: float|int,
     *     fail_open?: bool,
     *     token_field?: string
     * } $options
     */
    public static function fromArray(array $options): self
    {
        return new self(
            (string) ($options['site_key'] ?? ''),
            (string) ($options['secret_key'] ?? ''),
            (string) ($options['base_url'] ?? self::DEFAULT_BASE_URL),
            (float) ($options['timeout'] ?? self::DEFAULT_TIMEOUT),
            (float) ($options['connect_timeout'] ?? self::DEFAULT_CONNECT_TIMEOUT),
            (bool) ($options['fail_open'] ?? false),
            (string) ($options['token_field'] ?? self::DEFAULT_TOKEN_FIELD)
        );
    }

    public function baseUrl(): string
    {
        return $this->baseUrl;
    }

    public function siteKey(): string
    {
        return $this->siteKey;
    }

    public function secretKey(): string
    {
        return $this->secretKey;
    }

    public function timeout(): float
    {
        return $this->timeout;
    }

    public function connectTimeout(): float
    {
        return $this->connectTimeout;
    }

    public function failOpen(): bool
    {
        return $this->failOpen;
    }

    public function tokenField(): string
    {
        return $this->tokenField;
    }

    public function validateUrl(): string
    {
        return $this->baseUrl . '/api/v1/validate';
    }

    public function clientJsUrl(): string
    {
        return $this->baseUrl . '/client.js';
    }
}
