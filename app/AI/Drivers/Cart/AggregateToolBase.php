<?php

namespace FluentToolkit\AI\Drivers\Cart;

abstract class AggregateToolBase extends ToolBase
{
    protected function normalizeEnumList($value, array $allowed, array $default = []): array
    {
        $values = $this->normalizeStringList($value);
        $values = array_values(array_intersect($values, $allowed));

        if (!$values) {
            return $default;
        }

        return array_values(array_unique($values));
    }

    protected function normalizeStringList($value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $items = [];

        foreach ($value as $item) {
            if (!is_scalar($item)) {
                continue;
            }

            $item = trim(sanitize_text_field((string) $item));
            if ($item === '') {
                continue;
            }

            $items[] = $item;
        }

        return $items;
    }

    protected function normalizeIntList($value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $items = [];

        foreach ($value as $item) {
            $item = absint($item);
            if ($item < 1) {
                continue;
            }

            $items[] = $item;
        }

        return array_values(array_unique($items));
    }

    protected function normalizeOptionalString($value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $value = trim(sanitize_text_field((string) $value));

        return $value === '' ? null : $value;
    }

    protected function sanitizeSortDirection($value): string
    {
        return strtolower((string) $value) === 'asc' ? 'ASC' : 'DESC';
    }

    protected function addInClause(string $column, array $values, array &$where, array &$params, string $placeholder = '%s'): void
    {
        if (!$values) {
            return;
        }

        $where[] = sprintf(
            '%s IN (%s)',
            $column,
            implode(', ', array_fill(0, count($values), $placeholder))
        );

        foreach ($values as $value) {
            $params[] = $value;
        }
    }

    protected function addDateRangeClause(string $dateExpression, ?string $startDate, ?string $endDate, array &$where, array &$params): void
    {
        if ($startDate) {
            $where[] = sprintf('DATE(%s) >= %%s', $dateExpression);
            $params[] = $startDate;
        }

        if ($endDate) {
            $where[] = sprintf('DATE(%s) <= %%s', $dateExpression);
            $params[] = $endDate;
        }
    }

    protected function addMinimumNumericClause(string $column, $value, array &$where, array &$params): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (!is_numeric($value)) {
            return;
        }

        $where[] = sprintf('%s >= %%d', $column);
        $params[] = (int) $value;
    }

    protected function buildOrderBy(array $dimensions, array $metrics, array $allowedSorts, ?string $sortBy, string $direction): string
    {
        if ($sortBy && isset($allowedSorts[$sortBy])) {
            return sprintf('ORDER BY %s %s', $allowedSorts[$sortBy], $direction);
        }

        if ($metrics) {
            $firstMetric = $metrics[0];
            if (isset($allowedSorts[$firstMetric])) {
                return sprintf('ORDER BY %s %s', $allowedSorts[$firstMetric], $direction);
            }
        }

        if ($dimensions) {
            $parts = [];
            foreach ($dimensions as $dimension) {
                if (isset($allowedSorts[$dimension])) {
                    $parts[] = $allowedSorts[$dimension] . ' ASC';
                }
            }

            if ($parts) {
                return 'ORDER BY ' . implode(', ', $parts);
            }
        }

        return '';
    }

    protected function runPreparedResults(string $sql, array $params = []): array
    {
        $prepared = $params ? $this->wpdb->prepare($sql, ...$params) : $sql;
        $results = $this->wpdb->get_results($prepared, ARRAY_A);

        if ($this->wpdb->last_error) {
            return [new \WP_Error('aggregate_query_failed', $this->wpdb->last_error)];
        }

        return $results ?: [];
    }
}
