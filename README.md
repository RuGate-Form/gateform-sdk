# GateForm PHP SDK

Серверная проверка токенов **GateForm** для форм на PHP.

SDK отправляет одноразовый токен из формы на `POST /api/v1/validate` и возвращает
понятный результат: принят, отклонён или принят по политике fail-open.
Секретный ключ никогда не покидает сервер.

## Требования

- PHP **8.1+**
- Расширения `json` (обязательно), `curl` (рекомендуется; без него используется резервный транспорт на streams)
- Аккаунт GateForm и созданный **ключ проверки** (`site_key` + `secret_key`)

## Установка

```bash
composer require gateform/sdk:^1.0
```

## Настройка

Ключи храните в переменных окружения, а не в коде:

```env
GATEFORM_SITE_KEY=gf_site_...
GATEFORM_SECRET_KEY=gf_sec_...
GATEFORM_BASE_URL=https://gateform.ru
```

```php
use GateForm\Client;

$client = Client::create([
    'site_key' => getenv('GATEFORM_SITE_KEY'),
    'secret_key' => getenv('GATEFORM_SECRET_KEY'),
    'base_url' => getenv('GATEFORM_BASE_URL'),
]);
```

### Параметры

| Параметр | Обязателен | По умолчанию | Описание |
| --- | --- | --- | --- |
| `site_key` | да | — | Публичный ключ площадки |
| `secret_key` | да | — | Секретный ключ, только для сервера |
| `base_url` | нет | `https://gateform.ru` | Адрес вашего экземпляра GateForm |
| `timeout` | нет | `5.0` | Таймаут запроса, секунды |
| `connect_timeout` | нет | `3.0` | Таймаут соединения, секунды |
| `fail_open` | нет | `false` | Принимать форму при недоступности сервиса |
| `token_field` | нет | `gf_token` | Имя поля с токеном |

## Использование

### Проверка формы

```php
$result = $client->validatePost(); // читает $_POST['gf_token']

if (!$result->isValid()) {
    http_response_code(400);
    exit('Не удалось проверить форму. Обновите страницу и попробуйте снова.');
}

// Токен принят — обрабатываем заявку.
```

Токен из другого источника:

```php
$result = $client->validate($token);
```

Короткая форма:

```php
if (!$client->isAcceptable($token)) {
    // отклонить
}
```

### Результат проверки

```php
$result->isValid();     // true — форму можно принять
$result->code();        // OK, TOKEN_EXPIRED, TOKEN_ALREADY_USED, ...
$result->score();       // оценка доверия 0..1
$result->isFailOpen();  // true — принято без проверки из-за сбоя сервиса
$result->expiresAt();   // срок действия токена (ISO 8601) или null
$result->message();     // пояснение для логов
$result->raw();         // исходный ответ API
```

### Коды результатов

| Код | Значение |
| --- | --- |
| `OK` | Токен валиден |
| `FAIL_OPEN` | Сервис недоступен, форма принята по политике `fail_open` |
| `TOKEN_MISSING` | В форме нет токена |
| `INVALID_TOKEN` | Токен повреждён или неверного формата |
| `TOKEN_EXPIRED` | Срок действия токена истёк |
| `TOKEN_ALREADY_USED` | Токен уже использован (он одноразовый) |
| `TOKEN_KEY_MISMATCH` | Токен выдан для другого ключа |
| `INVALID_CREDENTIALS` | Неверный `secret_key` или ключ отключён |
| `ACCOUNT_BLOCKED` | Аккаунт владельца ключа заблокирован |
| `SERVICE_UNAVAILABLE` | Сервис недоступен, `fail_open = false` |

## Политика fail-open

По умолчанию SDK работает **fail-closed**: если сервис проверки недоступен,
форма отклоняется. Это безопасный режим для платежей и регистрации.

Если важнее не терять заявки при сбое, включите `fail_open => true`:
при сетевой ошибке, таймауте, HTTP 5xx или 429 форма будет принята,
а результат получит `isFailOpen() === true`. Обязательно логируйте такие случаи.

```php
if ($result->isFailOpen()) {
    error_log('GateForm fail-open: ' . $result->message());
}
```

Неверный секретный ключ (`INVALID_CREDENTIALS`) **никогда** не приводит к fail-open:
это ошибка конфигурации, и её нужно исправлять.

## Подмена транспорта

Для тестов можно передать свою реализацию `GateForm\Http\TransportInterface`:

```php
use GateForm\Client;
use GateForm\Config;
use GateForm\Http\Response;
use GateForm\Http\TransportInterface;

final class FakeTransport implements TransportInterface
{
    public function postJson(string $url, array $payload, array $headers, float $connectTimeout, float $timeout): Response
    {
        return new Response(200, '{"valid":true,"score":0.9}');
    }
}

$client = new Client(new Config($siteKey, $secretKey, $baseUrl), new FakeTransport());
```

## Связанные пакеты

- [gateform/yii2](https://github.com/RuGate-Form/gateform-yii2) — компонент, валидатор, виджеты и фильтр для Yii2
- [gateform/yii3](https://github.com/RuGate-Form/gateform-yii3) — виджеты, валидатор и PSR-15 middleware для Yii3
- [gateform/laravel](https://github.com/RuGate-Form/gateform-laravel) — правило валидации, middleware и Blade-компоненты
- [WordPress-плагин GateForm](https://gateform.ru/docs) — капча, Contact Form 7 и виджеты

## Лицензия

MIT. См. [LICENSE](LICENSE).

Разработчик: **GateForm** — <https://gateform.ru>
