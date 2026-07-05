<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Contracts;

use Ronu\LaravelAgentProtocol\DTO\DocumentationDescriptor;

interface DocumentationProvider
{
    /**
     * @return iterable<DocumentationDescriptor>
     */
    public function documents(): iterable;
}
