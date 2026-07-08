<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Security\AgentGuard;

use JsonSerializable;

final readonly class IntentValidationResult implements JsonSerializable
{
    /**
     * @param  array<int, PolicyViolation>  $violations
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public bool $allowed,
        public array $violations = [],
        public ?string $resource = null,
        public ?string $operation = null,
        public ?string $risk = null,
        public bool $requiresConfirmation = false,
        public array $meta = [],
    ) {}

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function allowed(
        ?string $resource = null,
        ?string $operation = null,
        ?string $risk = null,
        bool $requiresConfirmation = false,
        array $meta = [],
    ): self {
        return new self(true, [], $resource, $operation, $risk, $requiresConfirmation, $meta);
    }

    /**
     * @param  array<int, PolicyViolation>  $violations
     * @param  array<string, mixed>  $meta
     */
    public static function rejected(
        array $violations,
        ?string $resource = null,
        ?string $operation = null,
        ?string $risk = null,
        bool $requiresConfirmation = false,
        array $meta = [],
    ): self {
        return new self(false, $violations, $resource, $operation, $risk, $requiresConfirmation, $meta);
    }

    public function status(): int
    {
        return $this->violations[0]->status ?? 200;
    }

    public function action(): string
    {
        return $this->violations[0]->action ?? 'allowed';
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
            'ok' => $this->allowed,
            'code' => $this->code(),
            'message' => $this->message(),
            'status' => $this->status(),
            'action' => $this->action(),
            'resource' => $this->resource,
            'operation' => $this->operation,
            'risk' => $this->risk,
            'requires_confirmation' => $this->requiresConfirmation,
            'violations' => $this->violations,
            'meta' => $this->meta,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->jsonSerialize();
    }
}
