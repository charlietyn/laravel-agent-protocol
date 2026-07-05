<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Metadata;

use Ronu\LaravelAgentProtocol\DTO\CapabilityDescriptor;
use Ronu\LaravelAgentProtocol\DTO\OperationDescriptor;
use Ronu\LaravelAgentProtocol\DTO\ValidationDescriptor;

final class OperationFactory
{
    /**
     * @param  array<string, ValidationDescriptor>  $validations
     * @return array<int, OperationDescriptor>
     */
    public function fromValidations(
        array $validations,
        string $endpoint,
        string $source = 'form-request',
    ): array {
        $operations = [];

        foreach ($validations as $scenario => $validation) {
            $operations[] = $this->make((string) $scenario, $endpoint, $validation, $source);
        }

        if ($operations === []) {
            foreach (['query', 'create', 'update', 'delete'] as $scenario) {
                $operations[] = $this->make($scenario, $endpoint, null, 'rest-generic-class-default');
            }
        }

        return $operations;
    }

    public function fromRoute(
        string $scenario,
        string $method,
        string $endpoint,
        ?ValidationDescriptor $validation = null,
    ): OperationDescriptor {
        return new OperationDescriptor(
            scenario: $scenario,
            method: strtoupper($method),
            endpoint: $endpoint,
            description: "Operation discovered from Laravel route for scenario '{$scenario}'.",
            validation: $validation,
            capabilities: $this->capabilityFor($scenario),
            request: [
                'content_type' => in_array(strtoupper($method), ['POST', 'PUT', 'PATCH'], true)
                    ? 'application/json'
                    : null,
            ],
            response: [
                'content_type' => 'application/json',
            ],
            source: 'route',
        );
    }

    private function make(
        string $scenario,
        string $endpoint,
        ?ValidationDescriptor $validation,
        string $source,
    ): OperationDescriptor {
        [$method, $suffix] = $this->httpShapeFor($scenario);
        $operationEndpoint = $suffix === '' ? $endpoint : rtrim($endpoint, '/').'/'.$suffix;

        return new OperationDescriptor(
            scenario: $scenario,
            method: $method,
            endpoint: $operationEndpoint,
            description: "Operation inferred for '{$scenario}' scenario.",
            validation: $validation,
            capabilities: $this->capabilityFor($scenario),
            request: [
                'content_type' => in_array($method, ['POST', 'PUT', 'PATCH'], true) ? 'application/json' : null,
            ],
            response: [
                'content_type' => 'application/json',
            ],
            source: $source,
        );
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function httpShapeFor(string $scenario): array
    {
        return match ($scenario) {
            'query', 'index', 'show' => ['GET', ''],
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
            query: in_array($scenario, ['query', 'index', 'show'], true),
            create: str_contains($scenario, 'create') || in_array($scenario, ['register'], true),
            update: str_contains($scenario, 'update') || in_array($scenario, ['change_password', 'reset_password'], true),
            delete: in_array($scenario, ['delete', 'destroy', 'delete_by_id'], true),
            restore: str_contains($scenario, 'restore'),
            forceDelete: str_contains($scenario, 'force_delete'),
            export: str_starts_with($scenario, 'export_'),
        );
    }
}
