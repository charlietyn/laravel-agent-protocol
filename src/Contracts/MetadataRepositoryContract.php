<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Contracts;

use Ronu\LaravelAgentProtocol\DTO\AgentMetadataGraph;

interface MetadataRepositoryContract
{
    /**
     * @param  array<string, mixed>|null  $variation
     */
    public function get(?array $variation = null): AgentMetadataGraph;

    /**
     * @param  array<string, mixed>|null  $variation
     */
    public function refresh(?array $variation = null): AgentMetadataGraph;

    /**
     * @param  array<string, mixed>|null  $variation
     */
    public function clear(?array $variation = null): void;
}
