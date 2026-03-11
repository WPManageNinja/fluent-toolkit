<?php

namespace FluentToolkit\AI\Drivers\Cart;

class QueryProductsAggregate extends AggregateToolBase
{
    private const METRICS = [
        'units_sold',
        'line_revenue',
        'order_count',
        'average_unit_price',
        'refund_amount',
        'unique_customers',
    ];

    private const DIMENSIONS = [
        'product_id',
        'product_name',
        'variation_id',
        'day',
        'week',
        'month',
        'order_status',
        'payment_status',
        'payment_method',
    ];

    public function getName(): string
    {
        return 'query_products_aggregate';
    }

    public function getDefinition(): array
    {
        return [
            'type' => 'function',
            'name' => $this->getName(),
            'description' => 'Aggregate sold-product and line-item metrics from order items by dimensions such as product, month, payment status, or payment method. Use this for sales performance, not for total catalog product counts.',
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
                            'start_date' => ['type' => 'string'],
                            'end_date' => ['type' => 'string'],
                            'product_ids' => ['type' => 'array', 'items' => ['type' => 'integer']],
                            'product_name_contains' => ['type' => 'string'],
                            'order_statuses' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'payment_statuses' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'payment_methods' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'order_types' => ['type' => 'array', 'items' => ['type' => 'string']],
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
        $metrics = $this->normalizeEnumList($arguments['metrics'] ?? [], self::METRICS, ['units_sold']);
        $dimensions = $this->normalizeEnumList($arguments['dimensions'] ?? [], self::DIMENSIONS, ['product_name']);
        $filters = is_array($arguments['filters'] ?? null) ? $arguments['filters'] : [];
        $limit = $this->sanitizeLimit($arguments['limit'] ?? 50, 50, 200);
        $sortBy = $this->normalizeOptionalString($arguments['sort_by'] ?? null);
        $sortDirection = $this->sanitizeSortDirection($arguments['sort_direction'] ?? 'desc');

        $dateExpression = 'COALESCE(o.completed_at, o.created_at)';
        $metricExpressions = [
            'units_sold' => 'SUM(oi.quantity) AS units_sold',
            'line_revenue' => 'ROUND(COALESCE(SUM(oi.line_total), 0) / 100, 2) AS line_revenue',
            'order_count' => 'COUNT(DISTINCT oi.order_id) AS order_count',
            'average_unit_price' => 'ROUND(COALESCE(AVG(oi.unit_price), 0) / 100, 2) AS average_unit_price',
            'refund_amount' => 'ROUND(COALESCE(SUM(oi.refund_total), 0) / 100, 2) AS refund_amount',
            'unique_customers' => 'COUNT(DISTINCT o.customer_id) AS unique_customers',
        ];
        $dimensionExpressions = [
            'product_id' => 'oi.post_id AS product_id',
            'product_name' => 'oi.post_title AS product_name',
            'variation_id' => 'oi.object_id AS variation_id',
            'day' => "DATE_FORMAT({$dateExpression}, '%Y-%m-%d') AS day",
            'week' => "DATE_FORMAT({$dateExpression}, '%x-W%v') AS week",
            'month' => "DATE_FORMAT({$dateExpression}, '%Y-%m') AS month",
            'order_status' => 'o.status AS order_status',
            'payment_status' => 'o.payment_status AS payment_status',
            'payment_method' => "COALESCE(NULLIF(o.payment_method_title, ''), o.payment_method) AS payment_method",
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
            array_intersect($metrics, ['units_sold', 'line_revenue', 'average_unit_price', 'refund_amount'])
        ) {
            $paymentStatuses = ['paid', 'partially_paid', 'partially_refunded'];
        }
        $this->addInClause('o.payment_status', $paymentStatuses, $where, $params);
        $this->addInClause('o.payment_method', $this->normalizeStringList($filters['payment_methods'] ?? []), $where, $params);
        $this->addInClause('oi.post_id', $this->normalizeIntList($filters['product_ids'] ?? []), $where, $params, '%d');

        $productNameContains = $this->normalizeOptionalString($filters['product_name_contains'] ?? null);
        if ($productNameContains) {
            $where[] = 'oi.post_title LIKE %s';
            $params[] = '%' . $this->wpdb->esc_like($productNameContains) . '%';
        }

        $sql = 'SELECT ' . implode(', ', $selectParts) . "
            FROM {$this->table('fct_order_items')} oi
            INNER JOIN {$this->table('fct_orders')} o ON o.id = oi.order_id";

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
