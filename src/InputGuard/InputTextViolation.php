<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\InputGuard;

use JsonSerializable;

final readonly class InputTextViolation implements JsonSerializable
{
    /**
     * @param array<string, mixed> $details
     */
    public function __construct(
        public string $code,
        public string $message,
        public string $severity = 'error',
        public array $details = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'code' => $this->code,
            'message' => $this->message,
            'severity' => $this->severity,
            'details' => $this->details,
        ];
    }
}
