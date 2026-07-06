<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Console\Commands;

use Illuminate\Console\Command;
use Ronu\LaravelAgentProtocol\DTO\SchemaCatalogDescriptor;
use Ronu\LaravelAgentProtocol\SchemaDiscovery\SchemaCatalogBuilder;
use Ronu\LaravelAgentProtocol\SchemaDiscovery\SchemaConfigExporter;
use Ronu\LaravelAgentProtocol\SchemaDiscovery\SchemaJsonExporter;
use Ronu\LaravelAgentProtocol\SchemaDiscovery\SchemaMarkdownExporter;
use Ronu\LaravelAgentProtocol\SchemaDiscovery\SchemaOverrideRepository;

final class AgentSchemaDiscoverCommand extends Command
{
    protected $signature = 'agent:schema:discover
        {connection? : Database connection name}
        {--json : Output the schema catalog as JSON}
        {--markdown : Output the schema catalog as Markdown}
        {--output= : Write output to a file}
        {--include-views : Include database views}
        {--estimate-rows : Count table rows for cacheable classification}
        {--suggest-reference-tables : Output reference_tables config suggestions}
        {--suggest-resources : Include resource naming suggestions in the summary}
        {--write-config : Write or update config/agent-protocol/schemas/{connection}.php}
        {--force : Overwrite generated schema config values}';

    protected $description = 'Discover database schema metadata for ADP documentation and configuration.';

    public function handle(
        SchemaCatalogBuilder $builder,
        SchemaJsonExporter $json,
        SchemaMarkdownExporter $markdown,
        SchemaConfigExporter $config,
        SchemaOverrideRepository $overrides,
    ): int {
        $catalog = $builder->build($this->connection(), $this->discoveryOptions());

        if ($this->option('write-config')) {
            $result = $overrides->writeDraft($catalog, (bool) $this->option('force'));
            $this->info(($result['created'] ? 'Created' : 'Updated').' schema override config: '.$result['path']);
        }

        $output = $this->outputPayload($catalog, $json, $markdown, $config);
        $path = $this->option('output');

        if (is_string($path) && $path !== '') {
            $output ??= $json->export($catalog);
            $this->writeOutput($path, $output);
            $this->info('Schema discovery output written to ['.$path.'].');

            return self::SUCCESS;
        }

        if ($output !== null) {
            $this->line($output);

            return self::SUCCESS;
        }

        $this->summary($catalog);

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function discoveryOptions(): array
    {
        return [
            'include_views' => (bool) $this->option('include-views') ?: (bool) config('agent-protocol.schema_discovery.include_views', true),
            'estimate_rows' => (bool) $this->option('estimate-rows'),
        ];
    }

    private function connection(): ?string
    {
        $argument = $this->argument('connection');

        return is_string($argument) && $argument !== '' ? $argument : null;
    }

    private function outputPayload(
        SchemaCatalogDescriptor $catalog,
        SchemaJsonExporter $json,
        SchemaMarkdownExporter $markdown,
        SchemaConfigExporter $config,
    ): ?string {
        if ($this->option('suggest-reference-tables')) {
            return $config->referenceTables($catalog);
        }

        if ($this->option('markdown')) {
            return $markdown->export($catalog);
        }

        if ($this->option('json')) {
            return $json->export($catalog);
        }

        return null;
    }

    private function summary(SchemaCatalogDescriptor $catalog): void
    {
        $this->info('Database schema discovered successfully.');
        $this->line('Connection: '.$catalog->connection);
        $this->line('Driver: '.$catalog->driver);
        $this->line('Tables: '.count($catalog->baseTables()));
        $this->line('Views: '.count($catalog->views()));
        $this->line('Cacheable candidates: '.count($catalog->cacheableTables()));
        $this->line('Reference-table candidates: '.count($catalog->referenceTables()));

        foreach ($catalog->tables as $table) {
            $suffix = $table->referenceTable ? ' reference' : ($table->cacheable ? ' cacheable' : '');
            $resource = $this->option('suggest-resources') && $table->resource ? ' -> '.$table->resource : '';
            $this->line('- '.$table->name.' ['.count($table->columns).' columns'.$suffix.']'.$resource);
        }
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
