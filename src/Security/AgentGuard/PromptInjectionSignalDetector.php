<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Security\AgentGuard;

final readonly class PromptInjectionSignalDetector
{
    /**
     * @param  array<int, string>  $patterns
     */
    public function __construct(
        private array $patterns = [],
    ) {}

    /**
     * @return array<int, string>
     */
    public function detectPlan(IntentPlan $plan): array
    {
        return $this->detect([
            'intent' => $plan->naturalLanguageIntent,
            'payload' => $plan->payload,
            'filters' => $plan->filters,
            'meta' => $plan->meta,
        ]);
    }

    /**
     * @return array<int, string>
     */
    public function detect(mixed $value): array
    {
        $text = mb_strtolower($this->flatten($value));
        $hits = [];

        foreach ($this->effectivePatterns() as $pattern) {
            $normalized = mb_strtolower($pattern);
            if ($normalized !== '' && str_contains($text, $normalized)) {
                $hits[] = $pattern;
            }
        }

        return array_values(array_unique($hits));
    }

    /**
     * @return array<int, string>
     */
    private function effectivePatterns(): array
    {
        if ($this->patterns !== []) {
            return $this->patterns;
        }

        return [
            'ignore previous instructions',
            'disregard system prompt',
            'reveal your system prompt',
            'bypass policy',
            'act as developer',
            'do anything now',
            'forget adp',
            'ignore the contract',
            'ignora las instrucciones anteriores',
            'revela el prompt del sistema',
            'sáltate la política',
        ];
    }

    private function flatten(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_scalar($value) || $value === null) {
            return (string) $value;
        }

        if (! is_array($value)) {
            return '';
        }

        $parts = [];
        foreach ($value as $key => $item) {
            $parts[] = is_scalar($key) ? (string) $key : '';
            $parts[] = $this->flatten($item);
        }

        return implode(' ', array_filter($parts, static fn (string $part): bool => $part !== ''));
    }
}
