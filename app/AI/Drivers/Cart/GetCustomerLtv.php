<?php

namespace FluentToolkit\AI\Drivers\Cart;

class GetCustomerLtv extends ToolBase
{
    public function getName(): string
    {
        return 'get_customer_ltv';
    }

    public function getDefinition(): array
    {
        return [
            'type' => 'function',
            'name' => $this->getName(),
            'description' => 'Return customers ordered by lifetime value.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'country' => [
                        'type' => 'string',
                        'description' => 'Optional ISO country code filter.',
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Maximum number of customers to return. Defaults to 10.',
                    ],
                ],
                'additionalProperties' => false,
            ],
            'strict' => false,
        ];
    }

    public function execute(array $arguments)
    {
        $country = strtoupper(sanitize_text_field((string) ($arguments['country'] ?? '')));
        $limit = $this->sanitizeLimit($arguments['limit'] ?? 10, 10, 50);

        $where = [];
        $params = [];

        if ($country !== '') {
            $where[] = 'country = %s';
            $params[] = $country;
        }

        $sql = "SELECT
                id,
                email,
                TRIM(CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, ''))) AS customer_name,
                purchase_count,
                ROUND(COALESCE(ltv, 0) / 100, 2) AS ltv,
                country,
                last_purchase_date
            FROM {$this->table('fct_customers')}";

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY ltv DESC LIMIT %d';
        $params[] = $limit;

        $results = $this->wpdb->get_results($this->wpdb->prepare($sql, ...$params), ARRAY_A);

        if ($this->wpdb->last_error) {
            return new \WP_Error('customer_ltv_failed', $this->wpdb->last_error);
        }

        return [
            'country' => $country ?: null,
            'customers' => $results ?: [],
        ];
    }
}
