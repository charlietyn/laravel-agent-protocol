<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Console\Commands;

use Illuminate\Console\Command;
use Ronu\LaravelAgentProtocol\DTO\SchemaCatalogDescriptor;
use Ronu\LaravelAgentProtocol\SchemaDiscovery\SchemaCatalogBuilder;
use Ronu\LaravelAgentProtocol\SchemaDiscovery\SchemaConfigExporter;
use Ronu\LaravelAgentProtocol\SchemaDiscovery\SchemaJsonExporter;
use Ronu\LaravelAgentProtocol\SchemaDiscovery\SchemaMarkdownExporter;

final class AgentSchemaExportCommand extends Command
{
    protected $signature = 'agent:schema:export
        {connection? : Database connection name}
        {path=schema-catalog.json : Output path}
        {--format=json : Export format: json, markdown, reference-config}
        {--include-views : Include database views}
        {--estimate-rows : Count table rows for cacheable classification}';

    protected $description = 'Export discovered database schema metadata.';

    public function handle(
        SchemaCatalogBuilder $builder,
        SchemaJsonExporter $json,
        SchemaMarkdownExporter $markdown,
        SchemaConfigExporter $config,
    ): int {
        $catalog = $builder->build($this->connection(), [
            'include_views' => (bool) $this->option('include-views') ?: (bool) config('agent-protocol.schema_discovery.include_views', true),
            'estimate_rows' => (bool) $this->option('estimate-rows'),
        ]);

        $path = $this->path();
        $this->writeOutput($path, $this->payload($catalog, $json, $markdown, $config));

        $this->info("Schema catalog exported to [{$path}].");

        return self::SUCCESS;
    }

    private function connection(): ?string
    {
        $argument = $this->argument('connection');

        return is_string($argument) && $argument !== '' ? $argument : null;
    }

    private function path(): string
    {
        $argument = $this->argument('path');

        return is_string($argument) && $argument !== '' ? $argument : 'schema-catalog.json';
    }

    private function payload(
        SchemaCatalogDescriptor $catalog,
        SchemaJsonExporter $json,
        SchemaMarkdownExporter $markdown,
        SchemaConfigExporter $config,
    ): string {
        return match ($this->option('format')) {
            'markdown' => $markdown->export($catalog),
            'reference-config' => $config->referenceTables($catalog),
            default => $json->export($catalog),
        };
    }

    private function writeOutput(string $path, string $output): void
    {
        $directory = dirname($path);
        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        file_put_contents($path, $output, LOCK_EX);
    }
}
