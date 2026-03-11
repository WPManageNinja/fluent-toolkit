<?php

namespace FluentToolkit\AI\Drivers\Cart;

class GetCouponUsage extends ToolBase
{
    public function getName(): string
    {
        return 'get_coupon_usage';
    }

    public function getDefinition(): array
    {
        return [
            'type' => 'function',
            'name' => $this->getName(),
            'description' => 'Return coupon usage and discount totals for a date range.',
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
                        'description' => 'Maximum number of coupons to return. Defaults to 10.',
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
        $where = [];
        $params = [];

        if ($startDate) {
            $where[] = 'DATE(COALESCE(o.completed_at, o.created_at)) >= %s';
            $params[] = $startDate;
        }

        if ($endDate) {
            $where[] = 'DATE(COALESCE(o.completed_at, o.created_at)) <= %s';
            $params[] = $endDate;
        }

        $where[] = "o.type = 'payment'";

        $sql = "SELECT
                ac.code,
                COUNT(*) AS usage_count,
                ROUND(COALESCE(SUM(ac.amount), 0) / 100, 2) AS discount_total,
                ROUND(COALESCE(SUM(o.total_amount), 0) / 100, 2) AS gross_revenue
            FROM {$this->table('fct_applied_coupons')} ac
            INNER JOIN {$this->table('fct_orders')} o ON o.id = ac.order_id
            WHERE " . implode(' AND ', $where);

        $sql .= ' GROUP BY ac.code ORDER BY usage_count DESC, discount_total DESC LIMIT %d';
        $params[] = $limit;

        $results = $this->wpdb->get_results($this->wpdb->prepare($sql, ...$params), ARRAY_A);

        if ($this->wpdb->last_error) {
            return new \WP_Error('coupon_usage_failed', $this->wpdb->last_error);
        }

        return [
            'range' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'coupons' => $results ?: [],
        ];
    }
}
