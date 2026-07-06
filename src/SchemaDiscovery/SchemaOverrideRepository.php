<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\SchemaDiscovery;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Ronu\LaravelAgentProtocol\DTO\SchemaCatalogDescriptor;

final readonly class SchemaOverrideRepository
{
    public function __construct(
        private ConfigRepository $config,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function load(string $connection): array
    {
        $path = $this->path($connection);
        if (! is_file($path)) {
            return $this->configOverride($connection);
        }

        $overrides = require $path;

        return is_array($overrides)
            ? array_replace_recursive($this->configOverride($connection), $overrides)
            : $this->configOverride($connection);
    }

    /**
     * @return array{path: string, created: bool}
     */
    public function writeDraft(SchemaCatalogDescriptor $catalog, bool $force = false): array
    {
        $path = $this->path($catalog->connection);
        $created = ! is_file($path);
        $draft = $this->draft($catalog);

        if (is_file($path) && ! $force) {
            $existing = $this->load($catalog->connection);
            $draft = array_replace_recursive($draft, $existing);
        }

        $directory = dirname($path);
        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        file_put_contents($path, $this->phpArray($draft), LOCK_EX);

        return ['path' => $path, 'created' => $created];
    }

    public function path(string $connection): string
    {
        $basePath = $this->config->get('agent-protocol.schema_discovery.config_path');
        $basePath = is_string($basePath) && $basePath !== '' ? $basePath : (getcwd() ?: sys_get_temp_dir());
        $filename = preg_replace('/[^A-Za-z0-9_.-]+/', '_', $connection) ?: $connection;

        return rtrim($basePath, DIRECTORY_SEPARATOR.'/\\').DIRECTORY_SEPARATOR.$filename.'.php';
    }

    /**
     * @return array<string, mixed>
     */
    private function configOverride(string $connection): array
    {
        $connections = $this->config->get('agent-protocol.schema_discovery.connections', []);
        if (! is_array($connections)) {
            return [];
        }

        $override = $connections[$connection] ?? [];

        return is_array($override) ? $override : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function draft(SchemaCatalogDescriptor $catalog): array
    {
        $tables = [];
        $columns = [];

        foreach ($catalog->tables as $table) {
            $tables[$table->name] = array_filter([
                'module' => $table->module,
                'label' => $table->label,
                'description' => $table->description,
                'expose' => $table->expose,
                'cacheable' => $table->cacheable,
                'reference_table' => $table->referenceTable,
                'resource' => $table->resource,
                'lookup_field' => $table->lookupField,
                'max_records' => $table->maxRecords,
            ], fn (mixed $value): bool => $value !== null);

            foreach ($table->columns as $column) {
                $columns[$table->name.'.'.$column->name] = array_filter([
                    'label' => $column->label,
                    'description' => $column->description,
                    'sensitive' => $column->sensitive ?: null,
                    'references' => $column->references,
                ], fn (mixed $value): bool => $value !== null && $value !== []);
            }
        }

        return [
            'module' => $this->moduleFromConnection($catalog->connection),
            'tables' => $tables,
            'columns' => $columns,
        ];
    }

    private function moduleFromConnection(string $connection): string
    {
        return str_replace(['-', '.'], '_', strtolower($connection));
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function phpArray(array $values): string
    {
        return "<?php\n\nreturn ".var_export($values, true).";\n";
    }
}
