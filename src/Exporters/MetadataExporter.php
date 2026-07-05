<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Exporters;

use Ronu\LaravelAgentProtocol\DTO\AgentMetadataGraph;

interface MetadataExporter
{
    public function export(AgentMetadataGraph $graph): string;
}
