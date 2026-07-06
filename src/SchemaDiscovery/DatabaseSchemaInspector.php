<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\SchemaDiscovery;

use DateTimeImmutable;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;
use Ronu\LaravelAgentProtocol\DTO\SchemaCatalogDescriptor;
use Ronu\LaravelAgentProtocol\DTO\SchemaColumnDescriptor;
use Ronu\LaravelAgentProtocol\DTO\SchemaTableDescriptor;

final readonly class DatabaseSchemaInspector
{
    public function __construct(
        private DatabaseManager $database,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     */
    public function inspect(?string $connectionName = null, array $options = []): SchemaCatalogDescriptor
    {
        $connection = $this->database->connection($connectionName);
        $resolvedConnectionName = $connectionName ?: $connection->getName();
        $resolvedConnectionName = is_string($resolvedConnectionName) && $resolvedConnectionName !== ''
            ? $resolvedConnectionName
            : 'default';
        $includeViews = (bool) ($options['include_views'] ?? true);
        $estimateRows = (bool) ($options['estimate_rows'] ?? false);
        $includeTables = $this->stringList($options['include_tables'] ?? []);
        $excludeTables = $this->stringList($options['exclude_tables'] ?? []);

        $tables = [];
        foreach ($this->tables($connection, false) as $tableName => $tableMeta) {
            if (! $this->shouldInclude($tableName, $includeTables, $excludeTables)) {
                continue;
            }

            $tables[] = $this->table($connection, $tableName, 'table', $tableMeta, $estimateRows);
        }

        if ($includeViews) {
            foreach ($this->tables($connection, true) as $viewName => $viewMeta) {
                if (! $this->shouldInclude($viewName, $includeTables, $excludeTables)) {
                    continue;
                }

                $tables[] = $this->table($connection, $viewName, 'view', $viewMeta, false);
            }
        }

        usort($tables, fn (SchemaTableDescriptor $left, SchemaTableDescriptor $right): int => [$left->type, $left->name] <=> [$right->type, $right->name]);

        return new SchemaCatalogDescriptor(
            connection: $resolvedConnectionName,
            driver: $connection->getDriverName(),
            database: method_exists($connection, 'getDatabaseName') ? $connection->getDatabaseName() : null,
            generatedAt: new DateTimeImmutable,
            tables: $tables,
            meta: [
                'source' => 'database',
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $tableMeta
     */
    private function table(Connection $connection, string $name, string $type, array $tableMeta, bool $estimateRows): SchemaTableDescriptor
    {
        $indexes = $this->indexes($connection, $name);
        $foreignKeys = $this->foreignKeys($connection, $name);
        $primaryKey = $this->primaryKey($indexes);
        $indexedColumns = $this->indexedColumns($indexes);
        $uniqueColumns = $this->uniqueColumns($indexes);
        $foreignKeyByColumn = $this->foreignKeyByColumn($foreignKeys);

        $columns = array_map(
            fn (array $column): SchemaColumnDescriptor => $this->column(
                $column,
                $primaryKey,
                $indexedColumns,
                $uniqueColumns,
                $foreignKeyByColumn,
            ),
            $this->columns($connection, $name),
        );

        return new SchemaTableDescriptor(
            name: $name,
            type: $type,
            schema: $this->stringOrNull($tableMeta['schema'] ?? null),
            label: Str::of($name)->replace(['_', '-'], ' ')->headline()->toString(),
            description: $type === 'view'
                ? "View {$name} discovered from the database schema."
                : "Table {$name} discovered from the database schema.",
            descriptionSource: 'inferred',
            rowEstimate: $estimateRows && $type === 'table' ? $this->rowEstimate($connection, $name) : null,
            columns: $columns,
            primaryKey: $primaryKey,
            indexes: $indexes,
            foreignKeys: $foreignKeys,
            meta: [
                'source' => 'database',
                'raw' => $tableMeta,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $column
     * @param  array<int, string>  $primaryKey
     * @param  array<string, bool>  $indexedColumns
     * @param  array<string, bool>  $uniqueColumns
     * @param  array<string, array<string, mixed>>  $foreignKeyByColumn
     */
    private function column(
        array $column,
        array $primaryKey,
        array $indexedColumns,
        array $uniqueColumns,
        array $foreignKeyByColumn,
    ): SchemaColumnDescriptor {
        $name = $this->stringOrDefault($column['name'] ?? null, 'unknown');
        $nativeType = $this->stringOrNull($column['type_name'] ?? $column['type'] ?? null);

        return new SchemaColumnDescriptor(
            name: $name,
            type: $this->logicalType($nativeType),
            nativeType: $nativeType,
            nullable: array_key_exists('nullable', $column) ? (bool) $column['nullable'] : null,
            default: $column['default'] ?? null,
            autoIncrement: (bool) ($column['auto_increment'] ?? false),
            primary: in_array($name, $primaryKey, true),
            unique: isset($uniqueColumns[$name]),
            indexed: isset($indexedColumns[$name]),
            label: Str::of($name)->replace(['_', '-'], ' ')->headline()->toString(),
            description: "Column {$name} discovered from the database schema.",
            descriptionSource: 'inferred',
            references: $foreignKeyByColumn[$name] ?? [],
            meta: [
                'raw' => $column,
            ],
        );
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function tables(Connection $connection, bool $views): array
    {
        $schema = $connection->getSchemaBuilder();
        $method = $views ? 'getViews' : 'getTables';

        try {
            if (method_exists($schema, $method)) {
                return $this->namedRecords($schema->{$method}());
            }
        } catch (\Throwable) {
            // Fall back below.
        }

        if ($connection->getDriverName() === 'sqlite') {
            return $this->sqliteTables($connection, $views);
        }

        if ($views) {
            return [];
        }

        return [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function columns(Connection $connection, string $table): array
    {
        $schema = $connection->getSchemaBuilder();

        try {
            if (method_exists($schema, 'getColumns')) {
                return $this->records($schema->getColumns($table));
            }
        } catch (\Throwable) {
            // Fall back below.
        }

        try {
            return array_map(
                fn (string $column): array => [
                    'name' => $column,
                    'type' => method_exists($schema, 'getColumnType') ? $schema->getColumnType($table, $column) : null,
                ],
                $schema->getColumnListing($table),
            );
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function indexes(Connection $connection, string $table): array
    {
        $schema = $connection->getSchemaBuilder();

        try {
            if (method_exists($schema, 'getIndexes')) {
                return $this->records($schema->getIndexes($table));
            }
        } catch (\Throwable) {
            // Unsupported driver or grammar.
        }

        return [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function foreignKeys(Connection $connection, string $table): array
    {
        $schema = $connection->getSchemaBuilder();

        try {
            if (method_exists($schema, 'getForeignKeys')) {
                return $this->records($schema->getForeignKeys($table));
            }
        } catch (\Throwable) {
            // Fall back below.
        }

        if ($connection->getDriverName() === 'sqlite') {
            return $this->sqliteForeignKeys($connection, $table);
        }

        return [];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function sqliteTables(Connection $connection, bool $views): array
    {
        $type = $views ? 'view' : 'table';
        $rows = $connection->select(
            "select name, type from sqlite_master where type = ? and name not like 'sqlite_%' order by name",
            [$type],
        );

        $tables = [];
        foreach ($rows as $row) {
            $record = (array) $row;
            $name = $this->stringOrNull($record['name'] ?? null);
            if ($name) {
                $tables[$name] = $record;
            }
        }

        return $tables;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function sqliteForeignKeys(Connection $connection, string $table): array
    {
        $keys = [];
        foreach ($connection->select('pragma foreign_key_list('.$connection->getQueryGrammar()->wrapTable($table).')') as $row) {
            $record = (array) $row;
            $keys[] = [
                'name' => 'fk_'.$table.'_'.($record['from'] ?? 'unknown'),
                'columns' => [(string) ($record['from'] ?? '')],
                'foreign_table' => (string) ($record['table'] ?? ''),
                'foreign_columns' => [(string) ($record['to'] ?? '')],
            ];
        }

        return $keys;
    }

    private function rowEstimate(Connection $connection, string $table): ?int
    {
        try {
            return (int) $connection->table($table)->count();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $indexes
     * @return array<int, string>
     */
    private function primaryKey(array $indexes): array
    {
        foreach ($indexes as $index) {
            if (($index['primary'] ?? false) === true) {
                return $this->stringList($index['columns'] ?? []);
            }
        }

        return [];
    }

    /**
     * @param  array<int, array<string, mixed>>  $indexes
     * @return array<string, bool>
     */
    private function indexedColumns(array $indexes): array
    {
        $columns = [];
        foreach ($indexes as $index) {
            foreach ($this->stringList($index['columns'] ?? []) as $column) {
                $columns[$column] = true;
            }
        }

        return $columns;
    }

    /**
     * @param  array<int, array<string, mixed>>  $indexes
     * @return array<string, bool>
     */
    private function uniqueColumns(array $indexes): array
    {
        $columns = [];
        foreach ($indexes as $index) {
            if (($index['unique'] ?? false) !== true) {
                continue;
            }

            foreach ($this->stringList($index['columns'] ?? []) as $column) {
                $columns[$column] = true;
            }
        }

        return $columns;
    }

    /**
     * @param  array<int, array<string, mixed>>  $foreignKeys
     * @return array<string, array<string, mixed>>
     */
    private function foreignKeyByColumn(array $foreignKeys): array
    {
        $byColumn = [];
        foreach ($foreignKeys as $foreignKey) {
            foreach ($this->stringList($foreignKey['columns'] ?? []) as $column) {
                $byColumn[$column] = $foreignKey;
            }
        }

        return $byColumn;
    }

    private function logicalType(?string $nativeType): string
    {
        $type = strtolower($nativeType ?? '');

        if (str_contains($type, 'int')) {
            return 'integer';
        }

        if (str_contains($type, 'decimal') || str_contains($type, 'float') || str_contains($type, 'double') || str_contains($type, 'real')) {
            return 'number';
        }

        if (str_contains($type, 'bool')) {
            return 'boolean';
        }

        if (str_contains($type, 'date') || str_contains($type, 'time')) {
            return 'date-time';
        }

        if (str_contains($type, 'json')) {
            return 'object';
        }

        return $type !== '' ? 'string' : 'mixed';
    }

    /**
     * @param  array<int, string>  $includeTables
     * @param  array<int, string>  $excludeTables
     */
    private function shouldInclude(string $table, array $includeTables, array $excludeTables): bool
    {
        if ($includeTables !== [] && ! $this->matchesAny($table, $includeTables)) {
            return false;
        }

        return ! $this->matchesAny($table, $excludeTables);
    }

    /**
     * @param  array<int, string>  $patterns
     */
    private function matchesAny(string $value, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (Str::is($pattern, $value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, mixed>  $records
     * @return array<string, array<string, mixed>>
     */
    private function namedRecords(array $records): array
    {
        $named = [];
        foreach ($this->records($records) as $record) {
            $name = $this->stringOrNull($record['name'] ?? $record['table'] ?? null);
            if ($name !== null) {
                $named[$name] = $record;
            }
        }

        return $named;
    }

    /**
     * @param  array<int, mixed>  $records
     * @return array<int, array<string, mixed>>
     */
    private function records(array $records): array
    {
        return array_values(array_filter(array_map(
            fn (mixed $record): array => is_array($record) ? $record : (array) $record,
            $records,
        ), fn (array $record): bool => $record !== []));
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

    private function stringOrDefault(mixed $value, string $default): string
    {
        return is_string($value) && $value !== '' ? $value : $default;
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
