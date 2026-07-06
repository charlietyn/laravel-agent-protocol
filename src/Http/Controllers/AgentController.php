<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Ronu\LaravelAgentProtocol\Contracts\MetadataRepositoryContract;
use Ronu\LaravelAgentProtocol\DTO\AgentMetadataGraph;
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
        $graph = $this->metadata->get();

        return $this->metadataResponse($graph->toArray(), $graph);
    }

    public function bundle(Request $request): JsonResponse
    {
        if (! (bool) config('agent-protocol.bundle.enabled', true)) {
            return $this->notFound('ADP_BUNDLE_DISABLED', 'The ADP bundle endpoint is disabled.');
        }

        $graph = $this->metadata->get();
        $defaultMode = config('agent-protocol.bundle.default_mode', 'full');
        $mode = $request->query('mode', is_string($defaultMode) ? $defaultMode : 'full');
        $payload = $graph->toArray();

        if ($mode === 'slim') {
            $payload = $this->withoutInlineReferenceValues($payload);
            $payload['bundle'] = ['mode' => 'slim'];
        } else {
            $payload['bundle'] = ['mode' => 'full'];
        }

        return $this->metadataResponse($payload, $graph);
    }

    public function modules(): JsonResponse
    {
        return $this->metadataResponse([
            'data' => array_map(fn ($module): array => $module->toArray(), $this->modules->all()),
        ], $this->metadata->get());
    }

    public function resources(): JsonResponse
    {
        return $this->metadataResponse([
            'data' => array_map(fn ($resource): array => $resource->toArray(), $this->resources->all()),
        ], $this->metadata->get());
    }

    public function resource(string $resource): JsonResponse
    {
        $descriptor = $this->resources->find($resource);

        if (! $descriptor) {
            return $this->notFound('ADP_RESOURCE_NOT_FOUND', "Resource [{$resource}] was not found.");
        }

        return $this->metadataResponse($descriptor->toArray(), $this->metadata->get());
    }

    public function operations(string $resource): JsonResponse
    {
        if (! $this->resources->find($resource)) {
            return $this->notFound('ADP_RESOURCE_NOT_FOUND', "Resource [{$resource}] was not found.");
        }

        return $this->metadataResponse([
            'data' => array_map(fn ($operation): array => $operation->toArray(), $this->scenarios->forResource($resource)),
        ], $this->metadata->get());
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

        return $this->metadataResponse($operation->toArray(), $this->metadata->get());
    }

    public function filterDocumentation(): JsonResponse
    {
        return $this->metadataResponse($this->documentation->filter()?->toArray() ?? [], $this->metadata->get());
    }

    public function errorDocumentation(): JsonResponse
    {
        return $this->metadataResponse($this->documentation->find('errors')?->toArray() ?? ['errors' => []], $this->metadata->get());
    }

    public function dictionary(): JsonResponse
    {
        return $this->metadataResponse([
            'data' => $this->documentation->dictionary(),
        ], $this->metadata->get());
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

    /**
     * @param  array<string, mixed>  $payload
     */
    private function metadataResponse(array $payload, AgentMetadataGraph $graph): JsonResponse
    {
        $response = response()->json($payload);
        $encoded = (string) json_encode($payload);

        if ((bool) config('agent-protocol.cache.etag', true)) {
            $response->headers->set('ETag', '"'.sha1($encoded).'"');
        }

        if ((bool) config('agent-protocol.cache.last_modified', true)) {
            $response->headers->set('Last-Modified', $graph->generatedAt->format(DATE_RFC7231));
        }

        return $response;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function withoutInlineReferenceValues(array $payload): array
    {
        $resources = $payload['resources'] ?? [];
        if (! is_array($resources)) {
            return $payload;
        }

        foreach ($resources as $resourceIndex => $resource) {
            if (! is_array($resource)) {
                continue;
            }

            $fields = $resource['fields'] ?? [];
            if (! is_array($fields)) {
                continue;
            }

            foreach ($fields as $fieldIndex => $field) {
                if (! is_array($field) || ! isset($field['reference']) || ! is_array($field['reference'])) {
                    continue;
                }

                unset($field['reference']['inline_values']);
                $fields[$fieldIndex] = $field;
            }

            $resource['fields'] = $fields;
            $resources[$resourceIndex] = $resource;
        }

        $payload['resources'] = $resources;

        return $payload;
    }
}
