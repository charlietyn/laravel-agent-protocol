<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\ProjectContext;

final readonly class NullProjectContextProvider implements ProjectContextProvider
{
    public function enabled(): bool
    {
        return false;
    }

    public function health(): ProjectContextHealth
    {
        return new ProjectContextHealth(
            available: false,
            provider: 'none',
            source: 'disabled',
            warnings: ['Project context is disabled.'],
        );
    }

    public function query(ProjectContextQuery $query): ProjectContextResult
    {
        return new ProjectContextResult(
            provider: 'none',
            source: 'disabled',
            warnings: ['Project context is disabled.'],
            metadata: ['question' => $query->question],
        );
    }
}
