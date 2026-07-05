<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Contracts;

use Ronu\LaravelAgentProtocol\DTO\AgentMetadataGraph;

interface MetadataRepositoryContract
{
    public function get(): AgentMetadataGraph;

    public function refresh(): AgentMetadataGraph;

    public function clear(): void;
}
