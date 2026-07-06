<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Ronu\LaravelAgentProtocol\Contracts\MetadataRepositoryContract;
use Ronu\LaravelAgentProtocol\Exporters\MarkdownMetadataExporter;

final class AgentDocsCommand extends Command
{
    protected $signature = 'agent:docs {path=docs/generated}';

    protected $description = 'Generate Markdown ADP documentation from compiled metadata.';

    public function handle(MetadataRepositoryContract $repository, MarkdownMetadataExporter $exporter): int
    {
        $argument = $this->argument('path');
        $path = is_string($argument) && $argument !== '' ? $argument : 'docs/generated';

        File::ensureDirectoryExists($path);

        foreach ($exporter->exportDocuments($repository->refresh()) as $file => $contents) {
            File::put(rtrim($path, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$file, $contents);
        }

        $this->info("Agent documentation generated in [{$path}].");

        return self::SUCCESS;
    }
}
