<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Security\AgentGuard;

use JsonSerializable;

final readonly class PolicyViolation implements JsonSerializable
{
    /**
     * @param array<string, mixed> $details
     */
    public function __construct(
        public string $code,
        public string $message,
        public int $status = 422,
        public string $action = 'blocked',
        public array $details = [],
    ) {}

    /**
     * @param array<string, mixed> $details
     */
    public static function outOfDomain(string $message = 'The requested intent is outside the published ADP business domain.', array $details = []): self
    {
        return new self('ADP_INTENT_OUT_OF_DOMAIN', $message, 422, 'blocked', $details);
    }

    /**
     * @param array<string, mixed> $details
     */
    public static function untrustedInstruction(array $details = []): self
    {
        return new self(
            'ADP_UNTRUSTED_INSTRUCTION_DETECTED',
            'The request contains instructions that attempt to override the ADP execution policy.',
            422,
            'blocked',
            $details,
        );
    }

    /**
     * @param array<string, mixed> $details
     */
    public static function unknownResource(string $resource, array $details = []): self
    {
        return new self(
            'ADP_RESOURCE_NOT_FOUND',
            "Resource [{$resource}] is not published by ADP.",
            404,
            'blocked',
            $details,
        );
    }

    /**
     * @param array<string, mixed> $details
     */
    public static function unknownOperation(string $operation, array $details = []): self
    {
        return new self(
            'ADP_OPERATION_NOT_FOUND',
            "Operation [{$operation}] is not published for the selected ADP resource.",
            404,
            'blocked',
            $details,
        );
    }

    /**
     * @param array<string, mixed> $details
     */
    public static function forbiddenField(string $field, array $details = []): self
    {
        return new self(
            'ADP_FORBIDDEN_FIELD',
            "Field [{$field}] is not visible, selectable, filterable or published by ADP.",
            403,
            'blocked',
            $details,
        );
    }

    /**
     * @param array<string, mixed> $details
     */
    public static function invalidRelation(string $relation, array $details = []): self
    {
        return new self(
            'ADP_INVALID_RELATION',
            "Relation [{$relation}] is not published as an allowed ADP relation.",
            400,
            'blocked',
            $details,
        );
    }

    /**
     * @param array<string, mixed> $details
     */
    public static function invalidOperator(string $operator, array $details = []): self
    {
        return new self(
            'ADP_INVALID_OPERATOR',
            "Filter operator [{$operator}] is not allowed by the ADP filter contract.",
            400,
            'blocked',
            $details,
        );
    }

    /**
     * @param array<string, mixed> $details
     */
    public static function invalidPlan(string $message, array $details = []): self
    {
        return new self('ADP_TOOL_PLAN_INVALID', $message, 422, 'blocked', $details);
    }

    /**
     * @param array<string, mixed> $details
     */
    public static function confirmationRequired(string $operation, string $risk, array $details = []): self
    {
        return new self(
            'ADP_CONFIRMATION_REQUIRED',
            "Operation [{$operation}] is [{$risk}] risk and requires explicit human confirmation.",
            409,
            'confirmation_required',
            $details,
        );
    }

    /**
     * @param array<string, mixed> $details
     */
    public static function criticalBlocked(string $operation, array $details = []): self
    {
        return new self(
            'ADP_CRITICAL_OPERATION_BLOCKED',
            "Critical operation [{$operation}] is blocked by the ADP Agent Guard policy.",
            403,
            'blocked',
            $details,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'code' => $this->code,
            'message' => $this->message,
            'status' => $this->status,
            'action' => $this->action,
            'details' => $this->details,
        ];
    }
}
