<?php

namespace FluentToolkit\AI\Drivers\Cart;

class GetPaymentMethodBreakdown extends ToolBase
{
    public function getName(): string
    {
        return 'get_payment_method_breakdown';
    }

    public function getDefinition(): array
    {
        return [
            'type' => 'function',
            'name' => $this->getName(),
            'description' => 'Return payment method mix for a date range.',
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
                payment_method,
                payment_method_title,
                COUNT(*) AS order_count,
                ROUND(COALESCE(SUM(total_amount), 0) / 100, 2) AS gross_revenue
            FROM {$this->table('fct_orders')}
            WHERE " . implode(' AND ', $where) . '
            GROUP BY payment_method, payment_method_title
            ORDER BY gross_revenue DESC, order_count DESC';

        $results = $this->wpdb->get_results(
            $params ? $this->wpdb->prepare($sql, ...$params) : $sql,
            ARRAY_A
        );

        if ($this->wpdb->last_error) {
            return new \WP_Error('payment_method_breakdown_failed', $this->wpdb->last_error);
        }

        return [
            'range' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'payment_methods' => $results ?: [],
        ];
    }
}
