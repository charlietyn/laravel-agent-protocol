<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Tests\Fixtures;

final class FakeRequest
{
    /**
     * @return array<int, string>
     */
    public function getAvailableScenarios(): array
    {
        return ['query', 'create', 'update'];
    }

    /**
     * @return array<string, mixed>
     */
    public function getRulesForScenario(string $scenario): array
    {
        return match ($scenario) {
            'create' => [
                'name' => ['required', 'string'],
                'email' => ['required', 'email'],
            ],
            'update' => [
                'name' => ['sometimes', 'string'],
                'email' => ['sometimes', 'email'],
            ],
            default => [
                'status' => ['nullable', 'string'],
            ],
        };
    }
}
