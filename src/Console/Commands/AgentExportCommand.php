<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Ronu\LaravelAgentProtocol\Contracts\MetadataRepositoryContract;
use Ronu\LaravelAgentProtocol\Exporters\JsonMetadataExporter;

final class AgentExportCommand extends Command
{
    protected $signature = 'agent:export {path=agent-metadata.json}';

    protected $description = 'Export the compiled ADP metadata graph as JSON.';

    public function handle(MetadataRepositoryContract $repository, JsonMetadataExporter $exporter): int
    {
        $argument = $this->argument('path');
        $path = is_string($argument) && $argument !== '' ? $argument : 'agent-metadata.json';

        File::put($path, $exporter->export($repository->refresh()));
        $this->info("Agent metadata exported to [{$path}].");

        return self::SUCCESS;
    }
}
