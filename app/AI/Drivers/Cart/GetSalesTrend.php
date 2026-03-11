<?php

namespace FluentToolkit\AI\Drivers\Cart;

class GetSalesTrend extends ToolBase
{
    public function getName(): string
    {
        return 'get_sales_trend';
    }

    public function getDefinition(): array
    {
        return [
            'type' => 'function',
            'name' => $this->getName(),
            'description' => 'Return sales trend buckets for daily, weekly, or monthly reporting.',
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
                    'interval' => [
                        'type' => 'string',
                        'description' => 'One of daily, weekly, or monthly. Defaults to daily.',
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
        $interval = strtolower((string) ($arguments['interval'] ?? 'daily'));

        if (!$startDate || !$endDate) {
            return new \WP_Error('missing_date_range', 'Both start_date and end_date are required.');
        }

        if (!in_array($interval, ['daily', 'weekly', 'monthly'], true)) {
            $interval = 'daily';
        }

        $orderDateExpr = 'COALESCE(completed_at, created_at)';
        $bucketExpr = match ($interval) {
            'weekly' => "DATE_FORMAT({$orderDateExpr}, '%x-W%v')",
            'monthly' => "DATE_FORMAT({$orderDateExpr}, '%Y-%m')",
            default => "DATE_FORMAT({$orderDateExpr}, '%Y-%m-%d')",
        };

        $sql = "SELECT
                {$bucketExpr} AS bucket,
                COUNT(*) AS order_count,
                ROUND(COALESCE(SUM(total_amount), 0) / 100, 2) AS gross_revenue,
                ROUND(COALESCE(SUM(total_paid), 0) / 100, 2) AS total_paid,
                ROUND(COALESCE(SUM(total_refund), 0) / 100, 2) AS total_refunded
            FROM {$this->table('fct_orders')}
            WHERE type = 'payment'
              AND payment_status IN (" . $this->successfulPaymentStatuses() . ")
              AND DATE({$orderDateExpr}) >= %s
              AND DATE({$orderDateExpr}) <= %s
            GROUP BY bucket
            ORDER BY bucket ASC";

        $results = $this->wpdb->get_results(
            $this->wpdb->prepare($sql, $startDate, $endDate),
            ARRAY_A
        );

        if ($this->wpdb->last_error) {
            return new \WP_Error('sales_trend_failed', $this->wpdb->last_error);
        }

        return [
            'range' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'interval' => $interval,
            'trend' => $results ?: [],
        ];
    }
}
