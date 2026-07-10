<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\InputGuard;

use JsonSerializable;

final readonly class InputTextValidationResult implements JsonSerializable
{
    /**
     * @param array<int, InputTextViolation> $violations
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public bool $allowed,
        public string $input,
        public string $normalizedInput,
        public array $violations = [],
        public bool $truncated = false,
        public array $metadata = [],
    ) {}

    public static function allowed(string $input, string $normalizedInput, bool $truncated = false, array $metadata = []): self
    {
        return new self(
            allowed: true,
            input: $input,
            normalizedInput: $normalizedInput,
            truncated: $truncated,
            metadata: $metadata,
        );
    }

    /**
     * @param array<int, InputTextViolation> $violations
     * @param array<string, mixed> $metadata
     */
    public static function rejected(string $input, string $normalizedInput, array $violations, array $metadata = []): self
    {
        return new self(
            allowed: false,
            input: $input,
            normalizedInput: $normalizedInput,
            violations: $violations,
            metadata: $metadata,
        );
    }

    public function code(): ?string
    {
        return $this->violations[0]->code ?? null;
    }

    public function message(): ?string
    {
        return $this->violations[0]->message ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'allowed' => $this->allowed,
            'code' => $this->code(),
            'message' => $this->message(),
            'truncated' => $this->truncated,
            'violations' => $this->violations,
            'metadata' => $this->metadata,
        ];
    }
}
