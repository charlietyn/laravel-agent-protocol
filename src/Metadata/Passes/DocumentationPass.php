<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Metadata\Passes;

use Ronu\LaravelAgentProtocol\Contracts\DocumentationProvider;
use Ronu\LaravelAgentProtocol\Contracts\MetadataCompilerPass;
use Ronu\LaravelAgentProtocol\Metadata\AgentMetadataGraphBuilder;
use Ronu\LaravelAgentProtocol\Metadata\MetadataBuildContext;

final readonly class DocumentationPass implements MetadataCompilerPass
{
    /**
     * @param  iterable<DocumentationProvider>  $providers
     */
    public function __construct(
        private iterable $providers,
    ) {}

    public function compile(MetadataBuildContext $context, AgentMetadataGraphBuilder $builder): void
    {
        foreach ($this->providers($context) as $provider) {
            foreach ($provider->documents() as $document) {
                $builder->addDocumentation($document);
            }
        }
    }

    /**
     * @return iterable<DocumentationProvider>
     */
    private function providers(MetadataBuildContext $context): iterable
    {
        yield from $this->providers;

        foreach ((array) $context->config('agent-protocol.providers.documentation', []) as $providerClass) {
            if (! is_string($providerClass) || ! class_exists($providerClass)) {
                continue;
            }

            $provider = $context->make($providerClass);
            if ($provider instanceof DocumentationProvider) {
                yield $provider;
            }
        }
    }
}
