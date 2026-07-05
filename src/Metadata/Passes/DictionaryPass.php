<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Metadata\Passes;

use Ronu\LaravelAgentProtocol\Contracts\DictionaryProvider;
use Ronu\LaravelAgentProtocol\Contracts\MetadataCompilerPass;
use Ronu\LaravelAgentProtocol\Metadata\AgentMetadataGraphBuilder;
use Ronu\LaravelAgentProtocol\Metadata\MetadataBuildContext;

final readonly class DictionaryPass implements MetadataCompilerPass
{
    /**
     * @param  iterable<DictionaryProvider>  $providers
     */
    public function __construct(
        private iterable $providers,
    ) {}

    public function compile(MetadataBuildContext $context, AgentMetadataGraphBuilder $builder): void
    {
        foreach ($this->providers($context) as $provider) {
            $builder->mergeDictionary($provider->dictionary());
        }
    }

    /**
     * @return iterable<DictionaryProvider>
     */
    private function providers(MetadataBuildContext $context): iterable
    {
        yield from $this->providers;

        foreach ((array) $context->config('agent-protocol.providers.dictionary', []) as $providerClass) {
            if (! is_string($providerClass) || ! class_exists($providerClass)) {
                continue;
            }

            $provider = $context->make($providerClass);
            if ($provider instanceof DictionaryProvider) {
                yield $provider;
            }
        }
    }
}
