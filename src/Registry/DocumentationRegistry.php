<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Registry;

use Ronu\LaravelAgentProtocol\Contracts\MetadataRepositoryContract;
use Ronu\LaravelAgentProtocol\DTO\DocumentationDescriptor;
use Ronu\LaravelAgentProtocol\DTO\FilterDescriptor;

final readonly class DocumentationRegistry
{
    public function __construct(
        private MetadataRepositoryContract $repository,
    ) {}

    public function filter(): ?FilterDescriptor
    {
        return $this->repository->get()->filterDocumentation;
    }

    /**
     * @return array<int, DocumentationDescriptor>
     */
    public function all(): array
    {
        return $this->repository->get()->documentation;
    }

    public function find(string $slug): ?DocumentationDescriptor
    {
        foreach ($this->all() as $document) {
            if ($document->slug === $slug) {
                return $document;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function dictionary(): array
    {
        return $this->repository->get()->dictionary;
    }
}
