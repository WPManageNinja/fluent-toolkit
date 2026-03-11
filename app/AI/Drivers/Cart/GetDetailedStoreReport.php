<?php

namespace FluentToolkit\AI\Drivers\Cart;

class GetDetailedStoreReport extends ToolBase
{
    public function getName(): string
    {
        return 'get_detailed_store_report';
    }

    public function getDefinition(): array
    {
        return [
            'type' => 'function',
            'name' => $this->getName(),
            'description' => 'Return a comprehensive store report for a date range. Use this for prompts like detailed report, full report, performance report, or store overview. It separates overall order activity from paid sales metrics.',
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
                        'description' => 'Maximum number of rows to return for top customers and top products. Defaults to 5.',
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
        $limit = $this->sanitizeLimit($arguments['limit'] ?? 5, 5, 20);
        $dateExpression = 'DATE(COALESCE(completed_at, created_at))';
        $ordersDateExpression = 'DATE(COALESCE(o.completed_at, o.created_at))';

        [$whereSql, $params] = $this->buildDateRangeWhere($dateExpression, $startDate, $endDate);
        [$ordersWhereSql, $ordersParams] = $this->buildDateRangeWhere($ordersDateExpression, $startDate, $endDate);

        $activitySummary = $this->wpdb->get_row(
            $this->prepareWithDateRange(
                "SELECT
                    COUNT(*) AS total_records,
                    COUNT(DISTINCT customer_id) AS unique_customers,
                    ROUND(COALESCE(SUM(total_amount), 0) / 100, 2) AS gross_amount,
                    ROUND(COALESCE(SUM(total_paid), 0) / 100, 2) AS total_paid,
                    ROUND(COALESCE(SUM(total_refund), 0) / 100, 2) AS total_refunded,
                    ROUND(COALESCE(AVG(total_amount), 0) / 100, 2) AS average_record_value
                FROM {$this->table('fct_orders')}
                {$whereSql}",
                $params
            ),
            ARRAY_A
        );
        if ($this->wpdb->last_error) {
            return new \WP_Error('detailed_report_activity_summary_failed', $this->wpdb->last_error);
        }

        $paidSalesSummary = $this->wpdb->get_row(
            $this->prepareWithDateRange(
                "SELECT
                    COUNT(*) AS order_count,
                    ROUND(COALESCE(SUM(total_amount), 0) / 100, 2) AS gross_revenue,
                    ROUND(COALESCE(SUM(total_paid), 0) / 100, 2) AS total_paid,
                    ROUND(COALESCE(SUM(total_refund), 0) / 100, 2) AS total_refunded,
                    ROUND(COALESCE(AVG(total_amount), 0) / 100, 2) AS average_order_value
                FROM {$this->table('fct_orders')}
                WHERE type = 'payment'
                  AND payment_status IN (" . $this->successfulPaymentStatuses() . ")
                  " . $this->appendDateRangeToExistingWhere($dateExpression, $startDate, $endDate),
                $params
            ),
            ARRAY_A
        );
        if ($this->wpdb->last_error) {
            return new \WP_Error('detailed_report_paid_summary_failed', $this->wpdb->last_error);
        }

        $typeBreakdown = $this->wpdb->get_results(
            $this->prepareWithDateRange(
                "SELECT
                    COALESCE(NULLIF(type, ''), 'unknown') AS order_type,
                    COUNT(*) AS record_count,
                    ROUND(COALESCE(SUM(total_amount), 0) / 100, 2) AS gross_amount,
                    ROUND(COALESCE(SUM(total_paid), 0) / 100, 2) AS total_paid,
                    ROUND(COALESCE(SUM(total_refund), 0) / 100, 2) AS total_refunded
                FROM {$this->table('fct_orders')}
                {$whereSql}
                GROUP BY type
                ORDER BY gross_amount DESC, record_count DESC",
                $params
            ),
            ARRAY_A
        );
        if ($this->wpdb->last_error) {
            return new \WP_Error('detailed_report_type_breakdown_failed', $this->wpdb->last_error);
        }

        $orderStatuses = $this->wpdb->get_results(
            $this->prepareWithDateRange(
                "SELECT
                    COALESCE(NULLIF(status, ''), 'unknown') AS status,
                    COUNT(*) AS record_count,
                    ROUND(COALESCE(SUM(total_amount), 0) / 100, 2) AS gross_amount
                FROM {$this->table('fct_orders')}
                {$whereSql}
                GROUP BY status
                ORDER BY record_count DESC, gross_amount DESC",
                $params
            ),
            ARRAY_A
        );
        if ($this->wpdb->last_error) {
            return new \WP_Error('detailed_report_order_status_failed', $this->wpdb->last_error);
        }

        $paymentStatuses = $this->wpdb->get_results(
            $this->prepareWithDateRange(
                "SELECT
                    COALESCE(NULLIF(payment_status, ''), 'unknown') AS payment_status,
                    COUNT(*) AS record_count,
                    ROUND(COALESCE(SUM(total_amount), 0) / 100, 2) AS gross_amount
                FROM {$this->table('fct_orders')}
                {$whereSql}
                GROUP BY payment_status
                ORDER BY record_count DESC, gross_amount DESC",
                $params
            ),
            ARRAY_A
        );
        if ($this->wpdb->last_error) {
            return new \WP_Error('detailed_report_payment_status_failed', $this->wpdb->last_error);
        }

        $paymentMethods = $this->wpdb->get_results(
            $this->prepareWithDateRange(
                "SELECT
                    COALESCE(NULLIF(payment_method_title, ''), NULLIF(payment_method, ''), 'Unknown') AS payment_method,
                    COUNT(*) AS record_count,
                    ROUND(COALESCE(SUM(total_amount), 0) / 100, 2) AS gross_amount,
                    ROUND(COALESCE(SUM(total_paid), 0) / 100, 2) AS total_paid,
                    ROUND(COALESCE(SUM(total_refund), 0) / 100, 2) AS total_refunded
                FROM {$this->table('fct_orders')}
                {$whereSql}
                GROUP BY payment_method, payment_method_title
                ORDER BY gross_amount DESC, record_count DESC",
                $params
            ),
            ARRAY_A
        );
        if ($this->wpdb->last_error) {
            return new \WP_Error('detailed_report_payment_methods_failed', $this->wpdb->last_error);
        }

        $topCustomersSql = "SELECT
                c.id AS customer_id,
                c.email,
                COALESCE(NULLIF(TRIM(CONCAT(COALESCE(c.first_name, ''), ' ', COALESCE(c.last_name, ''))), ''), c.email, 'Unknown') AS customer_name,
                COUNT(o.id) AS record_count,
                ROUND(COALESCE(SUM(o.total_amount), 0) / 100, 2) AS gross_amount,
                ROUND(COALESCE(SUM(o.total_paid), 0) / 100, 2) AS total_paid,
                ROUND(COALESCE(SUM(o.total_refund), 0) / 100, 2) AS total_refunded
            FROM {$this->table('fct_orders')} o
            LEFT JOIN {$this->table('fct_customers')} c ON c.id = o.customer_id
            {$ordersWhereSql}
            GROUP BY c.id, c.email, c.first_name, c.last_name
            ORDER BY gross_amount DESC, record_count DESC
            LIMIT %d";
        $topCustomersParams = array_merge($ordersParams, [$limit]);
        $topCustomers = $this->wpdb->get_results(
            $this->wpdb->prepare($topCustomersSql, ...$topCustomersParams),
            ARRAY_A
        );
        if ($this->wpdb->last_error) {
            return new \WP_Error('detailed_report_top_customers_failed', $this->wpdb->last_error);
        }

        $topProductsSql = "SELECT
                oi.post_id AS product_id,
                COALESCE(NULLIF(oi.post_title, ''), NULLIF(oi.title, ''), 'Unknown Product') AS product_name,
                SUM(oi.quantity) AS units_sold,
                ROUND(COALESCE(SUM(oi.line_total), 0) / 100, 2) AS gross_amount,
                ROUND(COALESCE(SUM(oi.refund_total), 0) / 100, 2) AS refunded_amount
            FROM {$this->table('fct_order_items')} oi
            INNER JOIN {$this->table('fct_orders')} o ON o.id = oi.order_id
            {$ordersWhereSql}
            GROUP BY oi.post_id, oi.post_title, oi.title
            ORDER BY gross_amount DESC, units_sold DESC
            LIMIT %d";
        $topProductsParams = array_merge($ordersParams, [$limit]);
        $topProducts = $this->wpdb->get_results(
            $this->wpdb->prepare($topProductsSql, ...$topProductsParams),
            ARRAY_A
        );
        if ($this->wpdb->last_error) {
            return new \WP_Error('detailed_report_top_products_failed', $this->wpdb->last_error);
        }

        return [
            'range' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'overall_activity' => $activitySummary ?: [],
            'paid_sales' => $paidSalesSummary ?: [],
            'type_breakdown' => $typeBreakdown ?: [],
            'order_statuses' => $orderStatuses ?: [],
            'payment_statuses' => $paymentStatuses ?: [],
            'payment_methods' => $paymentMethods ?: [],
            'top_customers' => $topCustomers ?: [],
            'top_products' => $topProducts ?: [],
        ];
    }

    private function buildDateRangeWhere(string $dateExpression, ?string $startDate, ?string $endDate): array
    {
        $where = [];
        $params = [];

        if ($startDate) {
            $where[] = "{$dateExpression} >= %s";
            $params[] = $startDate;
        }

        if ($endDate) {
            $where[] = "{$dateExpression} <= %s";
            $params[] = $endDate;
        }

        if (!$where) {
            return ['', []];
        }

        return ['WHERE ' . implode(' AND ', $where), $params];
    }

    private function appendDateRangeToExistingWhere(string $dateExpression, ?string $startDate, ?string $endDate): string
    {
        $clauses = [];

        if ($startDate) {
            $clauses[] = "{$dateExpression} >= %s";
        }

        if ($endDate) {
            $clauses[] = "{$dateExpression} <= %s";
        }

        if (!$clauses) {
            return '';
        }

        return ' AND ' . implode(' AND ', $clauses);
    }

    private function prepareWithDateRange(string $sql, array $params): string
    {
        if (!$params) {
            return $sql;
        }

        return $this->wpdb->prepare($sql, ...$params);
    }
}
