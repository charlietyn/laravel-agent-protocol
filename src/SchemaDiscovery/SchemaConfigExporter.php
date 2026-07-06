<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\SchemaDiscovery;

use Illuminate\Support\Str;
use Ronu\LaravelAgentProtocol\DTO\SchemaCatalogDescriptor;
use Ronu\LaravelAgentProtocol\DTO\SchemaColumnDescriptor;
use Ronu\LaravelAgentProtocol\DTO\SchemaTableDescriptor;

final class SchemaConfigExporter
{
    public function referenceTables(SchemaCatalogDescriptor $catalog): string
    {
        return "<?php\n\nreturn [\n    'reference_tables' => ".$this->exportArray($this->referenceTableArray($catalog), 1).",\n];\n";
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function referenceTableArray(SchemaCatalogDescriptor $catalog): array
    {
        $tables = [];

        foreach ($catalog->referenceTables() as $table) {
            $fields = $this->referenceFields($table);
            $tables[$table->name] = array_filter([
                'resource' => $table->resource ?? $this->resourceName($table),
                'fields' => $fields,
                'lookup_field' => $table->lookupField,
                'foreign_keys' => $this->foreignKeysReferencing($catalog, $table),
                'max_records' => $table->maxRecords,
            ], fn (mixed $value): bool => $value !== null && $value !== []);
        }

        return $tables;
    }

    private function resourceName(SchemaTableDescriptor $table): string
    {
        $module = $table->module ?? '--site--';
        $name = Str::of($table->name)->singular()->replace('_', '-')->toString();

        return $module.'.'.$name;
    }

    /**
     * @return array<int, string>
     */
    private function referenceFields(SchemaTableDescriptor $table): array
    {
        $fields = ['id'];
        if ($table->lookupField && $table->lookupField !== 'id') {
            $fields[] = $table->lookupField;
        }

        return array_values(array_unique($fields));
    }

    /**
     * @return array<int, string>
     */
    private function foreignKeysReferencing(SchemaCatalogDescriptor $catalog, SchemaTableDescriptor $referenceTable): array
    {
        $keys = [];
        foreach ($catalog->tables as $table) {
            foreach ($table->columns as $column) {
                if ($this->referencesTable($column, $referenceTable->name)) {
                    $keys[] = $column->name;
                }
            }
        }

        if ($keys === []) {
            $keys[] = Str::of($referenceTable->name)->singular()->toString().'_id';
        }

        return array_values(array_unique($keys));
    }

    private function referencesTable(SchemaColumnDescriptor $column, string $table): bool
    {
        $foreignTable = $column->references['foreign_table'] ?? null;

        return is_string($foreignTable) && $foreignTable === $table;
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function exportArray(array $value, int $indent): string
    {
        $exported = var_export($value, true);
        $padding = str_repeat('    ', $indent);

        return str_replace("\n", "\n".$padding, $exported);
    }
}
