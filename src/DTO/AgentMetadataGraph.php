<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\DTO;

use DateTimeImmutable;
use JsonSerializable;
use Ronu\LaravelAgentProtocol\DTO\Concerns\SerializesDescriptor;

final readonly class AgentMetadataGraph implements JsonSerializable
{
    use SerializesDescriptor;

    /**
     * @param  array<int, ModuleDescriptor>  $modules
     * @param  array<int, ResourceDescriptor>  $resources
     * @param  array<string, mixed>  $dictionary
     * @param  array<int, DocumentationDescriptor>  $documentation
     */
    public function __construct(
        public string $protocolVersion,
        public DateTimeImmutable $generatedAt,
        public array $modules,
        public array $resources,
        public ?FilterDescriptor $filterDocumentation = null,
        public array $dictionary = [],
        public array $documentation = [],
    ) {}

    public function resource(string $key): ?ResourceDescriptor
    {
        foreach ($this->resources as $resource) {
            if ($resource->key === $key) {
                return $resource;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->serializeValues([
            'protocol' => 'adp',
            'protocol_version' => $this->protocolVersion,
            'generated_at' => $this->generatedAt->format(DATE_ATOM),
            'implementation' => [
                'package' => 'ronu/laravel-agent-protocol',
                'framework' => 'laravel',
                'integration' => 'ronu/rest-generic-class',
            ],
            'capabilities' => [
                'discovery',
                'query',
                'mutation_metadata',
                'validation',
                'dictionary',
                'filter_documentation',
                'risk_metadata',
                'readiness',
                'export',
            ],
            'links' => [
                'self' => '/agent',
                'modules' => '/agent/modules',
                'resources' => '/agent/resources',
                'dictionary' => '/agent/dictionary',
                'filter_documentation' => '/agent/documentation/filter',
                'error_documentation' => '/agent/documentation/errors',
            ],
            'modules' => $this->modules,
            'resources' => $this->resources,
            'documentation' => $this->documentation,
            'filter' => $this->filterDocumentation,
            'dictionary' => $this->dictionary,
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
