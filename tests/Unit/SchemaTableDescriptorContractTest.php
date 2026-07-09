<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ronu\LaravelAgentProtocol\DTO\SchemaTableDescriptor;

/**
 * Contract test for {@see SchemaTableDescriptor}.
 *
 * SchemaTableDescriptor is the DTO that ties the whole Schema Discovery
 * subsystem together (the #2 highest-betweenness node). This test locks the
 * serialized key set, the default values and the immutable withOverrides()/
 * withClassification() semantics so an accidental change breaks the build
 * instead of silently breaking discovery, the exporters and the cache.
 */
final class SchemaTableDescriptorContractTest extends TestCase
{
    public function test_envelope_exposes_exactly_the_documented_keys(): void
    {
        $payload = (new SchemaTableDescriptor(name: 'users'))->toArray();

        self::assertSame([
            'name',
            'type',
            'schema',
            'module',
            'label',
            'description',
            'description_source',
            'expose',
            'cacheable',
            'reference_table',
            'resource',
            'lookup_field',
            'max_records',
            'row_estimate',
            'columns',
            'primary_key',
            'indexes',
            'foreign_keys',
            'meta',
        ], array_keys($payload));
    }

    public function test_default_values_are_stable(): void
    {
        $payload = (new SchemaTableDescriptor(name: 'users'))->toArray();

        self::assertSame('users', $payload['name']);
        self::assertSame('table', $payload['type']);
        self::assertNull($payload['schema']);
        self::assertNull($payload['module']);
        self::assertSame('inferred', $payload['description_source']);
        self::assertFalse($payload['expose']);
        self::assertFalse($payload['cacheable']);
        self::assertFalse($payload['reference_table']);
        self::assertNull($payload['max_records']);
        self::assertSame([], $payload['columns']);
        self::assertSame([], $payload['primary_key']);
        self::assertSame([], $payload['indexes']);
        self::assertSame([], $payload['foreign_keys']);
        self::assertSame([], $payload['meta']);
    }

    public function test_json_serialize_matches_to_array(): void
    {
        $descriptor = new SchemaTableDescriptor(name: 'users');

        self::assertSame($descriptor->toArray(), $descriptor->jsonSerialize());

        $roundTrip = json_decode(json_encode($descriptor, JSON_THROW_ON_ERROR), true);
        self::assertSame($descriptor->toArray(), $roundTrip);
    }

    public function test_with_overrides_maps_snake_case_keys_and_is_immutable(): void
    {
        $original = new SchemaTableDescriptor(name: 'users');

        $overridden = $original->withOverrides([
            'module' => 'security',
            'label' => 'Users',
            'description' => 'Application users.',
            'expose' => true,
            'reference_table' => true,
            'lookup_field' => 'name',
            'max_records' => 500,
            'primary_key' => ['id'],
        ]);

        // Original is untouched (readonly / immutable contract).
        self::assertNull($original->module);
        self::assertFalse($original->expose);

        // Overrides are applied and snake_case keys mapped to properties.
        self::assertSame('security', $overridden->module);
        self::assertSame('Users', $overridden->label);
        self::assertTrue($overridden->expose);
        self::assertTrue($overridden->referenceTable);
        self::assertSame('name', $overridden->lookupField);
        self::assertSame(500, $overridden->maxRecords);
        self::assertSame(['id'], $overridden->primaryKey);

        // Supplying a description flips the provenance to 'config'.
        self::assertSame('config', $overridden->descriptionSource);
    }

    public function test_with_classification_sets_cache_fields_and_is_immutable(): void
    {
        $original = new SchemaTableDescriptor(name: 'countries');

        $classified = $original->withClassification(
            cacheable: true,
            referenceTable: true,
            lookupField: 'iso_code',
            maxRecords: 300,
        );

        self::assertFalse($original->cacheable);

        self::assertTrue($classified->cacheable);
        self::assertTrue($classified->referenceTable);
        self::assertSame('iso_code', $classified->lookupField);
        self::assertSame(300, $classified->maxRecords);
        self::assertSame('countries', $classified->name);
    }
}
