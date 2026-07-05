<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Metadata;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Container\Container;

final readonly class MetadataBuildContext
{
    public function __construct(
        public Container $container,
    ) {}

    public function config(string $key, mixed $default = null): mixed
    {
        $config = $this->container->make('config');

        return $config instanceof ConfigRepository ? $config->get($key, $default) : $default;
    }

    /**
     * @template T of object
     *
     * @param  class-string<T>  $abstract
     * @return T
     */
    public function make(string $abstract): object
    {
        return $this->container->make($abstract);
    }
}
