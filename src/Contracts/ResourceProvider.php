<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Contracts;

interface ResourceProvider
{
    /**
     * @return iterable<string, array<string, mixed>>
     */
    public function resources(): iterable;
}
