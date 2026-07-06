<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\SchemaDiscovery;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Ronu\LaravelAgentProtocol\DTO\SchemaCatalogDescriptor;
use Ronu\LaravelAgentProtocol\DTO\SchemaTableDescriptor;

final readonly class SchemaCatalogBuilder
{
    public function __construct(
        private DatabaseSchemaInspector $inspector,
        private SchemaOverrideRepository $overrides,
        private CacheableTableClassifier $classifier,
        private ConfigRepository $config,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     */
    public function build(?string $connection = null, array $options = []): SchemaCatalogDescriptor
    {
        $connection ??= $this->defaultConnection();
        $options = $this->options($options);
        $catalog = $this->inspector->inspect($connection, $options);
        $overrides = $this->overrides->load($catalog->connection);
        $module = $this->stringOrNull($overrides['module'] ?? null);
        $tables = [];

        foreach ($catalog->tables as $table) {
            $tables[] = $this->applyOverrides($table, $overrides, $module);
        }

        return new SchemaCatalogDescriptor(
            connection: $catalog->connection,
            driver: $catalog->driver,
            database: $catalog->database,
            generatedAt: $catalog->generatedAt,
            tables: $tables,
            meta: [
                ...$catalog->meta,
                'override_path' => $this->overrides->path($catalog->connection),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function applyOverrides(SchemaTableDescriptor $table, array $overrides, ?string $defaultModule): SchemaTableDescriptor
    {
        $tableOverrides = $this->tableOverrides($table, $overrides);
        $columnOverrides = $this->columnOverrides($table, $overrides);
        $columns = [];

        foreach ($table->columns as $column) {
            $column = $column->withOverrides($columnOverrides[$column->name] ?? []);
            if ($this->classifier->sensitiveColumn($column)) {
                $column = $column->withOverrides(['sensitive' => true]);
            }

            $columns[] = $column;
        }

        if ($defaultModule && ! isset($tableOverrides['module'])) {
            $tableOverrides['module'] = $defaultModule;
        }

        $table = $table->withOverrides($tableOverrides, $columns);

        return $this->classifier->classify($table);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function tableOverrides(SchemaTableDescriptor $table, array $overrides): array
    {
        $tables = $overrides['tables'] ?? [];
        if (! is_array($tables)) {
            return [];
        }

        $direct = $tables[$table->name] ?? [];
        $qualified = $table->schema ? ($tables[$table->schema.'.'.$table->name] ?? []) : [];

        return array_replace_recursive(
            is_array($direct) ? $direct : [],
            is_array($qualified) ? $qualified : [],
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, array<string, mixed>>
     */
    private function columnOverrides(SchemaTableDescriptor $table, array $overrides): array
    {
        $columns = $overrides['columns'] ?? [];
        if (! is_array($columns)) {
            return [];
        }

        $matched = [];
        foreach ($columns as $key => $definition) {
            if (! is_string($key) || ! is_array($definition)) {
                continue;
            }

            $prefixes = [$table->name.'.'];
            if ($table->schema) {
                $prefixes[] = $table->schema.'.'.$table->name.'.';
            }

            foreach ($prefixes as $prefix) {
                if (str_starts_with($key, $prefix)) {
                    $matched[substr($key, strlen($prefix))] = $definition;
                }
            }
        }

        return $matched;
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function options(array $options): array
    {
        return [
            'include_views' => array_key_exists('include_views', $options)
                ? (bool) $options['include_views']
                : (bool) $this->config->get('agent-protocol.schema_discovery.include_views', true),
            'estimate_rows' => array_key_exists('estimate_rows', $options)
                ? (bool) $options['estimate_rows']
                : (bool) $this->config->get('agent-protocol.schema_discovery.estimate_rows', false),
            'include_tables' => $this->stringList($options['include_tables'] ?? $this->config->get('agent-protocol.schema_discovery.include_tables', [])),
            'exclude_tables' => $this->stringList($options['exclude_tables'] ?? $this->config->get('agent-protocol.schema_discovery.exclude_tables', [])),
        ];
    }

    private function defaultConnection(): ?string
    {
        $configured = $this->config->get('agent-protocol.schema_discovery.default_connection')
            ?? $this->config->get('database.default');

        return is_string($configured) && $configured !== '' ? $configured : null;
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @return array<int, string>
     */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, is_string(...)));
    }
}
