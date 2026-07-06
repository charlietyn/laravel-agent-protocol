<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Tests\Feature;

use Illuminate\Support\Facades\File;
use Ronu\LaravelAgentProtocol\Tests\TestCase;

final class AgentConsoleCommandsTest extends TestCase
{
    public function test_discover_validate_cache_and_export_commands_succeed(): void
    {
        $this->artisan('agent:discover')->assertExitCode(0);
        $this->artisan('agent:validate')->assertExitCode(0);
        $this->artisan('agent:cache')->assertExitCode(0);

        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'adp-export-'.bin2hex(random_bytes(4)).'.json';

        $this->artisan('agent:export', [
            'path' => $path,
            '--format' => 'mcp',
        ])->assertExitCode(0);

        self::assertFileExists($path);
        self::assertStringContainsString('query_security_fake_user', (string) File::get($path));

        File::delete($path);
    }

    public function test_docs_command_generates_markdown_files(): void
    {
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'adp-docs-'.bin2hex(random_bytes(4));

        $this->artisan('agent:docs', ['path' => $path])->assertExitCode(0);

        self::assertFileExists($path.DIRECTORY_SEPARATOR.'resources.md');
        self::assertStringContainsString('security.fake-user', (string) File::get($path.DIRECTORY_SEPARATOR.'resources.md'));

        File::deleteDirectory($path);
    }
}
