<?php

declare(strict_types=1);

/**
 * Локальный smoke-тест SDK без сети: транспорт подменяется фейком.
 *
 * Запуск: composer test   (или php tests/smoke.php)
 */

$autoload = __DIR__ . '/../vendor/autoload.php';

if (is_file($autoload)) {
    require $autoload;
} else {
    spl_autoload_register(static function (string $class): void {
        $prefix = 'GateForm\\';

        if (!str_starts_with($class, $prefix)) {
            return;
        }

        $relative = substr($class, strlen($prefix));
        $path = __DIR__ . '/../src/' . str_replace('\\', '/', $relative) . '.php';

        if (is_file($path)) {
            require $path;
        }
    });
}

use GateForm\Client;
use GateForm\Config;
use GateForm\Exception\ConfigurationException;
use GateForm\Exception\TransportException;
use GateForm\Http\Response;
use GateForm\Http\TransportInterface;

final class FakeTransport implements TransportInterface
{
    /** @var list<array<string, mixed>> */
    public array $calls = [];

    public function __construct(
        private int $status = 200,
        private string $body = '{}',
        private ?string $error = null
    ) {
    }

    public function postJson(
        string $url,
        array $payload,
        array $headers,
        float $connectTimeout,
        float $timeout
    ): Response {
        $this->calls[] = [
            'url' => $url,
            'payload' => $payload,
            'headers' => $headers,
            'connect_timeout' => $connectTimeout,
            'timeout' => $timeout,
        ];

        if ($this->error !== null) {
            throw new TransportException($this->error);
        }

        return new Response($this->status, $this->body);
    }
}

$failures = 0;
$checks = 0;

function check(bool $condition, string $label): void
{
    global $failures, $checks;

    $checks++;

    if (!$condition) {
        $failures++;
        fwrite(STDERR, 'FAIL: ' . $label . PHP_EOL);
    }
}

function makeClient(FakeTransport $transport, bool $failOpen = false, string $tokenField = 'gf_token'): Client
{
    return new Client(new Config(
        'gf_site_test_123456',
        'gf_sec_test_1234567890abcdef',
        'https://gateform.example',
        5.0,
        3.0,
        $failOpen,
        $tokenField
    ), $transport);
}

// 1. Конфигурация: валидные значения.
$config = Config::fromArray([
    'site_key' => 'gf_site_test_123456',
    'secret_key' => 'gf_sec_test_1234567890abcdef',
    'base_url' => 'https://gateform.example/',
]);
check($config->baseUrl() === 'https://gateform.example', 'base_url нормализуется без слэша');
check($config->validateUrl() === 'https://gateform.example/api/v1/validate', 'URL проверки');
check($config->clientJsUrl() === 'https://gateform.example/client.js', 'URL client.js');

// 2. Конфигурация: ошибки.
$thrown = false;

try {
    Config::fromArray(['site_key' => '', 'secret_key' => 'gf_sec_test_1234567890abcdef']);
} catch (ConfigurationException $exception) {
    $thrown = true;
}

check($thrown, 'пустой site_key вызывает ConfigurationException');

$thrown = false;

try {
    Config::fromArray(['site_key' => 'gf_site_test_123456', 'secret_key' => 'short']);
} catch (ConfigurationException $exception) {
    $thrown = true;
}

check($thrown, 'короткий secret_key вызывает ConfigurationException');

// 3. Успешная проверка.
$transport = new FakeTransport(200, json_encode([
    'success' => true,
    'valid' => true,
    'action' => 'allow',
    'score' => 0.95,
    'fail_open' => false,
    'expires_at' => '2026-10-20T12:10:00.000Z',
], JSON_UNESCAPED_UNICODE) ?: '{}');

$result = makeClient($transport)->validate('token-value-1234567890');
check($result->isValid(), 'валидный ответ принимается');
check($result->code() === 'OK', 'код OK');
check(abs($result->score() - 0.95) < 0.0001, 'score пробрасывается');
check(!$result->isFailOpen(), 'fail_open = false');
check(count($transport->calls) === 1, 'выполнен один HTTP-запрос');
check(
    $transport->calls[0]['payload']['secret_key'] === 'gf_sec_test_1234567890abcdef',
    'secret_key передаётся в запросе'
);

// 4. Просроченный токен.
$result = makeClient(new FakeTransport(200, '{"success":false,"valid":false,"code":"TOKEN_EXPIRED"}'))
    ->validate('token-value-1234567890');
check(!$result->isValid(), 'просроченный токен отклоняется');
check($result->code() === 'TOKEN_EXPIRED', 'код TOKEN_EXPIRED');

// 5. Неверный секретный ключ.
$result = makeClient(new FakeTransport(401, '{"success":false,"valid":false,"code":"INVALID_CREDENTIALS"}'))
    ->validate('token-value-1234567890');
check(!$result->isValid(), '401 отклоняется');
check($result->code() === 'INVALID_CREDENTIALS', 'код INVALID_CREDENTIALS');

// 6. Недоступность сервиса без fail-open.
$result = makeClient(new FakeTransport(503, '{"code":"VALIDATION_UNAVAILABLE"}'))
    ->validate('token-value-1234567890');
check(!$result->isValid(), '503 отклоняется при fail_open = false');
check($result->code() === 'SERVICE_UNAVAILABLE', 'код SERVICE_UNAVAILABLE');

// 7. Недоступность сервиса с fail-open.
$result = makeClient(new FakeTransport(503, '{}'), true)->validate('token-value-1234567890');
check($result->isValid(), '503 принимается при fail_open = true');
check($result->isFailOpen(), 'отмечен fail_open');

// 8. Сетевая ошибка с fail-open.
$result = makeClient(new FakeTransport(200, '{}', 'Connection timed out'), true)
    ->validate('token-value-1234567890');
check($result->isValid(), 'сетевая ошибка принимается при fail_open = true');
check($result->isFailOpen(), 'сетевая ошибка помечена fail_open');

// 9. Пустой токен не требует запроса.
$transport = new FakeTransport(200, '{"valid":true}');
$result = makeClient($transport)->validate('');
check(!$result->isValid(), 'пустой токен отклоняется');
check($result->code() === 'TOKEN_MISSING', 'код TOKEN_MISSING');
check(count($transport->calls) === 0, 'запрос не выполняется для пустого токена');

// 10. Чтение токена из переданного массива и своё имя поля.
$transport = new FakeTransport(200, '{"valid":true,"score":0.8}');
$client = makeClient($transport, false, 'custom_token');
$result = $client->validatePost(['custom_token' => 'token-value-1234567890']);
check($result->isValid(), 'токен читается из своего поля');
check($transport->calls[0]['payload']['token'] === 'token-value-1234567890', 'токен ушёл в запрос');

// 11. Массив вместо строки не ломает клиент.
$result = makeClient(new FakeTransport(200, '{"valid":true}'))
    ->validatePost(['gf_token' => ['nested']]);
check(!$result->isValid(), 'массив вместо токена отклоняется');
check($result->code() === 'INVALID_TOKEN', 'код INVALID_TOKEN');

if ($failures > 0) {
    fwrite(STDERR, sprintf('Провалено проверок: %d из %d.', $failures, $checks) . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, sprintf('Smoke-тест SDK пройден: %d проверок.', $checks) . PHP_EOL);
