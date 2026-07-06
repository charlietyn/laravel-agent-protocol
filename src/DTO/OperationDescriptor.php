<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\DTO;

use JsonSerializable;
use Ronu\LaravelAgentProtocol\DTO\Concerns\SerializesDescriptor;

final readonly class OperationDescriptor implements JsonSerializable
{
    use SerializesDescriptor;

    /**
     * @param  array<string, mixed>  $request
     * @param  array<string, mixed>  $response
     * @param  array<int, string>  $permissions
     * @param  array<string, mixed>  $security
     * @param  array<int, array<string, mixed>>  $examples
     * @param  array<string, mixed>  $sideEffects
     * @param  array<string, mixed>  $annotations
     */
    public function __construct(
        public string $scenario,
        public string $method,
        public string $endpoint,
        public string $description,
        public ?ValidationDescriptor $validation = null,
        public ?CapabilityDescriptor $capabilities = null,
        public array $request = [],
        public array $response = [],
        public string $source = 'inferred',
        public string $risk = 'medium',
        public bool $requiresConfirmation = false,
        public array $permissions = [],
        public array $security = [],
        public array $examples = [],
        public array $sideEffects = [],
        public array $annotations = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->serializeValues([
            'scenario' => $this->scenario,
            'method' => $this->method,
            'endpoint' => $this->endpoint,
            'description' => $this->description,
            'validation' => $this->validation,
            'capabilities' => $this->capabilities,
            'request' => $this->request,
            'response' => $this->response,
            'source' => $this->source,
            'risk' => $this->risk,
            'requires_confirmation' => $this->requiresConfirmation,
            'permissions' => $this->permissions,
            'security' => $this->security,
            'examples' => $this->examples,
            'side_effects' => $this->sideEffects,
            'annotations' => $this->annotations,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
