<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Registry;

use Ronu\LaravelAgentProtocol\DTO\OperationDescriptor;

final readonly class ScenarioRegistry
{
    public function __construct(
        private ResourceRegistry $resources,
    ) {}

    /**
     * @return array<int, OperationDescriptor>
     */
    public function forResource(string $resource): array
    {
        return $this->resources->find($resource)?->operations ?? [];
    }

    public function find(string $resource, string $scenario): ?OperationDescriptor
    {
        return $this->resources->find($resource)?->operation($scenario);
    }
}
