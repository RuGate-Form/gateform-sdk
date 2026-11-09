<?php

declare(strict_types=1);

namespace GateForm;

/**
 * Результат серверной проверки токена.
 *
 * Поле fail_open = true означает, что сервис проверки был недоступен,
 * а политика конфигурации разрешила принять форму без проверки.
 */
final class ValidationResult
{
    /**
     * @param array<string, mixed> $raw
     */
    private function __construct(
        private bool $valid,
        private string $code,
        private float $score,
        private bool $failOpen,
        private ?string $expiresAt,
        private string $message,
        private array $raw
    ) {
    }

    /**
     * @param array<string, mixed> $raw
     */
    public static function valid(
        float $score = 0.0,
        bool $failOpen = false,
        ?string $expiresAt = null,
        array $raw = []
    ): self {
        return new self(true, 'OK', $score, $failOpen, $expiresAt, '', $raw);
    }

    /**
     * @param array<string, mixed> $raw
     */
    public static function invalid(string $code, string $message = '', array $raw = []): self
    {
        return new self(
            false,
            $code !== '' ? $code : 'NOT_VALID',
            0.0,
            false,
            null,
            $message,
            $raw
        );
    }

    /**
     * Сервис недоступен, но политика fail-open разрешила принять форму.
     */
    public static function failOpen(string $message = ''): self
    {
        return new self(true, 'FAIL_OPEN', 0.0, true, null, $message, []);
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function code(): string
    {
        return $this->code;
    }

    public function score(): float
    {
        return $this->score;
    }

    public function isFailOpen(): bool
    {
        return $this->failOpen;
    }

    public function expiresAt(): ?string
    {
        return $this->expiresAt;
    }

    public function message(): string
    {
        return $this->message;
    }

    /**
     * @return array<string, mixed>
     */
    public function raw(): array
    {
        return $this->raw;
    }
}
