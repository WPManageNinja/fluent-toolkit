<?php

namespace FluentToolkit\AI\Drivers\Cart;

class GetSubscriptionSummary extends ToolBase
{
    public function getName(): string
    {
        return 'get_subscription_summary';
    }

    public function getDefinition(): array
    {
        return [
            'type' => 'function',
            'name' => $this->getName(),
            'description' => 'Return subscription status counts and upcoming renewals.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'status' => [
                        'type' => 'string',
                        'description' => 'Optional specific subscription status to filter by.',
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Maximum number of upcoming subscriptions to return. Defaults to 10.',
                    ],
                ],
                'additionalProperties' => false,
            ],
            'strict' => false,
        ];
    }

    public function execute(array $arguments)
    {
        $status = sanitize_text_field((string) ($arguments['status'] ?? ''));
        $limit = $this->sanitizeLimit($arguments['limit'] ?? 10, 10, 50);
        $where = [];
        $params = [];

        if ($status !== '') {
            $where[] = 'status = %s';
            $params[] = $status;
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $summarySql = "SELECT
                status,
                COUNT(*) AS subscription_count,
                ROUND(COALESCE(SUM(recurring_total), 0) / 100, 2) AS recurring_revenue
            FROM {$this->table('fct_subscriptions')}
            {$whereSql}
            GROUP BY status
            ORDER BY subscription_count DESC";

        $summary = $this->wpdb->get_results(
            $params ? $this->wpdb->prepare($summarySql, ...$params) : $summarySql,
            ARRAY_A
        );

        if ($this->wpdb->last_error) {
            return new \WP_Error('subscription_summary_failed', $this->wpdb->last_error);
        }

        $upcomingWhere = $where;
        $upcomingParams = $params;
        $upcomingWhere[] = 'next_billing_date IS NOT NULL';

        $upcomingSql = "SELECT
                id,
                customer_id,
                item_name,
                status,
                ROUND(COALESCE(recurring_total, 0) / 100, 2) AS recurring_total,
                billing_interval,
                next_billing_date
            FROM {$this->table('fct_subscriptions')}
            WHERE " . implode(' AND ', $upcomingWhere) . '
            ORDER BY next_billing_date ASC, id DESC
            LIMIT %d';

        $upcomingParams[] = $limit;
        $upcoming = $this->wpdb->get_results(
            $this->wpdb->prepare($upcomingSql, ...$upcomingParams),
            ARRAY_A
        );

        if ($this->wpdb->last_error) {
            return new \WP_Error('subscription_upcoming_failed', $this->wpdb->last_error);
        }

        return [
            'status_filter' => $status !== '' ? $status : null,
            'summary' => $summary ?: [],
            'upcoming' => $upcoming ?: [],
        ];
    }
}
