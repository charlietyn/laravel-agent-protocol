<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\DTO\Concerns;

trait SerializesDescriptor
{
    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function serializeValues(array $values): array
    {
        return array_map(function (mixed $value): mixed {
            if (is_array($value)) {
                return array_map(fn (mixed $item): mixed => $this->serializeItem($item), $value);
            }

            return $this->serializeItem($value);
        }, $values);
    }

    private function serializeItem(mixed $value): mixed
    {
        if (is_object($value) && method_exists($value, 'toArray')) {
            return $value->toArray();
        }

        if ($value instanceof \JsonSerializable) {
            return $value->jsonSerialize();
        }

        return $value;
    }
}
