<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Console\Commands;

use Illuminate\Console\Command;
use Ronu\LaravelAgentProtocol\Contracts\MetadataRepositoryContract;

final class AgentClearCommand extends Command
{
    protected $signature = 'agent:clear';

    protected $description = 'Clear the cached Agent Discovery Protocol metadata graph.';

    public function handle(MetadataRepositoryContract $repository): int
    {
        $repository->clear();
        $this->info('Agent metadata cache cleared.');

        return self::SUCCESS;
    }
}
