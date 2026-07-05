<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Metadata\Introspection;

final class RuleNormalizer
{
    /**
     * @return array<int, string>
     */
    public function normalize(mixed $rules): array
    {
        if ($rules === null) {
            return [];
        }

        if (is_string($rules)) {
            return array_values(array_filter(explode('|', $rules)));
        }

        if (! is_array($rules)) {
            return [$this->normalizeSingle($rules)];
        }

        return array_values(array_map(fn (mixed $rule): string => $this->normalizeSingle($rule), $rules));
    }

    private function normalizeSingle(mixed $rule): string
    {
        if (is_string($rule)) {
            return $rule;
        }

        if (is_object($rule)) {
            if (method_exists($rule, '__toString')) {
                return (string) $rule;
            }

            return $rule::class;
        }

        if (is_scalar($rule)) {
            return (string) $rule;
        }

        return get_debug_type($rule);
    }
}
