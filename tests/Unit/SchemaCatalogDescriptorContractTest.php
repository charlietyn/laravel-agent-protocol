<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Ronu\LaravelAgentProtocol\DTO\SchemaCatalogDescriptor;
use Ronu\LaravelAgentProtocol\DTO\SchemaTableDescriptor;

/**
 * Contract test for {@see SchemaCatalogDescriptor}.
 *
 * SchemaCatalogDescriptor is a top-5 god node (33 edges) that aggregates every
 * SchemaTableDescriptor and is the root object of Schema Discovery — yet it had
 * zero test coverage. This test locks the serialized envelope, the computed
 * summary counts and the four table-filtering helpers so discovery, the
 * exporters and the cache cannot drift silently.
 */
final class SchemaCatalogDescriptorContractTest extends TestCase
{
    private function makeCatalog(): SchemaCatalogDescriptor
    {
        return new SchemaCatalogDescriptor(
            connection: 'mysql',
            driver: 'mysql',
            database: 'app',
            generatedAt: new DateTimeImmutable('2026-07-05T00:00:00+00:00'),
            tables: [
                new SchemaTableDescriptor(name: 'users'),
                new SchemaTableDescriptor(name: 'active_users_view', type: 'view'),
                new SchemaTableDescriptor(name: 'countries', cacheable: true),
                new SchemaTableDescriptor(name: 'currencies', cacheable: true, referenceTable: true),
            ],
        );
    }

    public function test_envelope_exposes_exactly_the_documented_keys(): void
    {
        $payload = $this->makeCatalog()->toArray();

        self::assertSame([
            'connection',
            'driver',
            'database',
            'generated_at',
            'tables',
            'summary',
            'meta',
        ], array_keys($payload));
    }

    public function test_top_level_values_are_stable(): void
    {
        $payload = $this->makeCatalog()->toArray();

        self::assertSame('mysql', $payload['connection']);
        self::assertSame('mysql', $payload['driver']);
        self::assertSame('app', $payload['database']);
        self::assertSame('2026-07-05T00:00:00+00:00', $payload['generated_at']);
        self::assertSame([], $payload['meta']);
    }

    public function test_tables_serialize_as_nested_descriptors(): void
    {
        $payload = $this->makeCatalog()->toArray();

        self::assertCount(4, $payload['tables']);
        self::assertSame('users', $payload['tables'][0]['name']);
        self::assertSame('view', $payload['tables'][1]['type']);
        self::assertTrue($payload['tables'][2]['cacheable']);
    }

    public function test_summary_counts_are_computed_correctly(): void
    {
        $payload = $this->makeCatalog()->toArray();

        self::assertSame([
            'tables' => 3,            // base tables (type=table): users, countries, currencies
            'views' => 1,             // active_users_view
            'cacheable_tables' => 2,  // countries, currencies
            'reference_tables' => 1,  // currencies
        ], $payload['summary']);
    }

    public function test_filter_helpers_partition_tables_by_classification(): void
    {
        $catalog = $this->makeCatalog();

        self::assertSame(['users', 'countries', 'currencies'], array_map(
            fn (SchemaTableDescriptor $t): string => $t->name,
            $catalog->baseTables(),
        ));
        self::assertSame(['active_users_view'], array_map(
            fn (SchemaTableDescriptor $t): string => $t->name,
            $catalog->views(),
        ));
        self::assertSame(['countries', 'currencies'], array_map(
            fn (SchemaTableDescriptor $t): string => $t->name,
            $catalog->cacheableTables(),
        ));
        self::assertSame(['currencies'], array_map(
            fn (SchemaTableDescriptor $t): string => $t->name,
            $catalog->referenceTables(),
        ));
    }

    public function test_json_serialize_matches_to_array(): void
    {
        $catalog = $this->makeCatalog();

        self::assertSame($catalog->toArray(), $catalog->jsonSerialize());

        $roundTrip = json_decode(json_encode($catalog, JSON_THROW_ON_ERROR), true);
        self::assertSame($catalog->toArray(), $roundTrip);
    }

    public function test_nullable_database_is_preserved(): void
    {
        $catalog = new SchemaCatalogDescriptor(
            connection: 'sqlite',
            driver: 'sqlite',
            database: null,
            generatedAt: new DateTimeImmutable('2026-07-05T00:00:00+00:00'),
        );

        self::assertNull($catalog->toArray()['database']);
    }
}
