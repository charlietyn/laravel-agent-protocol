<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\SchemaDiscovery;

use Ronu\LaravelAgentProtocol\DTO\SchemaCatalogDescriptor;
use Ronu\LaravelAgentProtocol\DTO\SchemaColumnDescriptor;
use Ronu\LaravelAgentProtocol\DTO\SchemaTableDescriptor;

final class SchemaMarkdownExporter
{
    public function export(SchemaCatalogDescriptor $catalog): string
    {
        $lines = [
            '# Database Schema Catalog',
            '',
            '- Connection: `'.$catalog->connection.'`',
            '- Driver: `'.$catalog->driver.'`',
            '- Database: `'.($catalog->database ?? 'unknown').'`',
            '- Tables: '.count($catalog->baseTables()),
            '- Views: '.count($catalog->views()),
            '- Cacheable candidates: '.count($catalog->cacheableTables()),
            '- Reference-table candidates: '.count($catalog->referenceTables()),
            '',
        ];

        foreach ($catalog->tables as $table) {
            array_push($lines, ...$this->table($table));
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * @return array<int, string>
     */
    private function table(SchemaTableDescriptor $table): array
    {
        $lines = [
            '## '.$table->name,
            '',
            '- Type: `'.$table->type.'`',
            '- Module: `'.($table->module ?? 'none').'`',
            '- Cacheable: '.($table->cacheable ? 'yes' : 'no'),
            '- Reference table: '.($table->referenceTable ? 'yes' : 'no'),
            '- Lookup field: `'.($table->lookupField ?? 'none').'`',
            '- Description: '.($table->description ?? 'none'),
            '',
            '| Column | Type | Nullable | Sensitive | Description |',
            '|---|---|---:|---:|---|',
        ];

        foreach ($table->columns as $column) {
            $lines[] = $this->column($column);
        }

        $lines[] = '';

        return $lines;
    }

    private function column(SchemaColumnDescriptor $column): string
    {
        return sprintf(
            '| `%s` | `%s` | %s | %s | %s |',
            $column->name,
            $column->nativeType ?? $column->type,
            $column->nullable === true ? 'yes' : 'no',
            $column->sensitive ? 'yes' : 'no',
            str_replace('|', '\\|', $column->description ?? ''),
        );
    }
}
