<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Metadata\Providers;

use Illuminate\Contracts\Config\Repository;
use Ronu\LaravelAgentProtocol\Contracts\ResourceProvider;

final readonly class ConfigResourceProvider implements ResourceProvider
{
    public function __construct(
        private Repository $config,
    ) {}

    public function resources(): iterable
    {
        $resources = $this->config->get('agent-protocol.resources', []);

        return is_array($resources) ? $resources : [];
    }
}
