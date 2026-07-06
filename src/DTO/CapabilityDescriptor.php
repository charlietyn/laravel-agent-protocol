<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\DTO;

use JsonSerializable;
use Ronu\LaravelAgentProtocol\DTO\Concerns\SerializesDescriptor;

final readonly class CapabilityDescriptor implements JsonSerializable
{
    use SerializesDescriptor;

    /**
     * @param  array<int, string>  $filters
     * @param  array<int, string>  $relations
     * @param  array<int, string>  $risks
     */
    public function __construct(
        public bool $query = false,
        public bool $create = false,
        public bool $update = false,
        public bool $bulkCreate = false,
        public bool $bulkUpdate = false,
        public bool $delete = false,
        public bool $restore = false,
        public bool $forceDelete = false,
        public bool $export = false,
        public bool $aggregate = false,
        public bool $hierarchy = false,
        public bool $softDeletes = false,
        public bool $permissioned = false,
        public array $filters = [],
        public array $relations = [],
        public array $risks = [],
    ) {}

    public function merge(self $other): self
    {
        return new self(
            query: $this->query || $other->query,
            create: $this->create || $other->create,
            update: $this->update || $other->update,
            bulkCreate: $this->bulkCreate || $other->bulkCreate,
            bulkUpdate: $this->bulkUpdate || $other->bulkUpdate,
            delete: $this->delete || $other->delete,
            restore: $this->restore || $other->restore,
            forceDelete: $this->forceDelete || $other->forceDelete,
            export: $this->export || $other->export,
            aggregate: $this->aggregate || $other->aggregate,
            hierarchy: $this->hierarchy || $other->hierarchy,
            softDeletes: $this->softDeletes || $other->softDeletes,
            permissioned: $this->permissioned || $other->permissioned,
            filters: array_values(array_unique([...$this->filters, ...$other->filters])),
            relations: array_values(array_unique([...$this->relations, ...$other->relations])),
            risks: array_values(array_unique([...$this->risks, ...$other->risks])),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->serializeValues([
            'query' => $this->query,
            'create' => $this->create,
            'update' => $this->update,
            'bulk_create' => $this->bulkCreate,
            'bulk_update' => $this->bulkUpdate,
            'delete' => $this->delete,
            'restore' => $this->restore,
            'force_delete' => $this->forceDelete,
            'export' => $this->export,
            'aggregate' => $this->aggregate,
            'hierarchy' => $this->hierarchy,
            'soft_deletes' => $this->softDeletes,
            'permissioned' => $this->permissioned,
            'filters' => $this->filters,
            'relations' => $this->relations,
            'risks' => $this->risks,
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
