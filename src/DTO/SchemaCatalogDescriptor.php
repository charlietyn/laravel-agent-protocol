<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\DTO;

use DateTimeImmutable;
use JsonSerializable;
use Ronu\LaravelAgentProtocol\DTO\Concerns\SerializesDescriptor;

final readonly class SchemaCatalogDescriptor implements JsonSerializable
{
    use SerializesDescriptor;

    /**
     * @param  array<int, SchemaTableDescriptor>  $tables
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public string $connection,
        public string $driver,
        public ?string $database,
        public DateTimeImmutable $generatedAt,
        public array $tables = [],
        public array $meta = [],
    ) {}

    /**
     * @return array<int, SchemaTableDescriptor>
     */
    public function views(): array
    {
        return array_values(array_filter($this->tables, fn (SchemaTableDescriptor $table): bool => $table->type === 'view'));
    }

    /**
     * @return array<int, SchemaTableDescriptor>
     */
    public function baseTables(): array
    {
        return array_values(array_filter($this->tables, fn (SchemaTableDescriptor $table): bool => $table->type === 'table'));
    }

    /**
     * @return array<int, SchemaTableDescriptor>
     */
    public function cacheableTables(): array
    {
        return array_values(array_filter($this->tables, fn (SchemaTableDescriptor $table): bool => $table->cacheable));
    }

    /**
     * @return array<int, SchemaTableDescriptor>
     */
    public function referenceTables(): array
    {
        return array_values(array_filter($this->tables, fn (SchemaTableDescriptor $table): bool => $table->referenceTable));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->serializeValues([
            'connection' => $this->connection,
            'driver' => $this->driver,
            'database' => $this->database,
            'generated_at' => $this->generatedAt->format(DATE_ATOM),
            'tables' => $this->tables,
            'summary' => [
                'tables' => count($this->baseTables()),
                'views' => count($this->views()),
                'cacheable_tables' => count($this->cacheableTables()),
                'reference_tables' => count($this->referenceTables()),
            ],
            'meta' => $this->meta,
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
