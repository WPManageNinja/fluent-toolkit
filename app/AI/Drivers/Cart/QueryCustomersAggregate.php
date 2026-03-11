<?php

namespace FluentToolkit\AI\Drivers\Cart;

class QueryCustomersAggregate extends AggregateToolBase
{
    private const METRICS = [
        'customer_count',
        'total_ltv',
        'average_ltv',
        'average_purchase_count',
        'repeat_customers',
    ];

    private const DIMENSIONS = [
        'country',
        'state',
        'city',
        'status',
        'first_purchase_month',
        'last_purchase_month',
    ];

    public function getName(): string
    {
        return 'query_customers_aggregate';
    }

    public function getDefinition(): array
    {
        return [
            'type' => 'function',
            'name' => $this->getName(),
            'description' => 'Aggregate customer data by location, lifecycle status, and purchase timing for questions about cohorts, repeat buyers, and customer value.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'metrics' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'string',
                            'enum' => self::METRICS,
                        ],
                    ],
                    'dimensions' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'string',
                            'enum' => self::DIMENSIONS,
                        ],
                    ],
                    'filters' => [
                        'type' => 'object',
                        'properties' => [
                            'countries' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'states' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'cities' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'statuses' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'first_purchase_start_date' => ['type' => 'string'],
                            'first_purchase_end_date' => ['type' => 'string'],
                            'last_purchase_start_date' => ['type' => 'string'],
                            'last_purchase_end_date' => ['type' => 'string'],
                            'min_purchase_count' => ['type' => 'integer'],
                            'min_ltv' => ['type' => 'integer'],
                        ],
                        'additionalProperties' => false,
                    ],
                    'limit' => ['type' => 'integer'],
                    'sort_by' => ['type' => 'string'],
                    'sort_direction' => ['type' => 'string'],
                ],
                'required' => ['metrics'],
                'additionalProperties' => false,
            ],
            'strict' => false,
        ];
    }

    public function execute(array $arguments)
    {
        $metrics = $this->normalizeEnumList($arguments['metrics'] ?? [], self::METRICS, ['customer_count']);
        $dimensions = $this->normalizeEnumList($arguments['dimensions'] ?? [], self::DIMENSIONS);
        $filters = is_array($arguments['filters'] ?? null) ? $arguments['filters'] : [];
        $limit = $this->sanitizeLimit($arguments['limit'] ?? 50, 50, 200);
        $sortBy = $this->normalizeOptionalString($arguments['sort_by'] ?? null);
        $sortDirection = $this->sanitizeSortDirection($arguments['sort_direction'] ?? 'desc');

        $metricExpressions = [
            'customer_count' => 'COUNT(*) AS customer_count',
            'total_ltv' => 'ROUND(COALESCE(SUM(c.ltv), 0) / 100, 2) AS total_ltv',
            'average_ltv' => 'ROUND(COALESCE(AVG(c.ltv), 0) / 100, 2) AS average_ltv',
            'average_purchase_count' => 'ROUND(COALESCE(AVG(c.purchase_count), 0), 2) AS average_purchase_count',
            'repeat_customers' => 'SUM(CASE WHEN c.purchase_count > 1 THEN 1 ELSE 0 END) AS repeat_customers',
        ];
        $dimensionExpressions = [
            'country' => "COALESCE(NULLIF(c.country, ''), 'Unknown') AS country",
            'state' => "COALESCE(NULLIF(c.state, ''), 'Unknown') AS state",
            'city' => "COALESCE(NULLIF(c.city, ''), 'Unknown') AS city",
            'status' => "COALESCE(NULLIF(c.status, ''), 'Unknown') AS status",
            'first_purchase_month' => "DATE_FORMAT(c.first_purchase_date, '%Y-%m') AS first_purchase_month",
            'last_purchase_month' => "DATE_FORMAT(c.last_purchase_date, '%Y-%m') AS last_purchase_month",
        ];

        $selectParts = [];
        foreach ($dimensions as $dimension) {
            $selectParts[] = $dimensionExpressions[$dimension];
        }
        foreach ($metrics as $metric) {
            $selectParts[] = $metricExpressions[$metric];
        }

        $where = [];
        $params = [];
        $this->addInClause('c.country', $this->normalizeStringList($filters['countries'] ?? []), $where, $params);
        $this->addInClause('c.state', $this->normalizeStringList($filters['states'] ?? []), $where, $params);
        $this->addInClause('c.city', $this->normalizeStringList($filters['cities'] ?? []), $where, $params);
        $this->addInClause('c.status', $this->normalizeStringList($filters['statuses'] ?? []), $where, $params);

        $this->addDateRangeClause(
            'c.first_purchase_date',
            $this->sanitizeDate($filters['first_purchase_start_date'] ?? null),
            $this->sanitizeDate($filters['first_purchase_end_date'] ?? null),
            $where,
            $params
        );
        $this->addDateRangeClause(
            'c.last_purchase_date',
            $this->sanitizeDate($filters['last_purchase_start_date'] ?? null),
            $this->sanitizeDate($filters['last_purchase_end_date'] ?? null),
            $where,
            $params
        );
        $this->addMinimumNumericClause('c.purchase_count', $filters['min_purchase_count'] ?? null, $where, $params);
        if (isset($filters['min_ltv']) && is_numeric($filters['min_ltv'])) {
            $where[] = 'c.ltv >= %d';
            $params[] = (int) round(((float) $filters['min_ltv']) * 100);
        }

        $sql = 'SELECT ' . implode(', ', $selectParts) . "
            FROM {$this->table('fct_customers')} c";

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        if ($dimensions) {
            $sql .= ' GROUP BY ' . implode(', ', $dimensions);
        }

        $allowedSorts = array_fill_keys(array_merge(self::DIMENSIONS, self::METRICS), null);
        foreach (array_keys($allowedSorts) as $key) {
            $allowedSorts[$key] = $key;
        }

        $orderBy = $this->buildOrderBy($dimensions, $metrics, $allowedSorts, $sortBy, $sortDirection);
        if ($orderBy !== '') {
            $sql .= ' ' . $orderBy;
        }

        $sql .= ' LIMIT %d';
        $params[] = $limit;

        $results = $this->runPreparedResults($sql, $params);
        if (isset($results[0]) && is_wp_error($results[0])) {
            return $results[0];
        }

        return [
            'metrics' => $metrics,
            'dimensions' => $dimensions,
            'filters' => $filters,
            'rows' => $results,
        ];
    }
}
