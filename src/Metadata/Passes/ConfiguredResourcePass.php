<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Metadata\Passes;

use Ronu\LaravelAgentProtocol\Contracts\MetadataCompilerPass;
use Ronu\LaravelAgentProtocol\Contracts\ResourceProvider;
use Ronu\LaravelAgentProtocol\DTO\CapabilityDescriptor;
use Ronu\LaravelAgentProtocol\DTO\OperationDescriptor;
use Ronu\LaravelAgentProtocol\DTO\RelationDescriptor;
use Ronu\LaravelAgentProtocol\DTO\ResourceDescriptor;
use Ronu\LaravelAgentProtocol\Metadata\AgentMetadataGraphBuilder;
use Ronu\LaravelAgentProtocol\Metadata\Introspection\ModelInspector;
use Ronu\LaravelAgentProtocol\Metadata\Introspection\RelationInspector;
use Ronu\LaravelAgentProtocol\Metadata\Introspection\ValidationInspector;
use Ronu\LaravelAgentProtocol\Metadata\MetadataBuildContext;
use Ronu\LaravelAgentProtocol\Metadata\OperationFactory;
use Ronu\LaravelAgentProtocol\Metadata\Readiness\ReadinessScorer;
use Ronu\LaravelAgentProtocol\Support\ResourceKey;

final readonly class ConfiguredResourcePass implements MetadataCompilerPass
{
    /**
     * @param  iterable<ResourceProvider>  $providers
     */
    public function __construct(
        private iterable $providers,
        private ModelInspector $modelInspector,
        private RelationInspector $relationInspector,
        private ValidationInspector $validationInspector,
        private OperationFactory $operationFactory,
        private ?ReadinessScorer $readinessScorer = null,
    ) {}

    public function compile(MetadataBuildContext $context, AgentMetadataGraphBuilder $builder): void
    {
        foreach ($this->providers($context) as $provider) {
            foreach ($provider->resources() as $key => $definition) {
                if (! is_array($definition)) {
                    continue;
                }

                $this->compileResource($context, $builder, (string) $key, $definition);
            }
        }
    }

    /**
     * @return iterable<ResourceProvider>
     */
    private function providers(MetadataBuildContext $context): iterable
    {
        yield from $this->providers;

        foreach ((array) $context->config('agent-protocol.providers.resources', []) as $providerClass) {
            if (! is_string($providerClass) || ! class_exists($providerClass)) {
                continue;
            }

            $provider = $context->make($providerClass);
            if ($provider instanceof ResourceProvider) {
                yield $provider;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function compileResource(
        MetadataBuildContext $context,
        AgentMetadataGraphBuilder $builder,
        string $configuredKey,
        array $definition,
    ): void {
        $modelClass = $this->stringOrNull($definition['model'] ?? null);
        $requestClass = $this->stringOrNull($definition['request'] ?? null);
        $defaultModule = $context->config('agent-protocol.discovery.default_module', '--site--');
        $module = $this->stringOrDefault($definition['module'] ?? $defaultModule, '--site--');
        $name = $this->stringOrDefault($definition['name'] ?? ResourceKey::nameFromModel($modelClass, $configuredKey), $configuredKey);
        $key = $configuredKey !== '' && ! is_numeric($configuredKey)
            ? $configuredKey
            : ResourceKey::fromModel($module, $modelClass, $name);
        $endpoint = $this->stringOrDefault($definition['endpoint'] ?? ('/'.str_replace('.', '/', $key)), '/'.str_replace('.', '/', $key));
        $visibility = $this->visibility($context, $definition);
        $security = $this->security($context, $definition, $visibility);
        $operationDefinitions = $this->operationDefinitions($definition['operations'] ?? []);
        $validations = $this->validationInspector->inspect($requestClass);
        $model = $this->modelInspector->inspect($modelClass, $validations, $visibility);
        $relations = $this->relationInspector->inspect($modelClass, $this->maxDepth($context));
        $operations = $this->operationFactory->fromValidations(
            validations: $validations,
            endpoint: $endpoint,
            overrides: $operationDefinitions,
            security: $security,
        );
        $definedScenarios = array_fill_keys(array_map(fn (OperationDescriptor $operation): string => $operation->scenario, $operations), true);

        foreach ($operationDefinitions as $scenario => $operationDefinition) {
            if (isset($definedScenarios[$scenario])) {
                continue;
            }

            if (! is_array($operationDefinition)) {
                continue;
            }

            $operations[] = $this->operationFactory->fromRoute(
                scenario: (string) $scenario,
                method: $this->stringOrDefault($operationDefinition['method'] ?? 'POST', 'POST'),
                endpoint: $this->stringOrDefault($operationDefinition['endpoint'] ?? $endpoint, $endpoint),
                validation: $validations[(string) $scenario] ?? null,
                security: $security,
                overrides: $operationDefinition,
            );
        }

        $resource = new ResourceDescriptor(
            key: $key,
            module: $module,
            name: $name,
            endpoint: $endpoint,
            model: $modelClass,
            table: $model['table'],
            primaryKey: $model['primary_key'],
            description: $this->stringOrNull($definition['description'] ?? null),
            fields: $model['fields'],
            relations: $relations,
            operations: $operations,
            capabilities: $this->capabilities($operations, $relations, $model['meta'], $context),
            filters: $this->filterParameters($context),
            security: $security,
            visibility: $visibility,
            examples: $this->arrayList($definition['examples'] ?? []),
            meta: [
                ...$model['meta'],
                'source' => 'config',
                'request' => $requestClass,
                'controller' => $definition['controller'] ?? null,
                'service' => $definition['service'] ?? null,
            ],
        );

        $builder->addResource($resource->withReadiness($this->scorer()->score($resource)));
    }

    /**
     * @param  array<int, OperationDescriptor>  $operations
     * @param  array<int, RelationDescriptor>  $relations
     * @param  array<string, mixed>  $modelMeta
     */
    private function capabilities(
        array $operations,
        array $relations,
        array $modelMeta,
        MetadataBuildContext $context,
    ): CapabilityDescriptor {
        $capabilities = new CapabilityDescriptor(
            filters: $this->filterParameters($context),
            relations: array_map(fn ($relation): string => $relation->name, $relations),
            hierarchy: isset($modelMeta['hierarchy_field']) && $modelMeta['hierarchy_field'] !== null,
            softDeletes: isset($modelMeta['soft_delete_column']) && $modelMeta['soft_delete_column'] !== null,
            permissioned: $this->hasPermissionedOperation($operations),
            risks: $this->risks($operations),
        );

        foreach ($operations as $operation) {
            if ($operation->capabilities) {
                $capabilities = $capabilities->merge($operation->capabilities);
            }
        }

        if (! empty($relations)) {
            $capabilities = $capabilities->merge(new CapabilityDescriptor(
                filters: [],
                relations: array_map(fn ($relation): string => $relation->name, $relations),
            ));
        }

        return $capabilities;
    }

    /**
     * @return array<int, string>
     */
    private function filterParameters(MetadataBuildContext $context): array
    {
        $parameters = [
            'eq',
            'attr',
            'oper',
            'relations',
            'select',
            'pagination',
            'orderby',
            'hierarchy',
            'soft_delete',
            '_nested',
        ];

        return array_values(array_unique(array_filter($parameters, is_string(...))));
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function stringOrDefault(mixed $value, string $default): string
    {
        return is_string($value) && $value !== '' ? $value : $default;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    private function visibility(MetadataBuildContext $context, array $definition): array
    {
        return [
            'redact_sensitive_fields' => (bool) $context->config('agent-protocol.security.redact_sensitive_fields', true),
            'expose_sensitive_fields' => (bool) ($definition['expose_sensitive_fields']
                ?? $context->config('agent-protocol.security.expose_sensitive_fields', false)),
            'sensitive_fields' => array_values(array_unique([
                ...$this->stringList($context->config('agent-protocol.security.sensitive_fields', [])),
                ...$this->stringList($definition['sensitive_fields'] ?? []),
            ])),
            'hidden_fields' => array_values(array_unique([
                ...$this->stringList($context->config('agent-protocol.security.hidden_fields', [])),
                ...$this->stringList($definition['hidden_fields'] ?? []),
            ])),
            'public_fields' => array_values(array_unique([
                ...$this->stringList($context->config('agent-protocol.security.public_fields', [])),
                ...$this->stringList($definition['public_fields'] ?? []),
            ])),
        ];
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, mixed>  $visibility
     * @return array<string, mixed>
     */
    private function security(MetadataBuildContext $context, array $definition, array $visibility): array
    {
        return [
            'middleware' => $this->stringList($definition['middleware'] ?? $context->config('agent-protocol.routes.middleware', ['api'])),
            'guards' => $this->stringList($definition['guards'] ?? []),
            'permissions' => $this->stringList($definition['permissions'] ?? []),
            'tenant_header' => $this->stringOrNull($definition['tenant_header'] ?? $context->config('agent-protocol.security.tenant_header')),
            'locale_header' => $this->stringOrNull($definition['locale_header'] ?? $context->config('agent-protocol.security.locale_header')),
            'tenant_aware' => (bool) ($definition['tenant_aware'] ?? false),
            'locale_aware' => (bool) ($definition['locale_aware'] ?? false),
            'redact_sensitive_fields' => (bool) ($visibility['redact_sensitive_fields'] ?? true),
            'expose_sensitive_fields' => (bool) ($visibility['expose_sensitive_fields'] ?? false),
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function operationDefinitions(mixed $operations): array
    {
        if (! is_array($operations)) {
            return [];
        }

        $definitions = [];
        foreach ($operations as $scenario => $definition) {
            if (is_string($scenario) && is_array($definition)) {
                $definitions[$scenario] = $definition;
            }
        }

        return $definitions;
    }

    private function maxDepth(MetadataBuildContext $context): int
    {
        $depth = $context->config('agent-protocol.limits.max_depth')
            ?? $context->config('rest-generic-class.filtering.max_depth', 5);

        return is_numeric($depth) ? (int) $depth : 5;
    }

    /**
     * @param  array<int, OperationDescriptor>  $operations
     */
    private function hasPermissionedOperation(array $operations): bool
    {
        foreach ($operations as $operation) {
            if ($operation->permissions !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, OperationDescriptor>  $operations
     * @return array<int, string>
     */
    private function risks(array $operations): array
    {
        return array_values(array_unique(array_map(fn (OperationDescriptor $operation): string => $operation->risk, $operations)));
    }

    /**
     * @return array<int, string>
     */
    private function stringList(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        return array_values(array_filter($values, is_string(...)));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function arrayList(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        return array_values(array_filter($values, is_array(...)));
    }

    private function scorer(): ReadinessScorer
    {
        return $this->readinessScorer ?? new ReadinessScorer;
    }
}
