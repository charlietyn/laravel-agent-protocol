<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Metadata;

use Ronu\LaravelAgentProtocol\DTO\CapabilityDescriptor;
use Ronu\LaravelAgentProtocol\DTO\OperationDescriptor;
use Ronu\LaravelAgentProtocol\DTO\ValidationDescriptor;
use Ronu\LaravelAgentProtocol\Security\OperationRiskClassifier;

final class OperationFactory
{
    public function __construct(
        private readonly ?OperationRiskClassifier $riskClassifier = null,
    ) {}

    /**
     * @param  array<string, ValidationDescriptor>  $validations
     * @param  array<string, array<string, mixed>>  $overrides
     * @param  array<string, mixed>  $security
     * @return array<int, OperationDescriptor>
     */
    public function fromValidations(
        array $validations,
        string $endpoint,
        string $source = 'form-request',
        array $overrides = [],
        array $security = [],
    ): array {
        $operations = [];

        foreach ($validations as $scenario => $validation) {
            $operations[] = $this->make(
                (string) $scenario,
                $endpoint,
                $validation,
                $source,
                $overrides[(string) $scenario] ?? [],
                $security,
            );
        }

        if ($operations === []) {
            foreach (['query', 'create', 'update', 'delete'] as $scenario) {
                $operations[] = $this->make(
                    $scenario,
                    $endpoint,
                    null,
                    'rest-generic-class-default',
                    $overrides[$scenario] ?? [],
                    $security,
                );
            }
        }

        return $operations;
    }

    /**
     * @param  array<string, mixed>  $security
     * @param  array<string, mixed>  $overrides
     */
    public function fromRoute(
        string $scenario,
        string $method,
        string $endpoint,
        ?ValidationDescriptor $validation = null,
        array $security = [],
        array $overrides = [],
    ): OperationDescriptor {
        $method = strtoupper($this->stringOrDefault($overrides['method'] ?? $method, $method));
        $endpoint = $this->stringOrDefault($overrides['endpoint'] ?? $endpoint, $endpoint);
        $risk = $this->risk($scenario, $method, $overrides);

        return new OperationDescriptor(
            scenario: $scenario,
            method: $method,
            endpoint: $endpoint,
            description: $this->stringOrDefault(
                $overrides['description'] ?? null,
                "Operation discovered from Laravel route for scenario '{$scenario}'.",
            ),
            validation: $validation,
            capabilities: $this->capabilityFor($scenario),
            request: [
                'content_type' => in_array($method, ['POST', 'PUT', 'PATCH'], true)
                    ? 'application/json'
                    : null,
            ],
            response: [
                'content_type' => 'application/json',
            ],
            source: 'route',
            risk: $risk,
            requiresConfirmation: $this->requiresConfirmation($risk, $overrides),
            permissions: $this->stringList($overrides['permissions'] ?? $security['permissions'] ?? []),
            security: $security,
            examples: $this->arrayList($overrides['examples'] ?? []),
            sideEffects: $this->arrayValue($overrides['side_effects'] ?? []),
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @param  array<string, mixed>  $security
     */
    private function make(
        string $scenario,
        string $endpoint,
        ?ValidationDescriptor $validation,
        string $source,
        array $overrides = [],
        array $security = [],
    ): OperationDescriptor {
        [$method, $suffix] = $this->httpShapeFor($scenario);
        $method = strtoupper($this->stringOrDefault($overrides['method'] ?? $method, $method));
        $operationEndpoint = $suffix === '' ? $endpoint : rtrim($endpoint, '/').'/'.$suffix;
        $operationEndpoint = $this->stringOrDefault($overrides['endpoint'] ?? $operationEndpoint, $operationEndpoint);
        $risk = $this->risk($scenario, $method, $overrides);

        return new OperationDescriptor(
            scenario: $scenario,
            method: $method,
            endpoint: $operationEndpoint,
            description: $this->stringOrDefault(
                $overrides['description'] ?? null,
                "Operation inferred for '{$scenario}' scenario.",
            ),
            validation: $validation,
            capabilities: $this->capabilityFor($scenario),
            request: [
                'content_type' => in_array($method, ['POST', 'PUT', 'PATCH'], true) ? 'application/json' : null,
            ],
            response: [
                'content_type' => 'application/json',
            ],
            source: $source,
            risk: $risk,
            requiresConfirmation: $this->requiresConfirmation($risk, $overrides),
            permissions: $this->stringList($overrides['permissions'] ?? $security['permissions'] ?? []),
            security: $security,
            examples: $this->arrayList($overrides['examples'] ?? []),
            sideEffects: $this->arrayValue($overrides['side_effects'] ?? []),
        );
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function httpShapeFor(string $scenario): array
    {
        return match ($scenario) {
            'query', 'index', 'show' => ['GET', ''],
            'query_one' => ['GET', '{id}'],
            'create', 'bulk_create', 'register', 'admin_create' => ['POST', ''],
            'update', 'bulk_update', 'update_profile', 'change_password', 'reset_password' => ['PUT', '{id}'],
            'delete', 'destroy', 'delete_by_id' => ['DELETE', '{id}'],
            'restore', 'restore_multiple' => ['POST', 'restore'],
            'force_delete', 'force_delete_multiple' => ['DELETE', 'force'],
            'export_excel' => ['GET', 'export/excel'],
            'export_pdf' => ['GET', 'export/pdf'],
            default => ['POST', $scenario],
        };
    }

    private function capabilityFor(string $scenario): CapabilityDescriptor
    {
        return new CapabilityDescriptor(
            query: in_array($scenario, ['query', 'query_one', 'index', 'show'], true),
            create: str_contains($scenario, 'create') || in_array($scenario, ['register'], true),
            update: str_contains($scenario, 'update') || in_array($scenario, ['change_password', 'reset_password'], true),
            bulkCreate: str_contains($scenario, 'bulk_create'),
            bulkUpdate: str_contains($scenario, 'bulk_update'),
            delete: in_array($scenario, ['delete', 'destroy', 'delete_by_id'], true),
            restore: str_contains($scenario, 'restore'),
            forceDelete: str_contains($scenario, 'force_delete'),
            export: str_starts_with($scenario, 'export_'),
            risks: [$this->classifier()->classify($scenario)],
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function risk(string $scenario, string $method, array $overrides): string
    {
        $classifier = $this->classifier();

        return $classifier->normalize(
            $overrides['risk'] ?? null,
            $classifier->classify($scenario, $method),
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function requiresConfirmation(string $risk, array $overrides): bool
    {
        if (isset($overrides['requires_confirmation'])) {
            return (bool) $overrides['requires_confirmation'];
        }

        return $this->classifier()->requiresConfirmation($risk);
    }

    private function classifier(): OperationRiskClassifier
    {
        return $this->riskClassifier ?? new OperationRiskClassifier;
    }

    private function stringOrDefault(mixed $value, string $default): string
    {
        return is_string($value) && $value !== '' ? $value : $default;
    }

    /**
     * @return array<int, string>
     */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, is_string(...)));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function arrayList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, is_array(...)));
    }

    /**
     * @return array<string, mixed>
     */
    private function arrayValue(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }
}
