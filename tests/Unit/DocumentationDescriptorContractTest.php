<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ronu\LaravelAgentProtocol\DTO\DocumentationDescriptor;

/**
 * Contract test for {@see DocumentationDescriptor}.
 *
 * A slug/title/payload wrapper carried in the ADP envelope's documentation
 * array. This test locks its serialized key set and JSON stability.
 */
final class DocumentationDescriptorContractTest extends TestCase
{
    private function makeDoc(): DocumentationDescriptor
    {
        return new DocumentationDescriptor(
            slug: 'errors',
            title: 'Error Documentation',
            payload: ['codes' => ['E001' => 'Invalid filter']],
        );
    }

    public function test_envelope_exposes_exactly_the_documented_keys(): void
    {
        $payload = $this->makeDoc()->toArray();

        self::assertSame(['slug', 'title', 'payload'], array_keys($payload));
    }

    public function test_values_are_preserved(): void
    {
        $payload = $this->makeDoc()->toArray();

        self::assertSame('errors', $payload['slug']);
        self::assertSame('Error Documentation', $payload['title']);
        self::assertSame(['codes' => ['E001' => 'Invalid filter']], $payload['payload']);
    }

    public function test_json_serialize_matches_to_array(): void
    {
        $doc = $this->makeDoc();

        self::assertSame($doc->toArray(), $doc->jsonSerialize());

        $roundTrip = json_decode(json_encode($doc, JSON_THROW_ON_ERROR), true);
        self::assertSame($doc->toArray(), $roundTrip);
    }
}
