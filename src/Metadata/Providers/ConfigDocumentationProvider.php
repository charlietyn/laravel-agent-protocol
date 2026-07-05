<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Metadata\Providers;

use Illuminate\Contracts\Config\Repository;
use Ronu\LaravelAgentProtocol\Contracts\DocumentationProvider;
use Ronu\LaravelAgentProtocol\DTO\DocumentationDescriptor;

final readonly class ConfigDocumentationProvider implements DocumentationProvider
{
    public function __construct(
        private Repository $config,
    ) {}

    public function documents(): iterable
    {
        $errors = $this->config->get('agent-protocol.documentation.errors', []);

        yield new DocumentationDescriptor(
            slug: 'errors',
            title: 'ADP error contract',
            payload: [
                'errors' => $errors,
            ],
        );
    }
}
