<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ronu\LaravelAgentProtocol\DTO\SchemaColumnDescriptor;

/**
 * Contract test for {@see SchemaColumnDescriptor}.
 *
 * The per-column descriptor inside every SchemaTableDescriptor. This test
 * locks its serialized key set, defaults and the immutable withOverrides()
 * snake_case mapping so schema discovery keeps a stable column shape.
 */
final class SchemaColumnDescriptorContractTest extends TestCase
{
    public function test_envelope_exposes_exactly_the_documented_keys(): void
    {
        $payload = (new SchemaColumnDescriptor(name: 'email'))->toArray();

        self::assertSame([
            'name',
            'type',
            'native_type',
            'nullable',
            'default',
            'auto_increment',
            'primary',
            'unique',
            'indexed',
            'sensitive',
            'label',
            'description',
            'description_source',
            'references',
            'meta',
        ], array_keys($payload));
    }

    public function test_default_values_are_stable(): void
    {
        $payload = (new SchemaColumnDescriptor(name: 'email'))->toArray();

        self::assertSame('email', $payload['name']);
        self::assertSame('mixed', $payload['type']);
        self::assertNull($payload['native_type']);
        self::assertNull($payload['nullable']);
        self::assertNull($payload['default']);
        self::assertFalse($payload['auto_increment']);
        self::assertFalse($payload['primary']);
        self::assertFalse($payload['unique']);
        self::assertFalse($payload['indexed']);
        self::assertFalse($payload['sensitive']);
        self::assertSame('inferred', $payload['description_source']);
        self::assertSame([], $payload['references']);
        self::assertSame([], $payload['meta']);
    }

    public function test_with_overrides_maps_snake_case_keys_and_is_immutable(): void
    {
        $original = new SchemaColumnDescriptor(name: 'id');

        $overridden = $original->withOverrides([
            'type' => 'integer',
            'native_type' => 'bigint',
            'nullable' => false,
            'auto_increment' => true,
            'primary' => true,
            'description' => 'Primary key.',
        ]);

        // Original untouched.
        self::assertSame('mixed', $original->type);
        self::assertFalse($original->primary);

        // Overrides applied with snake_case -> property mapping.
        self::assertSame('integer', $overridden->type);
        self::assertSame('bigint', $overridden->nativeType);
        self::assertFalse($overridden->nullable);
        self::assertTrue($overridden->autoIncrement);
        self::assertTrue($overridden->primary);

        // Supplying a description flips provenance to 'config'.
        self::assertSame('config', $overridden->descriptionSource);
    }

    public function test_json_serialize_matches_to_array(): void
    {
        $column = new SchemaColumnDescriptor(name: 'email');

        self::assertSame($column->toArray(), $column->jsonSerialize());

        $roundTrip = json_decode(json_encode($column, JSON_THROW_ON_ERROR), true);
        self::assertSame($column->toArray(), $roundTrip);
    }
}
