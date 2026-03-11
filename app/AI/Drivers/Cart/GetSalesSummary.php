<?php

namespace FluentToolkit\AI\Drivers\Cart;

class GetSalesSummary extends ToolBase
{
    public function getName(): string
    {
        return 'get_sales_summary';
    }

    public function getDefinition(): array
    {
        return [
            'type' => 'function',
            'name' => $this->getName(),
            'description' => 'Summarize FluentCart sales totals for a date range.',
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

        $where = [
            "type = 'payment'",
            "payment_status IN (" . $this->successfulPaymentStatuses() . ')',
        ];
        $params = [];

        if ($startDate) {
            $where[] = 'DATE(COALESCE(completed_at, created_at)) >= %s';
            $params[] = $startDate;
        }

        if ($endDate) {
            $where[] = 'DATE(COALESCE(completed_at, created_at)) <= %s';
            $params[] = $endDate;
        }

        $sql = "SELECT
                COUNT(*) AS order_count,
                ROUND(COALESCE(SUM(total_amount), 0) / 100, 2) AS gross_revenue,
                ROUND(COALESCE(SUM(total_paid), 0) / 100, 2) AS total_paid,
                ROUND(COALESCE(SUM(total_refund), 0) / 100, 2) AS total_refunded,
                ROUND(COALESCE(AVG(total_amount), 0) / 100, 2) AS average_order_value
            FROM {$this->table('fct_orders')}
            WHERE " . implode(' AND ', $where);

        $prepared = $params ? $this->wpdb->prepare($sql, ...$params) : $sql;
        $summary = $this->wpdb->get_row($prepared, ARRAY_A);

        if ($this->wpdb->last_error) {
            return new \WP_Error('sales_summary_failed', $this->wpdb->last_error);
        }

        return [
            'range' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'summary' => $summary ?: [],
        ];
    }
}
