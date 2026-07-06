<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\File;
use Ronu\LaravelAgentProtocol\Contracts\MetadataRepositoryContract;
use Ronu\LaravelAgentProtocol\Exporters\MetadataExporter;

final class AgentExportCommand extends Command
{
    protected $signature = 'agent:export {path=agent-metadata.json} {--format=json : Export format: json, json-schema, markdown, mcp}';

    protected $description = 'Export the compiled ADP metadata graph.';

    public function handle(MetadataRepositoryContract $repository, Container $container): int
    {
        $argument = $this->argument('path');
        $path = is_string($argument) && $argument !== '' ? $argument : 'agent-metadata.json';
        $format = $this->option('format');
        $format = is_string($format) && $format !== '' ? $format : 'json';
        $exporterClass = config("agent-protocol.exporters.{$format}");

        if (! is_string($exporterClass) || ! class_exists($exporterClass)) {
            $this->error("Unsupported ADP export format [{$format}].");

            return self::FAILURE;
        }

        $exporter = $container->make($exporterClass);
        if (! $exporter instanceof MetadataExporter) {
            $this->error("Exporter [{$exporterClass}] must implement ".MetadataExporter::class.'.');

            return self::FAILURE;
        }

        File::put($path, $exporter->export($repository->refresh()));
        $this->info("Agent metadata exported to [{$path}].");

        return self::SUCCESS;
    }
}
