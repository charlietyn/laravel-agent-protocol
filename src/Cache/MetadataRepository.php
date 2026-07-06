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

    /**
     * @param  array<string, mixed>|null  $variation
     */
    public function get(?array $variation = null): AgentMetadataGraph
    {
        if (! $this->enabled()) {
            return $this->compiler->compile();
        }

        if ($this->driver() === 'compiled_file') {
            return $this->getFromCompiledFile($variation);
        }

        return $this->store()->remember(
            $this->key($variation),
            $this->ttl(),
            fn (): AgentMetadataGraph => $this->compiler->compile(),
        );
    }

    /**
     * @param  array<string, mixed>|null  $variation
     */
    public function refresh(?array $variation = null): AgentMetadataGraph
    {
        $graph = $this->compiler->compile();

        if ($this->enabled()) {
            if ($this->driver() === 'compiled_file') {
                $this->putCompiledFile($graph, $variation);
            } else {
                $this->store()->put($this->key($variation), $graph, $this->ttl());
            }
        }

        return $graph;
    }

    /**
     * @param  array<string, mixed>|null  $variation
     */
    public function clear(?array $variation = null): void
    {
        if ($this->driver() === 'compiled_file') {
            $this->forgetCompiledFile($variation);

            return;
        }

        $this->store()->forget($this->key($variation));
    }

    private function enabled(): bool
    {
        return (bool) $this->config->get('agent-protocol.cache.enabled', true);
    }

    private function driver(): string
    {
        $driver = $this->config->get('agent-protocol.cache.driver', 'store');

        return is_string($driver) && $driver !== '' ? $driver : 'store';
    }

    /**
     * @param  array<string, mixed>|null  $variation
     */
    private function key(?array $variation = null): string
    {
        $key = $this->config->get('agent-protocol.cache.key', 'agent-protocol:metadata:v1');

        $base = is_string($key) && $key !== '' ? $key : 'agent-protocol:metadata:v1';
        $variation ??= $this->variation();

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
     * @param  array<string, mixed>|null  $variation
     */
    private function getFromCompiledFile(?array $variation = null): AgentMetadataGraph
    {
        $path = $this->compiledPath($variation);

        if (is_file($path) && ! $this->compiledFileExpired($path)) {
            $payload = json_decode((string) file_get_contents($path), true);

            if (is_array($payload)) {
                return (new CompiledMetadataGraphHydrator)->hydrate($payload);
            }
        }

        return $this->refresh($variation);
    }

    /**
     * @param  array<string, mixed>|null  $variation
     */
    private function putCompiledFile(AgentMetadataGraph $graph, ?array $variation = null): void
    {
        $path = $this->compiledPath($variation);
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        file_put_contents(
            $path,
            (string) json_encode($graph->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            LOCK_EX,
        );
    }

    /**
     * @param  array<string, mixed>|null  $variation
     */
    private function forgetCompiledFile(?array $variation = null): void
    {
        $path = $this->compiledPath($variation);

        if (is_file($path)) {
            unlink($path);
        }
    }

    private function compiledFileExpired(string $path): bool
    {
        $ttl = $this->ttl();

        return $ttl > 0 && (filemtime($path) ?: 0) + $ttl < time();
    }

    /**
     * @param  array<string, mixed>|null  $variation
     */
    private function compiledPath(?array $variation = null): string
    {
        $directory = $this->config->get('agent-protocol.cache.path');
        $filename = $this->config->get('agent-protocol.cache.compiled_filename', 'metadata.json');
        $directory = is_string($directory) && $directory !== '' ? $directory : (getcwd() ?: sys_get_temp_dir());
        $filename = is_string($filename) && $filename !== '' ? $filename : 'metadata.json';
        $variation ??= $this->variation();

        if ($variation !== []) {
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            $basename = $extension !== ''
                ? substr($filename, 0, -strlen($extension) - 1)
                : $filename;
            $filename = $basename.'-'.sha1((string) json_encode($variation)).($extension !== '' ? '.'.$extension : '');
        }

        return rtrim($directory, DIRECTORY_SEPARATOR.'/\\').DIRECTORY_SEPARATOR.$filename;
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
