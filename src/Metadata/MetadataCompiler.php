<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Metadata;

use Ronu\LaravelAgentProtocol\Contracts\MetadataCompilerContract;
use Ronu\LaravelAgentProtocol\Contracts\MetadataCompilerPass;
use Ronu\LaravelAgentProtocol\DTO\AgentMetadataGraph;

final readonly class MetadataCompiler implements MetadataCompilerContract
{
    /**
     * @param  iterable<MetadataCompilerPass>  $passes
     */
    public function __construct(
        private iterable $passes,
        private MetadataBuildContext $context,
    ) {}

    public function compile(): AgentMetadataGraph
    {
        $protocolVersion = $this->context->config('agent-protocol.protocol_version', '1.0');

        $builder = new AgentMetadataGraphBuilder(
            protocolVersion: is_string($protocolVersion) ? $protocolVersion : '1.0',
        );

        foreach ($this->passes as $pass) {
            $pass->compile($this->context, $builder);
        }

        return $builder->build();
    }
}
