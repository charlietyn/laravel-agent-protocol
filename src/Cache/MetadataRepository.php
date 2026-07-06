<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Cache;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Ronu\LaravelAgentProtocol\Contracts\MetadataCompilerContract;
use Ronu\LaravelAgentProtocol\Contracts\MetadataRepositoryContract;
use Ronu\LaravelAgentProtocol\DTO\AgentMetadataGraph;

final readonly class MetadataRepository implements MetadataRepositoryContract
{
    public function __construct(
        private MetadataCompilerContract $compiler,
        private CacheFactory $cache,
        private ConfigRepository $config,
        private Container $container,
    ) {}

    public function get(): AgentMetadataGraph
    {
        if (! $this->enabled()) {
            return $this->compiler->compile();
        }

        return $this->store()->remember(
            $this->key(),
            $this->ttl(),
            fn (): AgentMetadataGraph => $this->compiler->compile(),
        );
    }

    public function refresh(): AgentMetadataGraph
    {
        $graph = $this->compiler->compile();

        if ($this->enabled()) {
            $this->store()->put($this->key(), $graph, $this->ttl());
        }

        return $graph;
    }

    public function clear(): void
    {
        $this->store()->forget($this->key());
    }

    private function enabled(): bool
    {
        return (bool) $this->config->get('agent-protocol.cache.enabled', true);
    }

    private function key(): string
    {
        $key = $this->config->get('agent-protocol.cache.key', 'agent-protocol:metadata:v1');

        $base = is_string($key) && $key !== '' ? $key : 'agent-protocol:metadata:v1';
        $variation = $this->variation();

        if ($variation === []) {
            return $base;
        }

        return $base.':'.sha1((string) json_encode($variation));
    }

    private function ttl(): int
    {
        $ttl = $this->config->get('agent-protocol.cache.ttl', 3600);

        return is_numeric($ttl) ? (int) $ttl : 3600;
    }

    private function store(): Repository
    {
        $store = $this->config->get('agent-protocol.cache.store');

        return is_string($store) && $store !== ''
            ? $this->cache->store($store)
            : $this->cache->store();
    }

    /**
     * @return array<string, mixed>
     */
    private function variation(): array
    {
        $headers = $this->config->get('agent-protocol.cache.vary.headers', []);
        if (! is_array($headers) || $headers === [] || ! $this->container->bound('request')) {
            return [];
        }

        $request = $this->container->make('request');
        if (! $request instanceof Request) {
            return [];
        }

        $variation = [];
        foreach ($headers as $header) {
            if (! is_string($header) || $header === '') {
                continue;
            }

            $value = $request->headers->get($header);
            if ($value !== null && $value !== '') {
                $variation['headers'][$header] = $value;
            }
        }

        return $variation;
    }
}
