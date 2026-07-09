<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ronu\LaravelAgentProtocol\DTO\FilterDescriptor;

/**
 * Contract test for {@see FilterDescriptor}.
 *
 * FilterDescriptor documents the query-filter grammar exposed at
 * /agent/documentation/filter. This test locks its serialized key set, the
 * safe-by-default validation flags and the camelCase->snake_case mapping so
 * the documented filtering contract cannot drift.
 */
final class FilterDescriptorContractTest extends TestCase
{
    private function makeFilter(): FilterDescriptor
    {
        return new FilterDescriptor(
            operators: ['eq', 'like', 'in'],
            parameters: ['field' => 'string', 'value' => 'mixed'],
            conditionFormat: 'field:operator:value',
        );
    }

    public function test_envelope_exposes_exactly_the_documented_keys(): void
    {
        $payload = $this->makeFilter()->toArray();

        self::assertSame([
            'operators',
            'parameters',
            'condition_format',
            'examples',
            'limits',
            'strict_relations',
            'validate_columns',
            'strict_column_validation',
        ], array_keys($payload));
    }

    public function test_validation_flags_default_to_safe_by_default(): void
    {
        $payload = $this->makeFilter()->toArray();

        self::assertSame(['eq', 'like', 'in'], $payload['operators']);
        self::assertSame('field:operator:value', $payload['condition_format']);
        self::assertSame([], $payload['examples']);
        self::assertSame([], $payload['limits']);
        self::assertTrue($payload['strict_relations']);
        self::assertTrue($payload['validate_columns']);
        self::assertTrue($payload['strict_column_validation']);
    }

    public function test_validation_flags_can_be_relaxed(): void
    {
        $filter = new FilterDescriptor(
            operators: ['eq'],
            parameters: [],
            conditionFormat: 'field:operator:value',
            strictRelations: false,
            validateColumns: false,
            strictColumnValidation: false,
        );

        $payload = $filter->toArray();

        self::assertFalse($payload['strict_relations']);
        self::assertFalse($payload['validate_columns']);
        self::assertFalse($payload['strict_column_validation']);
    }

    public function test_json_serialize_matches_to_array(): void
    {
        $filter = $this->makeFilter();

        self::assertSame($filter->toArray(), $filter->jsonSerialize());

        $roundTrip = json_decode(json_encode($filter, JSON_THROW_ON_ERROR), true);
        self::assertSame($filter->toArray(), $roundTrip);
    }
}
