<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Console\Commands;

use Illuminate\Console\Command;
use Ronu\LaravelAgentProtocol\ProjectContext\ProjectContextManager;

final class AgentContextHealthCommand extends Command
{
    protected $signature = 'agent:context:health {--json : Output health as JSON}';

    protected $description = 'Check optional project context provider health.';

    public function handle(ProjectContextManager $manager): int
    {
        $health = $manager->health();

        if ($this->option('json')) {
            $this->line((string) json_encode($health, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->line('Project Context Health');
        $this->line('----------------------');
        $this->line('Provider: '.$health->provider);
        $this->line('Available: '.($health->available ? 'yes' : 'no'));

        if ($health->source !== null) {
            $this->line('Source: '.$health->source);
        }

        foreach ($health->warnings as $warning) {
            $this->warn($warning);
        }

        return $health->available || ! $manager->enabled() ? self::SUCCESS : self::FAILURE;
    }
}
