<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Console\Commands;

use Illuminate\Console\Command;
use Ronu\LaravelAgentProtocol\Contracts\MetadataRepositoryContract;
use Ronu\LaravelAgentProtocol\Validation\ProtocolValidator;

final class AgentValidateCommand extends Command
{
    protected $signature = 'agent:validate';

    protected $description = 'Validate the compiled metadata graph against the ADP contract.';

    public function handle(MetadataRepositoryContract $repository, ProtocolValidator $validator): int
    {
        $errors = $validator->validate($repository->refresh());

        if ($errors === []) {
            $this->info('Agent metadata is valid.');

            return self::SUCCESS;
        }

        foreach ($errors as $error) {
            $this->error($error);
        }

        return self::FAILURE;
    }
}
