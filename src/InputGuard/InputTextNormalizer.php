<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\InputGuard;

final readonly class InputTextNormalizer
{
    /**
     * @return array{input:string, changed:bool, metadata:array<string, mixed>}
     */
    public function normalize(string $input, InputTextPolicy $policy): array
    {
        $normalized = $input;
        $metadata = [];

        if ($policy->trim) {
            $trimmed = trim($normalized);
            if ($trimmed !== $normalized) {
                $metadata['trimmed'] = true;
                $normalized = $trimmed;
            }
        }

        if ($policy->collapseControlChars) {
            $collapsed = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/u', ' ', $normalized) ?? $normalized;
            if ($collapsed !== $normalized) {
                $metadata['control_chars_collapsed'] = true;
                $normalized = $collapsed;
            }
        }

        return [
            'input' => $normalized,
            'changed' => $normalized !== $input,
            'metadata' => $metadata,
        ];
    }
}
