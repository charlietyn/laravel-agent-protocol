<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\SchemaDiscovery;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Str;
use Ronu\LaravelAgentProtocol\DTO\SchemaColumnDescriptor;
use Ronu\LaravelAgentProtocol\DTO\SchemaTableDescriptor;

final readonly class CacheableTableClassifier
{
    public function __construct(
        private ConfigRepository $config,
    ) {}

    public function classify(SchemaTableDescriptor $table): SchemaTableDescriptor
    {
        $maxRecords = $table->maxRecords ?? $this->positiveInt($this->config->get('agent-protocol.schema_discovery.cacheable_row_limit'), 100);
        $lookupField = $table->lookupField ?? $this->lookupField($table);

        $cacheable = $table->cacheable || $this->looksCacheable($table, $maxRecords);
        $referenceTable = $table->referenceTable || ($cacheable && $lookupField !== null);

        return $table->withClassification($cacheable, $referenceTable, $lookupField, $maxRecords);
    }

    public function sensitiveColumn(SchemaColumnDescriptor $column): bool
    {
        $patterns = $this->stringList($this->config->get('agent-protocol.schema_discovery.sensitive_column_patterns', []));

        foreach ($patterns as $pattern) {
            if (Str::is($pattern, $column->name)) {
                return true;
            }
        }

        return $column->sensitive;
    }

    private function looksCacheable(SchemaTableDescriptor $table, int $maxRecords): bool
    {
        if ($table->type !== 'table') {
            return false;
        }

        if ($table->rowEstimate !== null && $table->rowEstimate > $maxRecords) {
            return false;
        }

        foreach ($this->stringList($this->config->get('agent-protocol.schema_discovery.cacheable_name_patterns', [])) as $pattern) {
            if (Str::is($pattern, $table->name)) {
                return true;
            }
        }

        return $table->rowEstimate !== null
            && $table->rowEstimate <= $maxRecords
            && count($table->columns) <= 8
            && $this->lookupField($table) !== null;
    }

    private function lookupField(SchemaTableDescriptor $table): ?string
    {
        $preferred = ['name', 'label', 'title', 'code', 'slug', 'description'];
        $columns = array_map(fn (SchemaColumnDescriptor $column): string => $column->name, $table->columns);

        foreach ($preferred as $field) {
            if (in_array($field, $columns, true)) {
                return $field;
            }
        }

        return null;
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

    private function positiveInt(mixed $value, int $default): int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : $default;
    }
}
