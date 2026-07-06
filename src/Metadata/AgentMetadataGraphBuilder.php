<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Metadata;

use DateTimeImmutable;
use Ronu\LaravelAgentProtocol\DTO\AgentMetadataGraph;
use Ronu\LaravelAgentProtocol\DTO\DocumentationDescriptor;
use Ronu\LaravelAgentProtocol\DTO\FilterDescriptor;
use Ronu\LaravelAgentProtocol\DTO\ModuleDescriptor;
use Ronu\LaravelAgentProtocol\DTO\ResourceDescriptor;

final class AgentMetadataGraphBuilder
{
    /** @var array<string, ModuleDescriptor> */
    private array $modules = [];

    /** @var array<string, ResourceDescriptor> */
    private array $resources = [];

    /** @var array<string, DocumentationDescriptor> */
    private array $documentation = [];

    /** @var array<string, mixed> */
    private array $dictionary = [];

    private ?FilterDescriptor $filterDocumentation = null;

    public function __construct(
        private readonly string $protocolVersion,
        private readonly DateTimeImmutable $generatedAt = new DateTimeImmutable,
    ) {}

    public function addModule(ModuleDescriptor $module): void
    {
        $this->modules[$module->key] = $module;
    }

    public function addResource(ResourceDescriptor $resource): void
    {
        $this->resources[$resource->key] = isset($this->resources[$resource->key])
            ? $this->resources[$resource->key]->merge($resource)
            : $resource;

        $module = $this->modules[$resource->module] ?? new ModuleDescriptor(
            key: $resource->module,
            name: $resource->module,
        );

        $this->modules[$resource->module] = $module->withResource($resource->key);
    }

    public function resource(string $key): ?ResourceDescriptor
    {
        return $this->resources[$key] ?? null;
    }

    /**
     * @return array<int, ResourceDescriptor>
     */
    public function resources(): array
    {
        ksort($this->resources);

        return array_values($this->resources);
    }

    public function setFilterDocumentation(FilterDescriptor $filter): void
    {
        $this->filterDocumentation = $filter;
    }

    /**
     * @param  array<string, mixed>  $dictionary
     */
    public function mergeDictionary(array $dictionary): void
    {
        $this->dictionary = array_replace_recursive($this->dictionary, $dictionary);
    }

    public function addDocumentation(DocumentationDescriptor $documentation): void
    {
        $this->documentation[$documentation->slug] = $documentation;
    }

    public function build(): AgentMetadataGraph
    {
        ksort($this->modules);
        ksort($this->resources);
        ksort($this->documentation);
        ksort($this->dictionary);

        return new AgentMetadataGraph(
            protocolVersion: $this->protocolVersion,
            generatedAt: $this->generatedAt,
            modules: array_values($this->modules),
            resources: array_values($this->resources),
            filterDocumentation: $this->filterDocumentation,
            dictionary: $this->dictionary,
            documentation: array_values($this->documentation),
        );
    }
}
