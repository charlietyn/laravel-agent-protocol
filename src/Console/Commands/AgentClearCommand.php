<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Console\Commands;

use Illuminate\Console\Command;
use Ronu\LaravelAgentProtocol\Contracts\MetadataRepositoryContract;

final class AgentClearCommand extends Command
{
    protected $signature = 'agent:clear {--tenant=} {--locale=}';

    protected $description = 'Clear the cached Agent Discovery Protocol metadata graph.';

    public function handle(MetadataRepositoryContract $repository): int
    {
        $repository->clear($this->variation());
        $this->info('Agent metadata cache cleared.');

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function variation(): array
    {
        $headers = [];
        $tenant = $this->option('tenant');
        $locale = $this->option('locale');
        $tenantHeader = config('agent-protocol.security.tenant_header', 'X-Tenant-Id');
        $localeHeader = config('agent-protocol.security.locale_header', 'Accept-Language');
        $tenantHeader = is_string($tenantHeader) && $tenantHeader !== '' ? $tenantHeader : 'X-Tenant-Id';
        $localeHeader = is_string($localeHeader) && $localeHeader !== '' ? $localeHeader : 'Accept-Language';

        if (is_scalar($tenant) && (string) $tenant !== '') {
            $headers[$tenantHeader] = (string) $tenant;
        }

        if (is_scalar($locale) && (string) $locale !== '') {
            $headers[$localeHeader] = (string) $locale;
        }

        return $headers === [] ? [] : ['headers' => $headers];
    }
}
