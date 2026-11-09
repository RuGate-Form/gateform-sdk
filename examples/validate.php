<?php

declare(strict_types=1);

/**
 * Пример обработчика формы: проверка токена перед обработкой заявки.
 *
 * Переменные окружения:
 *   GATEFORM_SITE_KEY, GATEFORM_SECRET_KEY, GATEFORM_BASE_URL
 */

require __DIR__ . '/../vendor/autoload.php';

use GateForm\Client;

$client = Client::create([
    'site_key' => (string) (getenv('GATEFORM_SITE_KEY') ?: ''),
    'secret_key' => (string) (getenv('GATEFORM_SECRET_KEY') ?: ''),
    'base_url' => (string) (getenv('GATEFORM_BASE_URL') ?: 'https://gateform.ru'),
    // false — при недоступности сервиса форма отклоняется (безопасный режим).
    // true  — форма принимается даже при сбое сервиса (доступность важнее).
    'fail_open' => false,
    'token_field' => 'gf_token',
]);

$result = $client->validatePost();

if (!$result->isValid()) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Не удалось проверить форму. Обновите страницу и попробуйте снова.';
    exit;
}

if ($result->isFailOpen()) {
    // Заявка принята без проверки из-за недоступности сервиса.
    error_log('GateForm: fail-open, ' . $result->message());
}

// Токен принят — обрабатываем заявку.
$email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
$message = trim((string) ($_POST['message'] ?? ''));

if ($email === false || $email === null || $message === '') {
    http_response_code(422);
    echo 'Заполните обязательные поля.';
    exit;
}

// ... сохранение заявки, отправка письма и т.д.

echo 'Спасибо! Заявка отправлена.';
