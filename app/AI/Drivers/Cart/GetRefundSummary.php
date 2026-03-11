<?php

namespace FluentToolkit\AI\Drivers\Cart;

class GetRefundSummary extends ToolBase
{
    public function getName(): string
    {
        return 'get_refund_summary';
    }

    public function getDefinition(): array
    {
        return [
            'type' => 'function',
            'name' => $this->getName(),
            'description' => 'Return refunded order totals and recent refunded orders for a date range.',
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
                        'description' => 'Maximum number of refunded orders to return. Defaults to 10.',
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
        $dateExpr = 'COALESCE(refunded_at, completed_at, created_at)';
        $where = ['total_refund > 0'];
        $params = [];

        if ($startDate) {
            $where[] = "DATE({$dateExpr}) >= %s";
            $params[] = $startDate;
        }

        if ($endDate) {
            $where[] = "DATE({$dateExpr}) <= %s";
            $params[] = $endDate;
        }

        $summarySql = "SELECT
                COUNT(*) AS refunded_order_count,
                ROUND(COALESCE(SUM(total_refund), 0) / 100, 2) AS total_refunded,
                ROUND(COALESCE(SUM(total_amount), 0) / 100, 2) AS impacted_revenue
            FROM {$this->table('fct_orders')}
            WHERE " . implode(' AND ', $where);

        $summary = $this->wpdb->get_row(
            $params ? $this->wpdb->prepare($summarySql, ...$params) : $summarySql,
            ARRAY_A
        );

        if ($this->wpdb->last_error) {
            return new \WP_Error('refund_summary_failed', $this->wpdb->last_error);
        }

        $ordersSql = "SELECT
                id,
                status,
                payment_status,
                ROUND(COALESCE(total_amount, 0) / 100, 2) AS total_amount,
                ROUND(COALESCE(total_refund, 0) / 100, 2) AS total_refunded,
                refunded_at,
                completed_at,
                created_at
            FROM {$this->table('fct_orders')}
            WHERE " . implode(' AND ', $where) . "
            ORDER BY {$dateExpr} DESC, id DESC
            LIMIT %d";

        $orderParams = $params;
        $orderParams[] = $limit;
        $orders = $this->wpdb->get_results($this->wpdb->prepare($ordersSql, ...$orderParams), ARRAY_A);

        if ($this->wpdb->last_error) {
            return new \WP_Error('refund_orders_failed', $this->wpdb->last_error);
        }

        return [
            'range' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'summary' => $summary ?: [],
            'orders' => $orders ?: [],
        ];
    }
}
