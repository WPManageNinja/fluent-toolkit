<?php

namespace FluentToolkit\AI\Drivers\Cart;

class GetTopProducts extends ToolBase
{
    public function getName(): string
    {
        return 'get_top_products';
    }

    public function getDefinition(): array
    {
        return [
            'type' => 'function',
            'name' => $this->getName(),
            'description' => 'Return top selling products ordered by revenue from order history. Use this for sales rankings, not for total catalog product counts.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'start_date' => [
                        'type' => 'string',
                        'description' => 'Inclusive start date in YYYY-MM-DD format.',
                    ],
                    'end_date' => [
                        'type' => 'string',
                        'description' => 'Inclusive end date in YYYY-MM-DD format.',
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Maximum number of products to return. Defaults to 10.',
                    ],
                ],
                'additionalProperties' => false,
            ],
            'strict' => false,
        ];
    }

    public function execute(array $arguments)
    {
        $startDate = $this->sanitizeDate($arguments['start_date'] ?? null);
        $endDate = $this->sanitizeDate($arguments['end_date'] ?? null);
        $limit = $this->sanitizeLimit($arguments['limit'] ?? 10, 10, 50);

        $where = [
            "o.type = 'payment'",
            "o.payment_status IN (" . $this->successfulPaymentStatuses() . ')',
        ];
        $params = [];

        if ($startDate) {
            $where[] = 'DATE(COALESCE(o.completed_at, o.created_at)) >= %s';
            $params[] = $startDate;
        }

        if ($endDate) {
            $where[] = 'DATE(COALESCE(o.completed_at, o.created_at)) <= %s';
            $params[] = $endDate;
        }

        $sql = "SELECT
                oi.post_id AS product_id,
                oi.post_title AS product_name,
                SUM(oi.quantity) AS units_sold,
                ROUND(COALESCE(SUM(oi.line_total), 0) / 100, 2) AS revenue
            FROM {$this->table('fct_order_items')} oi
            INNER JOIN {$this->table('fct_orders')} o ON o.id = oi.order_id
            WHERE " . implode(' AND ', $where) . '
            GROUP BY oi.post_id, oi.post_title
            ORDER BY revenue DESC
            LIMIT %d';

        $params[] = $limit;
        $results = $this->wpdb->get_results($this->wpdb->prepare($sql, ...$params), ARRAY_A);

        if ($this->wpdb->last_error) {
            return new \WP_Error('top_products_failed', $this->wpdb->last_error);
        }

        return [
            'range' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'products' => $results ?: [],
        ];
    }
}
