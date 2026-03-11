<?php

namespace FluentToolkit\AI\Drivers\Cart;

class QueryOrdersAggregate extends AggregateToolBase
{
    private const METRICS = [
        'order_count',
        'gross_revenue',
        'paid_revenue',
        'refunded_amount',
        'manual_discount_total',
        'coupon_discount_total',
        'total_discount',
        'average_order_value',
        'unique_customers',
    ];

    private const DIMENSIONS = [
        'day',
        'week',
        'month',
        'status',
        'payment_status',
        'payment_method',
        'utm_campaign',
        'utm_source',
        'utm_medium',
        'utm_content',
        'utm_id',
        'country',
        'state',
        'currency',
    ];

    public function getName(): string
    {
        return 'query_orders_aggregate';
    }

    public function getDefinition(): array
    {
        return [
            'type' => 'function',
            'name' => $this->getName(),
            'description' => 'Aggregate order metrics by one or more dimensions. Use this for custom breakdowns like revenue by month, discounts by month, orders by payment method, customer geography, or marketing attribution by utm_campaign, utm_source, utm_medium, utm_content, or utm_id.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'metrics' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'string',
                            'enum' => self::METRICS,
                        ],
                        'description' => 'Metrics to return.',
                    ],
                    'dimensions' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'string',
                            'enum' => self::DIMENSIONS,
                        ],
                        'description' => 'Dimensions to group by.',
                    ],
                    'filters' => [
                        'type' => 'object',
                        'properties' => [
                            'start_date' => ['type' => 'string'],
                            'end_date' => ['type' => 'string'],
                            'order_statuses' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'payment_statuses' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'payment_methods' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'order_types' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'utm_campaigns' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'utm_sources' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'utm_mediums' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'utm_contents' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'utm_ids' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'include_unknown_attribution' => ['type' => 'boolean'],
                            'countries' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'states' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'currencies' => ['type' => 'array', 'items' => ['type' => 'string']],
                        ],
                        'additionalProperties' => false,
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Maximum number of rows to return. Defaults to 50.',
                    ],
                    'sort_by' => [
                        'type' => 'string',
                        'description' => 'Metric or dimension to sort by.',
                    ],
                    'sort_direction' => [
                        'type' => 'string',
                        'description' => 'asc or desc. Defaults to desc.',
                    ],
                ],
                'required' => ['metrics'],
                'additionalProperties' => false,
            ],
            'strict' => false,
        ];
    }

    public function execute(array $arguments)
    {
        $metrics = $this->normalizeEnumList($arguments['metrics'] ?? [], self::METRICS, ['order_count']);
        $dimensions = $this->normalizeEnumList($arguments['dimensions'] ?? [], self::DIMENSIONS);
        $filters = is_array($arguments['filters'] ?? null) ? $arguments['filters'] : [];
        $limit = $this->sanitizeLimit($arguments['limit'] ?? 50, 50, 200);
        $sortBy = $this->normalizeOptionalString($arguments['sort_by'] ?? null);
        $sortDirection = $this->sanitizeSortDirection($arguments['sort_direction'] ?? 'desc');

        $dateExpression = 'COALESCE(o.completed_at, o.created_at)';
        $metricExpressions = [
            'order_count' => 'COUNT(DISTINCT o.id) AS order_count',
            'gross_revenue' => 'ROUND(COALESCE(SUM(o.total_amount), 0) / 100, 2) AS gross_revenue',
            'paid_revenue' => 'ROUND(COALESCE(SUM(o.total_paid), 0) / 100, 2) AS paid_revenue',
            'refunded_amount' => 'ROUND(COALESCE(SUM(o.total_refund), 0) / 100, 2) AS refunded_amount',
            'manual_discount_total' => 'ROUND(COALESCE(SUM(o.manual_discount_total), 0) / 100, 2) AS manual_discount_total',
            'coupon_discount_total' => 'ROUND(COALESCE(SUM(o.coupon_discount_total), 0) / 100, 2) AS coupon_discount_total',
            'total_discount' => 'ROUND(COALESCE(SUM(o.manual_discount_total + o.coupon_discount_total), 0) / 100, 2) AS total_discount',
            'average_order_value' => 'ROUND(COALESCE(AVG(o.total_amount), 0) / 100, 2) AS average_order_value',
            'unique_customers' => 'COUNT(DISTINCT o.customer_id) AS unique_customers',
        ];
        $dimensionExpressions = [
            'day' => "DATE_FORMAT({$dateExpression}, '%Y-%m-%d') AS day",
            'week' => "DATE_FORMAT({$dateExpression}, '%x-W%v') AS week",
            'month' => "DATE_FORMAT({$dateExpression}, '%Y-%m') AS month",
            'status' => 'o.status AS status',
            'payment_status' => 'o.payment_status AS payment_status',
            'payment_method' => "COALESCE(NULLIF(o.payment_method_title, ''), o.payment_method) AS payment_method",
            'utm_campaign' => "COALESCE(NULLIF(oo.utm_campaign, ''), 'Unknown') AS utm_campaign",
            'utm_source' => "COALESCE(NULLIF(oo.utm_source, ''), 'Unknown') AS utm_source",
            'utm_medium' => "COALESCE(NULLIF(oo.utm_medium, ''), 'Unknown') AS utm_medium",
            'utm_content' => "COALESCE(NULLIF(oo.utm_content, ''), 'Unknown') AS utm_content",
            'utm_id' => "COALESCE(NULLIF(oo.utm_id, ''), 'Unknown') AS utm_id",
            'country' => "COALESCE(NULLIF(c.country, ''), 'Unknown') AS country",
            'state' => "COALESCE(NULLIF(c.state, ''), 'Unknown') AS state",
            'currency' => 'o.currency AS currency',
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

        $this->addDateRangeClause(
            $dateExpression,
            $this->sanitizeDate($filters['start_date'] ?? null),
            $this->sanitizeDate($filters['end_date'] ?? null),
            $where,
            $params
        );

        $orderTypes = $this->normalizeStringList($filters['order_types'] ?? []);
        if (!$orderTypes) {
            $orderTypes = ['payment'];
        }
        $this->addInClause('o.type', $orderTypes, $where, $params);
        $this->addInClause('o.status', $this->normalizeStringList($filters['order_statuses'] ?? []), $where, $params);
        $paymentStatuses = $this->normalizeStringList($filters['payment_statuses'] ?? []);
        if (
            !$paymentStatuses &&
            !in_array('payment_status', $dimensions, true) &&
            array_intersect($metrics, ['gross_revenue', 'paid_revenue', 'refunded_amount', 'average_order_value'])
        ) {
            $paymentStatuses = ['paid', 'partially_paid', 'partially_refunded'];
        }
        $this->addInClause('o.payment_status', $paymentStatuses, $where, $params);
        $this->addInClause('o.payment_method', $this->normalizeStringList($filters['payment_methods'] ?? []), $where, $params);
        $this->addInClause('oo.utm_campaign', $this->normalizeStringList($filters['utm_campaigns'] ?? []), $where, $params);
        $this->addInClause('oo.utm_source', $this->normalizeStringList($filters['utm_sources'] ?? []), $where, $params);
        $this->addInClause('oo.utm_medium', $this->normalizeStringList($filters['utm_mediums'] ?? []), $where, $params);
        $this->addInClause('oo.utm_content', $this->normalizeStringList($filters['utm_contents'] ?? []), $where, $params);
        $this->addInClause('oo.utm_id', $this->normalizeStringList($filters['utm_ids'] ?? []), $where, $params);
        $this->addInClause('c.country', $this->normalizeStringList($filters['countries'] ?? []), $where, $params);
        $this->addInClause('c.state', $this->normalizeStringList($filters['states'] ?? []), $where, $params);
        $this->addInClause('o.currency', $this->normalizeStringList($filters['currencies'] ?? []), $where, $params);

        $utmDimensions = array_values(array_intersect($dimensions, ['utm_campaign', 'utm_source', 'utm_medium', 'utm_content', 'utm_id']));
        $includeUnknownAttribution = !empty($filters['include_unknown_attribution']);
        if ($utmDimensions && !$includeUnknownAttribution) {
            foreach ($utmDimensions as $utmDimension) {
                $where[] = sprintf("NULLIF(oo.%s, '') IS NOT NULL", $utmDimension);
            }
        }

        $sql = 'SELECT ' . implode(', ', $selectParts) . "
            FROM {$this->table('fct_orders')} o
            LEFT JOIN {$this->table('fct_customers')} c ON c.id = o.customer_id
            LEFT JOIN {$this->table('fct_order_operations')} oo ON oo.order_id = o.id";

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
