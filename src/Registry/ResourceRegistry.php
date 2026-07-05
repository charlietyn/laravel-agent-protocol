<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Registry;

use Ronu\LaravelAgentProtocol\Contracts\MetadataRepositoryContract;
use Ronu\LaravelAgentProtocol\DTO\ResourceDescriptor;

final readonly class ResourceRegistry
{
    public function __construct(
        private MetadataRepositoryContract $repository,
    ) {}

    /**
     * @return array<int, ResourceDescriptor>
     */
    public function all(): array
    {
        return $this->repository->get()->resources;
    }

    public function find(string $key): ?ResourceDescriptor
    {
        return $this->repository->get()->resource($key);
    }
}
