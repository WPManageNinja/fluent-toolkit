<?php

namespace FluentToolkit\AI\Drivers\Cart;

class GetProductCatalogSummary extends ToolBase
{
    private const EXCLUDED_STATUSES = [
        'trash',
        'auto-draft',
    ];

    public function getName(): string
    {
        return 'get_product_catalog_summary';
    }

    public function getDefinition(): array
    {
        return [
            'type' => 'function',
            'name' => $this->getName(),
            'description' => 'Return the actual FluentCart product catalog count plus catalog breakdowns by product post status and fulfillment type. Use this for questions about how many products exist in the store catalog or how many are physical, digital, service, mixed, published, private, or draft. Do not use sales-based product tools for catalog counts.',
            'parameters' => [
                'type' => 'object',
                'properties' => (object) [],
                'additionalProperties' => false,
            ],
            'strict' => true,
        ];
    }

    public function execute(array $arguments)
    {
        $postType = $this->resolveProductPostType();
        $statusSql = implode(
            ',',
            array_fill(0, count(self::EXCLUDED_STATUSES), '%s')
        );

        $sql = $this->wpdb->prepare(
            "SELECT post_status, COUNT(*) AS total
            FROM {$this->wpdb->posts}
            WHERE post_type = %s
              AND post_status NOT IN ({$statusSql})
            GROUP BY post_status
            ORDER BY total DESC, post_status ASC",
            ...array_merge([$postType], self::EXCLUDED_STATUSES)
        );

        $rows = $this->wpdb->get_results($sql, ARRAY_A);

        if ($this->wpdb->last_error) {
            return new \WP_Error('product_catalog_summary_failed', $this->wpdb->last_error);
        }

        $fulfillmentSql = $this->wpdb->prepare(
            "SELECT COALESCE(NULLIF(pd.fulfillment_type, ''), 'unknown') AS fulfillment_type, COUNT(*) AS total
            FROM {$this->wpdb->posts} p
            LEFT JOIN {$this->table('fct_product_details')} pd ON p.ID = pd.post_id
            WHERE p.post_type = %s
              AND p.post_status NOT IN ({$statusSql})
            GROUP BY fulfillment_type
            ORDER BY total DESC, fulfillment_type ASC",
            ...array_merge([$postType], self::EXCLUDED_STATUSES)
        );

        $fulfillmentRows = $this->wpdb->get_results($fulfillmentSql, ARRAY_A);

        if ($this->wpdb->last_error) {
            return new \WP_Error('product_catalog_fulfillment_summary_failed', $this->wpdb->last_error);
        }

        $statusCounts = [];
        $totalProducts = 0;

        foreach ($rows ?: [] as $row) {
            $status = sanitize_key((string) ($row['post_status'] ?? 'unknown'));
            $count = (int) ($row['total'] ?? 0);

            $statusCounts[$status] = $count;
            $totalProducts += $count;
        }

        $fulfillmentCounts = [];
        foreach ($fulfillmentRows ?: [] as $row) {
            $fulfillmentType = sanitize_key((string) ($row['fulfillment_type'] ?? 'unknown'));
            $fulfillmentCounts[$fulfillmentType] = (int) ($row['total'] ?? 0);
        }

        return [
            'post_type' => $postType,
            'total_products' => $totalProducts,
            'status_breakdown' => $statusCounts,
            'fulfillment_breakdown' => $fulfillmentCounts,
            'published_products' => (int) ($statusCounts['publish'] ?? 0),
            'private_products' => (int) ($statusCounts['private'] ?? 0),
            'draft_products' => (int) ($statusCounts['draft'] ?? 0),
            'pending_products' => (int) ($statusCounts['pending'] ?? 0),
            'future_products' => (int) ($statusCounts['future'] ?? 0),
            'physical_products' => (int) ($fulfillmentCounts['physical'] ?? 0),
            'digital_products' => (int) ($fulfillmentCounts['digital'] ?? 0),
            'service_products' => (int) ($fulfillmentCounts['service'] ?? 0),
            'mixed_products' => (int) ($fulfillmentCounts['mixed'] ?? 0),
            'unknown_fulfillment_products' => (int) ($fulfillmentCounts['unknown'] ?? 0),
            'supported_breakdowns' => ['post_status', 'fulfillment_type'],
        ];
    }

    private function resolveProductPostType(): string
    {
        if (class_exists('\FluentCart\App\CPT\FluentProducts')) {
            return (string) \FluentCart\App\CPT\FluentProducts::CPT_NAME;
        }

        return 'fluent-products';
    }
}
