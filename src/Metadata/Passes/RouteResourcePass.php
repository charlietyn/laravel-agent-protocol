<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Metadata\Passes;

use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionNamedType;
use Ronu\LaravelAgentProtocol\Contracts\MetadataCompilerPass;
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

final readonly class RouteResourcePass implements MetadataCompilerPass
{
    public function __construct(
        private ModelInspector $modelInspector,
        private RelationInspector $relationInspector,
        private ValidationInspector $validationInspector,
        private OperationFactory $operationFactory,
        private ?ReadinessScorer $readinessScorer = null,
    ) {}

    public function compile(MetadataBuildContext $context, AgentMetadataGraphBuilder $builder): void
    {
        if (! $context->config('agent-protocol.discovery.routes', true)) {
            return;
        }

        try {
            /** @var Router $router */
            $router = $context->container->make('router');
        } catch (\Throwable) {
            return;
        }

        /**
         * @var array<string, array{
         *     module: string,
         *     name: string,
         *     model: string,
         *     controller: string,
         *     request: string|null,
         *     operations: array<int, array{scenario: string, method: string, endpoint: string, middleware: array<int, string>, permissions: array<int, string>}>
         * }> $grouped
         */
        $grouped = [];

        foreach ($router->getRoutes()->getRoutes() as $route) {
            if (! $route instanceof Route) {
                continue;
            }

            $routeMeta = $this->routeMeta($context, $route);
            if ($routeMeta === null) {
                continue;
            }

            $grouped[$routeMeta['key']]['module'] = $routeMeta['module'];
            $grouped[$routeMeta['key']]['name'] = $routeMeta['name'];
            $grouped[$routeMeta['key']]['model'] = $routeMeta['model'];
            $grouped[$routeMeta['key']]['controller'] = $routeMeta['controller'];
            $grouped[$routeMeta['key']]['request'] ??= $routeMeta['request'];
            $grouped[$routeMeta['key']]['operations'][] = $routeMeta['operation'];
        }

        foreach ($grouped as $key => $definition) {
            $modelClass = $definition['model'];
            $requestClass = $definition['request'];
            $visibility = $this->visibility($context);
            $validations = $this->validationInspector->inspect($requestClass);
            $model = $this->modelInspector->inspect($modelClass, $validations, $visibility);
            $relations = $this->relationInspector->inspect($modelClass, $this->maxDepth($context));
            $operations = [];
            $security = $this->security($context, $definition['operations']);

            foreach ($definition['operations'] as $operation) {
                $operations[] = $this->operationFactory->fromRoute(
                    scenario: $operation['scenario'],
                    method: $operation['method'],
                    endpoint: $operation['endpoint'],
                    validation: $validations[$operation['scenario']] ?? null,
                    security: [
                        ...$security,
                        'middleware' => $operation['middleware'],
                        'permissions' => $operation['permissions'],
                    ],
                );
            }

            $resource = new ResourceDescriptor(
                key: (string) $key,
                module: (string) $definition['module'],
                name: (string) $definition['name'],
                endpoint: $this->baseEndpoint($definition['operations']),
                model: $modelClass,
                table: $model['table'],
                primaryKey: $model['primary_key'],
                description: 'Resource discovered from Laravel routes.',
                fields: $model['fields'],
                relations: $relations,
                operations: $operations,
                capabilities: $this->capabilities($operations, $relations, $model['meta']),
                filters: $this->filterParameters(),
                security: $security,
                visibility: $visibility,
                meta: [
                    ...$model['meta'],
                    'source' => 'routes',
                    'controller' => $definition['controller'],
                    'request' => $requestClass,
                ],
            );

            $builder->addResource($resource->withReadiness($this->scorer()->score($resource)));
        }
    }

    /**
     * @return array{
     *     key: string,
     *     module: string,
     *     name: string,
     *     model: string,
     *     controller: string,
     *     request: string|null,
     *     operation: array{scenario: string, method: string, endpoint: string, middleware: array<int, string>, permissions: array<int, string>}
     * }|null
     */
    private function routeMeta(MetadataBuildContext $context, Route $route): ?array
    {
        $controllerAction = $route->getAction('controller');
        if (! is_string($controllerAction) || ! str_contains($controllerAction, '@')) {
            return null;
        }

        [$controllerClass, $method] = explode('@', $controllerAction, 2);
        if (! class_exists($controllerClass) || ! $this->isDiscoverableController($context, $controllerClass)) {
            return null;
        }

        /** @var class-string $controllerClass */
        $modelClass = $this->defaultProperty($controllerClass, 'modelClass');
        if (! is_string($modelClass) || $modelClass === '' || ! class_exists($modelClass)) {
            return null;
        }

        $module = $this->moduleFromRoute($context, $route);
        $name = ResourceKey::nameFromModel($modelClass);
        $key = ResourceKey::fromModel($module, $modelClass, $name);
        $requestClass = $this->requestClass($controllerClass, $method);
        $methodVerb = $this->primaryVerb($route->methods());
        $middleware = $this->stringList($route->gatherMiddleware());

        return [
            'key' => $key,
            'module' => $module,
            'name' => $name,
            'model' => $modelClass,
            'controller' => $controllerClass,
            'request' => $requestClass,
            'operation' => [
                'scenario' => $this->scenarioFromControllerMethod($method),
                'method' => $methodVerb,
                'endpoint' => '/'.ltrim($route->uri(), '/'),
                'middleware' => $middleware,
                'permissions' => $this->permissionsFromMiddleware($middleware),
            ],
        ];
    }

    private function isDiscoverableController(MetadataBuildContext $context, string $controllerClass): bool
    {
        $parents = (array) $context->config('agent-protocol.discovery.controllers_extending', []);
        if ($parents === []) {
            return true;
        }

        foreach ($parents as $parent) {
            if (is_string($parent) && class_exists($parent) && is_subclass_of($controllerClass, $parent)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  class-string  $class
     */
    private function defaultProperty(string $class, string $property): mixed
    {
        try {
            $reflection = new ReflectionClass($class);
            $defaults = $reflection->getDefaultProperties();

            return $defaults[$property] ?? null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  class-string  $controllerClass
     */
    private function requestClass(string $controllerClass, string $method): ?string
    {
        try {
            $reflection = new ReflectionClass($controllerClass);
            if (! $reflection->hasMethod($method)) {
                return null;
            }

            foreach ($reflection->getMethod($method)->getParameters() as $parameter) {
                $type = $parameter->getType();
                if (! $type instanceof ReflectionNamedType || $type->isBuiltin()) {
                    continue;
                }

                $class = $type->getName();
                if (class_exists($class) && is_subclass_of($class, 'Illuminate\\Foundation\\Http\\FormRequest')) {
                    return $class;
                }
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    private function moduleFromRoute(MetadataBuildContext $context, Route $route): string
    {
        $segments = array_values(array_filter(explode('/', $route->uri())));
        $prefixes = (array) $context->config('agent-protocol.discovery.route_prefixes', ['api']);
        $configuredDefault = $context->config('agent-protocol.discovery.default_module', '--site--');
        $default = is_string($configuredDefault) && $configuredDefault !== '' ? $configuredDefault : '--site--';

        if ($segments === []) {
            return $default;
        }

        $first = $segments[0];
        if (in_array($first, $prefixes, true) && isset($segments[1])) {
            return $segments[1];
        }

        return $default;
    }

    /**
     * @param  array<int, string>  $methods
     */
    private function primaryVerb(array $methods): string
    {
        foreach ($methods as $method) {
            if ($method !== 'HEAD') {
                return $method;
            }
        }

        return 'GET';
    }

    private function scenarioFromControllerMethod(string $method): string
    {
        return match ($method) {
            'index' => 'query',
            'getOne' => 'query_one',
            'show' => 'show',
            'store' => 'create',
            'update' => 'update',
            'updateMultiple' => 'bulk_update',
            'destroy' => 'delete',
            'deleteById' => 'delete_by_id',
            'restore' => 'restore',
            'restoreMultiple' => 'restore_multiple',
            'forceDelete' => 'force_delete',
            'forceDeleteMultiple' => 'force_delete_multiple',
            'export_excel' => 'export_excel',
            'export_pdf' => 'export_pdf',
            'actionValidate' => 'validate',
            default => Str::of($method)->snake()->toString(),
        };
    }

    /**
     * @param  array<int, OperationDescriptor>  $operations
     * @param  array<int, RelationDescriptor>  $relations
     * @param  array<string, mixed>  $modelMeta
     */
    private function capabilities(array $operations, array $relations, array $modelMeta): CapabilityDescriptor
    {
        $capabilities = new CapabilityDescriptor(
            filters: ['eq', 'attr', 'oper', 'relations', 'select', 'pagination', 'orderby', 'hierarchy', '_nested'],
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

        return $capabilities;
    }

    /**
     * @param  array<int, array{scenario: string, method: string, endpoint: string, middleware: array<int, string>, permissions: array<int, string>}>  $operations
     */
    private function baseEndpoint(array $operations): ?string
    {
        $first = $operations[0]['endpoint'] ?? null;

        if (! is_string($first) || $first === '') {
            return null;
        }

        return preg_replace('#/\{[^}]+\}$#', '', $first) ?: $first;
    }

    /**
     * @return array<int, string>
     */
    private function filterParameters(): array
    {
        return ['eq', 'attr', 'oper', 'relations', 'select', 'pagination', 'orderby', 'hierarchy', 'soft_delete', '_nested'];
    }

    /**
     * @return array<string, mixed>
     */
    private function visibility(MetadataBuildContext $context): array
    {
        return [
            'redact_sensitive_fields' => (bool) $context->config('agent-protocol.security.redact_sensitive_fields', true),
            'expose_sensitive_fields' => (bool) $context->config('agent-protocol.security.expose_sensitive_fields', false),
            'sensitive_fields' => $this->stringList($context->config('agent-protocol.security.sensitive_fields', [])),
            'hidden_fields' => $this->stringList($context->config('agent-protocol.security.hidden_fields', [])),
            'public_fields' => $this->stringList($context->config('agent-protocol.security.public_fields', [])),
        ];
    }

    /**
     * @param  array<int, array{scenario: string, method: string, endpoint: string, middleware: array<int, string>, permissions: array<int, string>}>  $operations
     * @return array<string, mixed>
     */
    private function security(MetadataBuildContext $context, array $operations): array
    {
        $middleware = [];
        $permissions = [];

        foreach ($operations as $operation) {
            $middleware = [...$middleware, ...$operation['middleware']];
            $permissions = [...$permissions, ...$operation['permissions']];
        }

        return [
            'middleware' => array_values(array_unique($middleware)),
            'guards' => [],
            'permissions' => array_values(array_unique($permissions)),
            'tenant_header' => $context->config('agent-protocol.security.tenant_header', 'X-Tenant-Id'),
            'locale_header' => $context->config('agent-protocol.security.locale_header', 'Accept-Language'),
            'tenant_aware' => false,
            'locale_aware' => false,
            'redact_sensitive_fields' => (bool) $context->config('agent-protocol.security.redact_sensitive_fields', true),
            'expose_sensitive_fields' => (bool) $context->config('agent-protocol.security.expose_sensitive_fields', false),
        ];
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
     * @param  array<int, string>  $middleware
     * @return array<int, string>
     */
    private function permissionsFromMiddleware(array $middleware): array
    {
        $permissions = [];

        foreach ($middleware as $entry) {
            if (! str_contains($entry, ':')) {
                continue;
            }

            [$name, $arguments] = explode(':', $entry, 2);
            if (! in_array($name, ['can', 'permission', 'permissions'], true)) {
                continue;
            }

            foreach (explode(',', $arguments) as $permission) {
                $permission = trim($permission);
                if ($permission !== '') {
                    $permissions[] = $permission;
                }
            }
        }

        return array_values(array_unique($permissions));
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

    private function scorer(): ReadinessScorer
    {
        return $this->readinessScorer ?? new ReadinessScorer;
    }
}
