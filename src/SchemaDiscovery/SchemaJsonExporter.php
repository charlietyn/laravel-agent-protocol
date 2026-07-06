<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\SchemaDiscovery;

use Ronu\LaravelAgentProtocol\DTO\SchemaCatalogDescriptor;

final class SchemaJsonExporter
{
    public function export(SchemaCatalogDescriptor $catalog): string
    {
        return (string) json_encode($catalog->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
