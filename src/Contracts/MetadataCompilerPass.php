<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Contracts;

use Ronu\LaravelAgentProtocol\Metadata\AgentMetadataGraphBuilder;
use Ronu\LaravelAgentProtocol\Metadata\MetadataBuildContext;

interface MetadataCompilerPass
{
    public function compile(MetadataBuildContext $context, AgentMetadataGraphBuilder $builder): void;
}
