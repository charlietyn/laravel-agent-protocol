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
        $validations = $this->validationInspector->inspect($requestClass);
        $model = $this->modelInspector->inspect($modelClass, $validations);
        $relations = $this->relationInspector->inspect($modelClass);
        $operations = $this->operationFactory->fromValidations($validations, $endpoint);

        foreach ((array) ($definition['operations'] ?? []) as $scenario => $operationDefinition) {
            if (! is_array($operationDefinition)) {
                continue;
            }

            $operations[] = $this->operationFactory->fromRoute(
                scenario: (string) $scenario,
                method: $this->stringOrDefault($operationDefinition['method'] ?? 'POST', 'POST'),
                endpoint: $this->stringOrDefault($operationDefinition['endpoint'] ?? $endpoint, $endpoint),
                validation: $validations[(string) $scenario] ?? null,
            );
        }

        $builder->addResource(new ResourceDescriptor(
            key: $key,
            module: $module,
            name: $name,
            model: $modelClass,
            table: $model['table'],
            primaryKey: $model['primary_key'],
            description: $this->stringOrNull($definition['description'] ?? null),
            fields: $model['fields'],
            relations: $relations,
            operations: $operations,
            capabilities: $this->capabilities($operations, $relations, $model['meta'], $context),
            meta: [
                ...$model['meta'],
                'source' => 'config',
                'request' => $requestClass,
                'controller' => $definition['controller'] ?? null,
                'service' => $definition['service'] ?? null,
            ],
        ));
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
        return [
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
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function stringOrDefault(mixed $value, string $default): string
    {
        return is_string($value) && $value !== '' ? $value : $default;
    }
}
