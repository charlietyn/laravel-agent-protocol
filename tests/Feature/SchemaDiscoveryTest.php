<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Ronu\LaravelAgentProtocol\SchemaDiscovery\SchemaCatalogBuilder;
use Ronu\LaravelAgentProtocol\Tests\TestCase;

final class SchemaDiscoveryTest extends TestCase
{
    private string $schemaConfigPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->schemaConfigPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'adp-schema-config-'.bin2hex(random_bytes(4));
        config()->set('agent-protocol.schema_discovery.config_path', $this->schemaConfigPath);
        config()->set('agent-protocol.schema_discovery.estimate_rows', true);

        Schema::dropIfExists('users');
        Schema::dropIfExists('departments');

        Schema::create('departments', function ($table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('code')->nullable();
        });

        Schema::create('users', function ($table): void {
            $table->id();
            $table->string('name');
            $table->string('password');
            $table->foreignId('department_id')->nullable()->constrained('departments');
        });
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->schemaConfigPath);

        parent::tearDown();
    }

    public function test_schema_catalog_discovers_tables_and_applies_connection_overrides(): void
    {
        File::ensureDirectoryExists($this->schemaConfigPath);
        File::put($this->schemaConfigPath.DIRECTORY_SEPARATOR.'sqlite.php', <<<'PHP'
<?php

return [
    'module' => 'hr',
    'tables' => [
        'departments' => [
            'description' => 'Reference table for departments.',
            'cacheable' => true,
            'reference_table' => true,
            'resource' => 'hr.department',
            'lookup_field' => 'name',
            'max_records' => 25,
        ],
    ],
    'columns' => [
        'users.department_id' => [
            'label' => 'Department',
            'description' => 'Department assigned to the user.',
        ],
    ],
];
PHP);

        $catalog = app(SchemaCatalogBuilder::class)->build('sqlite', ['estimate_rows' => true]);
        $departments = collect($catalog->tables)->firstWhere('name', 'departments');
        $users = collect($catalog->tables)->firstWhere('name', 'users');
        $departmentId = collect($users->columns)->firstWhere('name', 'department_id');
        $password = collect($users->columns)->firstWhere('name', 'password');

        self::assertSame('sqlite', $catalog->connection);
        self::assertTrue($departments->cacheable);
        self::assertTrue($departments->referenceTable);
        self::assertSame('hr.department', $departments->resource);
        self::assertSame('name', $departments->lookupField);
        self::assertSame('Department', $departmentId->label);
        self::assertSame('config', $departmentId->descriptionSource);
        self::assertTrue($password->sensitive);
    }

    public function test_schema_discover_command_outputs_json_and_writes_override_config(): void
    {
        $this->artisan('agent:schema:discover', [
            'connection' => 'sqlite',
            '--json' => true,
        ])->assertExitCode(0);

        $this->artisan('agent:schema:discover', [
            'connection' => 'sqlite',
            '--write-config' => true,
        ])->assertExitCode(0);

        self::assertFileExists($this->schemaConfigPath.DIRECTORY_SEPARATOR.'sqlite.php');
        self::assertStringContainsString('departments', (string) File::get($this->schemaConfigPath.DIRECTORY_SEPARATOR.'sqlite.php'));
    }

    public function test_schema_export_command_writes_reference_table_suggestions(): void
    {
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'adp-reference-'.bin2hex(random_bytes(4)).'.php';

        $this->artisan('agent:schema:export', [
            'connection' => 'sqlite',
            'path' => $path,
            '--format' => 'reference-config',
            '--estimate-rows' => true,
        ])->assertExitCode(0);

        self::assertFileExists($path);
        self::assertStringContainsString('reference_tables', (string) File::get($path));

        File::delete($path);
    }
}
