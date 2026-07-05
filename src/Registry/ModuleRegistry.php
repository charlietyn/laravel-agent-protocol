<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Registry;

use Ronu\LaravelAgentProtocol\Contracts\MetadataRepositoryContract;
use Ronu\LaravelAgentProtocol\DTO\ModuleDescriptor;

final readonly class ModuleRegistry
{
    public function __construct(
        private MetadataRepositoryContract $repository,
    ) {}

    /**
     * @return array<int, ModuleDescriptor>
     */
    public function all(): array
    {
        return $this->repository->get()->modules;
    }
}
