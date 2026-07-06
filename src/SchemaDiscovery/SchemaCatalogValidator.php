<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\SchemaDiscovery;

use Ronu\LaravelAgentProtocol\DTO\SchemaCatalogDescriptor;
use Ronu\LaravelAgentProtocol\DTO\SchemaTableDescriptor;

final class SchemaCatalogValidator
{
    /**
     * @return array<int, string>
     */
    public function validate(SchemaCatalogDescriptor $catalog): array
    {
        $errors = [];

        foreach ($catalog->tables as $table) {
            if (($table->expose || $table->cacheable || $table->referenceTable)
                && ($table->description === null || $table->description === '')) {
                $errors[] = "Table [{$table->name}] is published or cacheable but has no description.";
            }

            if ($table->referenceTable && ($table->lookupField === null || ! $this->hasColumn($table, $table->lookupField))) {
                $errors[] = "Reference table [{$table->name}] must define a valid lookup_field.";
            }

            if (($table->cacheable || $table->referenceTable) && $this->hasSensitiveColumn($table)) {
                $errors[] = "Table [{$table->name}] is cacheable/reference but includes sensitive columns.";
            }
        }

        return $errors;
    }

    private function hasColumn(SchemaTableDescriptor $table, string $columnName): bool
    {
        foreach ($table->columns as $column) {
            if ($column->name === $columnName) {
                return true;
            }
        }

        return false;
    }

    private function hasSensitiveColumn(SchemaTableDescriptor $table): bool
    {
        foreach ($table->columns as $column) {
            if ($column->sensitive) {
                return true;
            }
        }

        return false;
    }
}
