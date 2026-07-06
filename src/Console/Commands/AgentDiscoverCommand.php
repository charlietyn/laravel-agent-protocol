<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Console\Commands;

use Illuminate\Console\Command;
use Ronu\LaravelAgentProtocol\Contracts\MetadataCompilerContract;
use Ronu\LaravelAgentProtocol\Exporters\JsonMetadataExporter;
use Ronu\LaravelAgentProtocol\Validation\ProtocolValidator;

final class AgentDiscoverCommand extends Command
{
    protected $signature = 'agent:discover {--json : Output the compiled graph as JSON without writing cache}';

    protected $description = 'Discover ADP metadata without writing the cache.';

    public function handle(
        MetadataCompilerContract $compiler,
        ProtocolValidator $validator,
        JsonMetadataExporter $exporter,
    ): int {
        $graph = $compiler->compile();
        $errors = $validator->validate($graph);

        if ($errors !== []) {
            foreach ($errors as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line($exporter->export($graph));

            return self::SUCCESS;
        }

        $this->info('Agent metadata discovered successfully.');
        $this->line('Resources: '.count($graph->resources));
        $this->line('Modules: '.count($graph->modules));

        foreach ($graph->resources as $resource) {
            $this->line('- '.$resource->key.' ['.count($resource->operations).' operations]');
        }

        return self::SUCCESS;
    }
}
