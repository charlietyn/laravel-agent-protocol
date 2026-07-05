<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Ronu\LaravelAgentProtocol\Contracts\MetadataRepositoryContract;
use Ronu\LaravelAgentProtocol\Registry\DocumentationRegistry;
use Ronu\LaravelAgentProtocol\Registry\ModuleRegistry;
use Ronu\LaravelAgentProtocol\Registry\ResourceRegistry;
use Ronu\LaravelAgentProtocol\Registry\ScenarioRegistry;

final class AgentController extends Controller
{
    public function __construct(
        private readonly MetadataRepositoryContract $metadata,
        private readonly ModuleRegistry $modules,
        private readonly ResourceRegistry $resources,
        private readonly ScenarioRegistry $scenarios,
        private readonly DocumentationRegistry $documentation,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json($this->metadata->get()->toArray());
    }

    public function modules(): JsonResponse
    {
        return response()->json([
            'data' => array_map(fn ($module): array => $module->toArray(), $this->modules->all()),
        ]);
    }

    public function resources(): JsonResponse
    {
        return response()->json([
            'data' => array_map(fn ($resource): array => $resource->toArray(), $this->resources->all()),
        ]);
    }

    public function resource(string $resource): JsonResponse
    {
        $descriptor = $this->resources->find($resource);

        if (! $descriptor) {
            return $this->notFound('ADP_RESOURCE_NOT_FOUND', "Resource [{$resource}] was not found.");
        }

        return response()->json($descriptor->toArray());
    }

    public function operations(string $resource): JsonResponse
    {
        if (! $this->resources->find($resource)) {
            return $this->notFound('ADP_RESOURCE_NOT_FOUND', "Resource [{$resource}] was not found.");
        }

        return response()->json([
            'data' => array_map(fn ($operation): array => $operation->toArray(), $this->scenarios->forResource($resource)),
        ]);
    }

    public function operation(string $resource, string $scenario): JsonResponse
    {
        if (! $this->resources->find($resource)) {
            return $this->notFound('ADP_RESOURCE_NOT_FOUND', "Resource [{$resource}] was not found.");
        }

        $operation = $this->scenarios->find($resource, $scenario);
        if (! $operation) {
            return $this->notFound('ADP_OPERATION_NOT_FOUND', "Operation [{$scenario}] was not found for resource [{$resource}].");
        }

        return response()->json($operation->toArray());
    }

    public function filterDocumentation(): JsonResponse
    {
        return response()->json($this->documentation->filter()?->toArray() ?? []);
    }

    public function errorDocumentation(): JsonResponse
    {
        return response()->json($this->documentation->find('errors')?->toArray() ?? ['errors' => []]);
    }

    public function dictionary(): JsonResponse
    {
        return response()->json([
            'data' => $this->documentation->dictionary(),
        ]);
    }

    private function notFound(string $code, string $message): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ], 404);
    }
}
