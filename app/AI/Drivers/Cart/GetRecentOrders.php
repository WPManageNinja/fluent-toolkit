<?php

namespace FluentToolkit\AI\Drivers\Cart;

class GetRecentOrders extends ToolBase
{
    public function getName(): string
    {
        return 'get_recent_orders';
    }

    public function getDefinition(): array
    {
        return [
            'type' => 'function',
            'name' => $this->getName(),
            'description' => 'Return the most recent FluentCart orders. Use this for requests like last 5 orders or recent orders.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Maximum number of orders to return. Defaults to 5.',
                    ],
                    'payment_only' => [
                        'type' => 'boolean',
                        'description' => 'If true, include only payment orders.',
                    ],
                ],
                'additionalProperties' => false,
            ],
            'strict' => false,
        ];
    }

    public function execute(array $arguments)
    {
        $limit = $this->sanitizeLimit($arguments['limit'] ?? 5, 5, 50);
        $paymentOnly = array_key_exists('payment_only', $arguments)
            ? (bool) $arguments['payment_only']
            : true;

        $where = [];
        $params = [];

        if ($paymentOnly) {
            $where[] = "type = 'payment'";
        }

        $sql = "SELECT
                id,
                type,
                status,
                payment_status,
                customer_id,
                ROUND(COALESCE(total_amount, 0) / 100, 2) AS total_amount,
                ROUND(COALESCE(total_paid, 0) / 100, 2) AS total_paid,
                ROUND(COALESCE(total_refund, 0) / 100, 2) AS total_refunded,
                created_at,
                completed_at,
                COALESCE(completed_at, created_at) AS order_date
            FROM {$this->table('fct_orders')}";

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY COALESCE(completed_at, created_at) DESC, id DESC LIMIT %d';
        $params[] = $limit;

        $results = $this->wpdb->get_results($this->wpdb->prepare($sql, ...$params), ARRAY_A);

        if ($this->wpdb->last_error) {
            return new \WP_Error('recent_orders_failed', $this->wpdb->last_error);
        }

        return [
            'orders' => $results ?: [],
        ];
    }
}
