<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Console\Commands;

use Illuminate\Console\Command;
use Ronu\LaravelAgentProtocol\SchemaDiscovery\SchemaCatalogBuilder;
use Ronu\LaravelAgentProtocol\SchemaDiscovery\SchemaCatalogValidator;

final class AgentSchemaValidateCommand extends Command
{
    protected $signature = 'agent:schema:validate
        {connection? : Database connection name}
        {--include-views : Include database views}
        {--estimate-rows : Count table rows for cacheable classification}';

    protected $description = 'Validate discovered database schema metadata and overrides.';

    public function handle(SchemaCatalogBuilder $builder, SchemaCatalogValidator $validator): int
    {
        $catalog = $builder->build($this->connection(), [
            'include_views' => (bool) $this->option('include-views') ?: (bool) config('agent-protocol.schema_discovery.include_views', true),
            'estimate_rows' => (bool) $this->option('estimate-rows'),
        ]);
        $errors = $validator->validate($catalog);

        if ($errors !== []) {
            foreach ($errors as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $this->info('Database schema metadata is valid.');

        return self::SUCCESS;
    }

    private function connection(): ?string
    {
        $argument = $this->argument('connection');

        return is_string($argument) && $argument !== '' ? $argument : null;
    }
}
