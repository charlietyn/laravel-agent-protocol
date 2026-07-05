<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Console\Commands;

use Illuminate\Console\Command;
use Ronu\LaravelAgentProtocol\Contracts\MetadataRepositoryContract;
use Ronu\LaravelAgentProtocol\Validation\ProtocolValidator;

final class AgentCacheCommand extends Command
{
    protected $signature = 'agent:cache';

    protected $description = 'Compile and cache the Agent Discovery Protocol metadata graph.';

    public function handle(MetadataRepositoryContract $repository, ProtocolValidator $validator): int
    {
        $graph = $repository->refresh();
        $errors = $validator->validate($graph);

        if ($errors !== []) {
            foreach ($errors as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $this->info('Agent metadata cached successfully.');
        $this->line('Resources: '.count($graph->resources));
        $this->line('Modules: '.count($graph->modules));

        return self::SUCCESS;
    }
}
