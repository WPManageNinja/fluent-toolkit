<?php

namespace FluentToolkit\AI\Drivers\Cart;

class GetOrdersByDateRange extends ToolBase
{
    public function getName(): string
    {
        return 'get_orders_by_date_range';
    }

    public function getDefinition(): array
    {
        return [
            'type' => 'function',
            'name' => $this->getName(),
            'description' => 'List orders in a date range with totals and statuses.',
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
                        'description' => 'Maximum number of orders to return. Defaults to 25.',
                    ],
                ],
                'required' => ['start_date', 'end_date'],
                'additionalProperties' => false,
            ],
            'strict' => false,
        ];
    }

    public function execute(array $arguments)
    {
        $startDate = $this->sanitizeDate($arguments['start_date'] ?? null);
        $endDate = $this->sanitizeDate($arguments['end_date'] ?? null);
        $limit = $this->sanitizeLimit($arguments['limit'] ?? 25, 25, 100);

        if (!$startDate || !$endDate) {
            return new \WP_Error('missing_date_range', 'Both start_date and end_date are required.');
        }

        $sql = "SELECT
                id,
                type,
                customer_id,
                status,
                payment_status,
                ROUND(COALESCE(total_amount, 0) / 100, 2) AS total_amount,
                ROUND(COALESCE(total_paid, 0) / 100, 2) AS total_paid,
                ROUND(COALESCE(total_refund, 0) / 100, 2) AS total_refunded,
                created_at,
                completed_at,
                COALESCE(completed_at, created_at) AS order_date
            FROM {$this->table('fct_orders')}
            WHERE type = 'payment'
              AND DATE(COALESCE(completed_at, created_at)) >= %s
              AND DATE(COALESCE(completed_at, created_at)) <= %s
            ORDER BY COALESCE(completed_at, created_at) DESC, id DESC
            LIMIT %d";

        $results = $this->wpdb->get_results(
            $this->wpdb->prepare($sql, $startDate, $endDate, $limit),
            ARRAY_A
        );

        if ($this->wpdb->last_error) {
            return new \WP_Error('orders_range_failed', $this->wpdb->last_error);
        }

        return [
            'range' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'orders' => $results ?: [],
        ];
    }
}
