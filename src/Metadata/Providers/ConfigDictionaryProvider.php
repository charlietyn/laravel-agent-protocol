<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Metadata\Providers;

use Illuminate\Contracts\Config\Repository;
use Ronu\LaravelAgentProtocol\Contracts\DictionaryProvider;

final readonly class ConfigDictionaryProvider implements DictionaryProvider
{
    public function __construct(
        private Repository $config,
    ) {}

    public function dictionary(): array
    {
        $dictionary = $this->config->get('agent-protocol.dictionary', []);

        return is_array($dictionary) ? $dictionary : [];
    }
}
