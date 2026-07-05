<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Registry;

use Ronu\LaravelAgentProtocol\DTO\CapabilityDescriptor;

final readonly class CapabilityRegistry
{
    public function __construct(
        private ResourceRegistry $resources,
    ) {}

    public function forResource(string $resource): ?CapabilityDescriptor
    {
        return $this->resources->find($resource)?->capabilities;
    }
}
