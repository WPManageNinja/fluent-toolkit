<?php

namespace FluentToolkit\AI\Drivers\Cart;

class GetOrderStatusBreakdown extends ToolBase
{
    public function getName(): string
    {
        return 'get_order_status_breakdown';
    }

    public function getDefinition(): array
    {
        return [
            'type' => 'function',
            'name' => $this->getName(),
            'description' => 'Return order and payment status breakdowns for a date range.',
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
        $where = ["type = 'payment'"];
        $params = [];

        if ($startDate) {
            $where[] = 'DATE(COALESCE(completed_at, created_at)) >= %s';
            $params[] = $startDate;
        }

        if ($endDate) {
            $where[] = 'DATE(COALESCE(completed_at, created_at)) <= %s';
            $params[] = $endDate;
        }

        $whereSql = implode(' AND ', $where);

        $orderStatusSql = "SELECT
                status,
                COUNT(*) AS order_count,
                ROUND(COALESCE(SUM(total_amount), 0) / 100, 2) AS gross_revenue
            FROM {$this->table('fct_orders')}
            WHERE {$whereSql}
            GROUP BY status
            ORDER BY order_count DESC";

        $paymentStatusSql = "SELECT
                payment_status,
                COUNT(*) AS order_count,
                ROUND(COALESCE(SUM(total_amount), 0) / 100, 2) AS gross_revenue
            FROM {$this->table('fct_orders')}
            WHERE {$whereSql}
            GROUP BY payment_status
            ORDER BY order_count DESC";

        $orderStatuses = $this->wpdb->get_results(
            $params ? $this->wpdb->prepare($orderStatusSql, ...$params) : $orderStatusSql,
            ARRAY_A
        );
        if ($this->wpdb->last_error) {
            return new \WP_Error('order_status_breakdown_failed', $this->wpdb->last_error);
        }

        $paymentStatuses = $this->wpdb->get_results(
            $params ? $this->wpdb->prepare($paymentStatusSql, ...$params) : $paymentStatusSql,
            ARRAY_A
        );
        if ($this->wpdb->last_error) {
            return new \WP_Error('payment_status_breakdown_failed', $this->wpdb->last_error);
        }

        return [
            'range' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'order_statuses' => $orderStatuses ?: [],
            'payment_statuses' => $paymentStatuses ?: [],
        ];
    }
}
