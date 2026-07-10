<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Runtime\Scope;

use JsonSerializable;
use Ronu\LaravelAgentProtocol\Security\AgentGuard\IntentPlan;

final readonly class BusinessScopeDecision implements JsonSerializable
{
    /**
     * @param array<int, array<string, mixed>> $conflicts
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public bool $allowed,
        public ?IntentPlan $plan,
        public BusinessScope $scope,
        public ?string $code = null,
        public ?string $message = null,
        public array $conflicts = [],
        public array $metadata = [],
    ) {}

    /**
     * @param array<string, mixed> $metadata
     */
    public static function allowed(IntentPlan $plan, BusinessScope $scope, array $metadata = []): self
    {
        return new self(true, $plan, $scope, metadata: $metadata);
    }

    /**
     * @param array<int, array<string, mixed>> $conflicts
     * @param array<string, mixed> $metadata
     */
    public static function rejected(BusinessScope $scope, string $code, string $message, array $conflicts = [], array $metadata = []): self
    {
        return new self(false, null, $scope, $code, $message, $conflicts, $metadata);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'allowed' => $this->allowed,
            'code' => $this->code,
            'message' => $this->message,
            'plan' => $this->plan,
            'scope' => $this->scope,
            'conflicts' => $this->conflicts,
            'metadata' => $this->metadata,
        ];
    }
}
