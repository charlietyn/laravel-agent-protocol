<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Contracts;

use Ronu\LaravelAgentProtocol\DTO\AgentMetadataGraph;

interface MetadataCompilerContract
{
    public function compile(): AgentMetadataGraph;
}
