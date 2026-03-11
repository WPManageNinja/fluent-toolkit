<?php

namespace FluentToolkit\AI\Drivers\Cart;

class GetTopCustomers extends ToolBase
{
    public function getName(): string
    {
        return 'get_top_customers';
    }

    public function getDefinition(): array
    {
        return [
            'type' => 'function',
            'name' => $this->getName(),
            'description' => 'Return top customers by revenue for a date range.',
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
                        'description' => 'Maximum number of customers to return. Defaults to 10.',
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
                c.id AS customer_id,
                c.email,
                TRIM(CONCAT(COALESCE(c.first_name, ''), ' ', COALESCE(c.last_name, ''))) AS customer_name,
                COUNT(o.id) AS order_count,
                ROUND(COALESCE(SUM(o.total_amount), 0) / 100, 2) AS revenue
            FROM {$this->table('fct_orders')} o
            INNER JOIN {$this->table('fct_customers')} c ON c.id = o.customer_id
            WHERE " . implode(' AND ', $where) . '
            GROUP BY c.id, c.email, c.first_name, c.last_name
            ORDER BY revenue DESC
            LIMIT %d';

        $params[] = $limit;
        $results = $this->wpdb->get_results($this->wpdb->prepare($sql, ...$params), ARRAY_A);

        if ($this->wpdb->last_error) {
            return new \WP_Error('top_customers_failed', $this->wpdb->last_error);
        }

        return [
            'range' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'customers' => $results ?: [],
        ];
    }
}
