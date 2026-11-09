<?php

declare(strict_types=1);

namespace GateForm;

use GateForm\Exception\TransportException;
use GateForm\Http\CurlTransport;
use GateForm\Http\StreamTransport;
use GateForm\Http\TransportInterface;

/**
 * Клиент серверной проверки токенов GateForm.
 *
 * Типичный сценарий:
 *   $client = Client::create([
 *       'site_key' => getenv('GATEFORM_SITE_KEY'),
 *       'secret_key' => getenv('GATEFORM_SECRET_KEY'),
 *       'base_url' => 'https://gateform.ru',
 *   ]);
 *
 *   $result = $client->validatePost();
 *   if (!$result->isValid()) {
 *       // отклонить форму
 *   }
 */
final class Client
{
    public const VERSION = '1.0.0';

    private Config $config;
    private TransportInterface $transport;

    public function __construct(Config $config, ?TransportInterface $transport = null)
    {
        $this->config = $config;
        $this->transport = $transport ?? self::defaultTransport();
    }

    /**
     * @param array<string, mixed> $options
     */
    public static function create(array $options, ?TransportInterface $transport = null): self
    {
        return new self(Config::fromArray($options), $transport);
    }

    public static function defaultTransport(): TransportInterface
    {
        return function_exists('curl_init')
            ? new CurlTransport()
            : new StreamTransport();
    }

    public function config(): Config
    {
        return $this->config;
    }

    /**
     * Проверяет одноразовый токен из формы.
     *
     * Токен одноразовый: повторная проверка вернёт TOKEN_ALREADY_USED.
     * Сохраняйте результат проверки в рамках обработки одной заявки.
     */
    public function validate(string $token): ValidationResult
    {
        $token = trim($token);

        if ($token === '') {
            return ValidationResult::invalid(
                'TOKEN_MISSING',
                'В форме отсутствует токен проверки.'
            );
        }

        if (strlen($token) > 4096) {
            return ValidationResult::invalid('INVALID_TOKEN', 'Токен проверки слишком длинный.');
        }

        try {
            $response = $this->transport->postJson(
                $this->config->validateUrl(),
                [
                    'secret_key' => $this->config->secretKey(),
                    'site_key' => $this->config->siteKey(),
                    'token' => $token,
                ],
                [],
                $this->config->connectTimeout(),
                $this->config->timeout()
            );
        } catch (TransportException $exception) {
            return $this->handleUnavailable($exception->getMessage());
        }

        if ($response->status === 429) {
            return $this->handleUnavailable('Сервис проверки ограничил частоту запросов.');
        }

        if ($response->status === 0 || $response->status >= 500) {
            return $this->handleUnavailable(
                'Сервис проверки вернул HTTP ' . $response->status . '.'
            );
        }

        $data = json_decode($response->body, true);

        if (!is_array($data)) {
            return $this->handleUnavailable('Некорректный ответ сервиса проверки.');
        }

        if ($response->status === 401) {
            return ValidationResult::invalid(
                (string) ($data['code'] ?? 'INVALID_CREDENTIALS'),
                (string) ($data['message'] ?? 'Секретный ключ не принят.'),
                $data
            );
        }

        if ($response->status !== 200) {
            return ValidationResult::invalid(
                (string) ($data['code'] ?? 'VALIDATION_FAILED'),
                (string) ($data['message'] ?? 'Проверка не пройдена.'),
                $data
            );
        }

        if (!empty($data['valid'])) {
            return ValidationResult::valid(
                (float) ($data['score'] ?? 0.0),
                (bool) ($data['fail_open'] ?? false),
                isset($data['expires_at']) ? (string) $data['expires_at'] : null,
                $data
            );
        }

        return ValidationResult::invalid(
            (string) ($data['code'] ?? 'NOT_VALID'),
            (string) ($data['message'] ?? 'Токен не принят.'),
            $data
        );
    }

    /**
     * Читает токен из суперглобального массива (по умолчанию $_POST)
     * и проверяет его.
     *
     * @param array<string, mixed>|null $source
     */
    public function validatePost(?array $source = null): ValidationResult
    {
        $source = $source ?? $_POST;
        $value = $source[$this->config->tokenField()] ?? '';

        if (is_array($value) || is_object($value)) {
            return ValidationResult::invalid('INVALID_TOKEN', 'Некорректный формат токена.');
        }

        return $this->validate((string) $value);
    }

    /**
     * Короткая форма: true, если форму можно принять
     * (валидный токен или разрешённый fail-open).
     */
    public function isAcceptable(string $token): bool
    {
        return $this->validate($token)->isValid();
    }

    private function handleUnavailable(string $reason): ValidationResult
    {
        if ($this->config->failOpen()) {
            return ValidationResult::failOpen($reason);
        }

        return ValidationResult::invalid('SERVICE_UNAVAILABLE', $reason);
    }
}
