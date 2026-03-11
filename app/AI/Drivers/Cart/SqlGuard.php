<?php

namespace FluentToolkit\AI\Drivers\Cart;

use FluentToolkit\AI\Common\Settings;

class SqlGuard
{
    private \wpdb $wpdb;
    private Settings $settings;
    private StoreContext $storeContext;

    public function __construct(?Settings $settings = null, ?StoreContext $storeContext = null, ?\wpdb $database = null)
    {
        global $wpdb;

        $this->wpdb = $database ?: $wpdb;
        $this->settings = $settings ?: new Settings();
        $this->storeContext = $storeContext ?: new StoreContext();
    }

    /**
     * @return array|\WP_Error
     */
    public function runBatch(array $queries)
    {
        if (!$this->settings->isSqlFallbackEnabled()) {
            return new \WP_Error('sql_fallback_disabled', 'SQL fallback is disabled.');
        }

        if (!current_user_can('manage_options')) {
            return new \WP_Error('sql_fallback_forbidden', 'You are not allowed to run SQL fallback queries.');
        }

        $queries = array_values(array_filter($queries, static fn ($query): bool => is_array($query)));

        if (!$queries) {
            return new \WP_Error('sql_batch_empty', 'At least one SQL query is required.');
        }

        $maxQueries = $this->settings->getSqlFallbackMaxQueries();
        if (count($queries) > $maxQueries) {
            return new \WP_Error(
                'sql_batch_too_large',
                sprintf('You can run at most %d SQL queries in one batch.', $maxQueries)
            );
        }

        $results = [];
        foreach ($queries as $index => $query) {
            $execution = $this->runSingleQuery($query, $index);
            if (is_wp_error($execution)) {
                return $execution;
            }

            $results[] = $execution;
        }

        return [
            'constraints' => [
                'max_queries' => $maxQueries,
                'max_rows' => $this->settings->getSqlFallbackMaxRows(),
                'allowed_tables' => $this->getAllowedTablesForPrompt(),
                'prefix' => $this->wpdb->prefix,
            ],
            'results' => $results,
        ];
    }

    public function getFallbackContext(): array
    {
        $currencyContext = $this->getStoreCurrencyContext();

        return [
            'enabled' => $this->settings->isSqlFallbackEnabled(),
            'prefix' => $this->wpdb->prefix,
            'store_currency' => $currencyContext,
            'allowed_tables' => $this->getAllowedTablesForPrompt(),
            'schema_reference' => $this->getSchemaReference(),
            'max_queries' => $this->settings->getSqlFallbackMaxQueries(),
            'max_rows' => $this->settings->getSqlFallbackMaxRows(),
            'max_query_length' => $this->settings->getSqlFallbackMaxQueryLength(),
            'rules' => [
                'Only SELECT or WITH queries are allowed.',
                'Use only approved FluentCart tables.',
                'Use explicit columns, not SELECT * or alias.* projections.',
                'Do not use comments, multiple statements, or write operations.',
                'Inspect the schema reference before using any columns you are not fully sure about.',
                'Batch related queries together when possible.',
                'Keep record listings narrow and limited.',
            ],
        ];
    }

    public function getSchemaReference(): array
    {
        $currencyContext = $this->getStoreCurrencyContext();

        return [
            'database' => 'MySQL / MariaDB for WordPress',
            'store_currency' => $currencyContext,
            'facts' => [
                'All FluentCart tables use the WordPress table prefix. Use {prefix} as the placeholder in generated SQL.',
                'IDs are typically BIGINT UNSIGNED.',
                'Monetary values are usually stored as integer cents. Divide by 100 for readable currency values.',
                sprintf(
                    'The default store currency is %s with symbol %s and position %s. If a query result does not include an explicit currency column, present money using this store currency context.',
                    $currencyContext['currency'] ?? 'USD',
                    $currencyContext['currency_sign'] ?? ($currencyContext['currency'] ?? 'USD'),
                    $currencyContext['currency_position'] ?? 'before'
                ),
                'Use explicit JOINs with clear aliases like o for orders, c for customers, and oi for order_items.',
                'Date columns like completed_at and created_at are DATETIME values.',
                'Only use the approved tables provided in this schema reference.',
            ],
            'authoring_rules' => [
                'Prefer grouped analytical queries over raw record dumps.',
                'Use explicit column lists. Do not use SELECT * or alias.* projections.',
                'Use exact schema columns only. Do not invent convenience fields such as gross_amount, refunded_amount, customer_name, or product_name unless you define them explicitly in SELECT.',
                'When turning cents into currency, aggregate in cents first and divide after aggregation where possible, such as SUM(o.total_amount) / 100.',
                'If the question could span multiple currencies, include the relevant currency column in SELECT and GROUP BY instead of silently combining different currencies.',
                'For raw record listings, keep the result set narrow and focused on the question.',
                'Use COALESCE(o.completed_at, o.created_at) when the user asks for order timing or date grouping unless the request clearly needs a specific date field.',
                'If a query fails with an unknown column or join error, inspect the schema and retry instead of asking the user for database documentation.',
            ],
            'statuses' => [
                'order_statuses' => ['processing', 'completed', 'on-hold', 'canceled', 'failed'],
                'payment_statuses' => ['pending', 'paid', 'partially_paid', 'failed', 'refunded', 'partially_refunded', 'authorized'],
                'successful_payment_statuses' => ['paid', 'partially_paid', 'partially_refunded'],
            ],
            'canonical_expressions' => [
                [
                    'name' => 'order_date',
                    'sql' => 'COALESCE(o.completed_at, o.created_at)',
                    'description' => 'Best default date expression for order timing and grouping.',
                ],
                [
                    'name' => 'customer_name',
                    'sql' => "TRIM(CONCAT(COALESCE(c.first_name, ''), ' ', COALESCE(c.last_name, '')))",
                    'description' => 'Canonical customer display name expression.',
                ],
                [
                    'name' => 'order_total',
                    'sql' => 'o.total_amount / 100',
                    'description' => 'Readable order total in currency units.',
                ],
                [
                    'name' => 'paid_total',
                    'sql' => 'o.total_paid / 100',
                    'description' => 'Readable paid total in currency units.',
                ],
                [
                    'name' => 'refunded_total',
                    'sql' => 'o.total_refund / 100',
                    'description' => 'Readable refunded total in currency units.',
                ],
                [
                    'name' => 'manual_discount_total',
                    'sql' => 'o.manual_discount_total / 100',
                    'description' => 'Readable manual discount total in currency units.',
                ],
                [
                    'name' => 'coupon_discount_total',
                    'sql' => 'o.coupon_discount_total / 100',
                    'description' => 'Readable coupon discount total in currency units.',
                ],
                [
                    'name' => 'total_discount',
                    'sql' => '(o.manual_discount_total + o.coupon_discount_total) / 100',
                    'description' => 'Readable total discount in currency units across manual and coupon discounts.',
                ],
                [
                    'name' => 'items_total',
                    'sql' => 'SUM(oi.line_total) / 100',
                    'description' => 'Readable summed line totals for order items.',
                ],
                [
                    'name' => 'item_product_name',
                    'sql' => 'oi.post_title',
                    'description' => 'Preferred product name source for order item reporting.',
                ],
                [
                    'name' => 'payment_method_label',
                    'sql' => "COALESCE(NULLIF(o.payment_method_title, ''), o.payment_method)",
                    'description' => 'Preferred readable payment method label.',
                ],
                [
                    'name' => 'order_currency',
                    'sql' => 'o.currency',
                    'description' => 'Preferred explicit currency code for order-level reporting when currency can vary.',
                ],
                [
                    'name' => 'successful_payment_filter',
                    'sql' => "o.payment_status IN ('paid', 'partially_paid', 'partially_refunded')",
                    'description' => 'Default payment-status filter for paid sales and revenue reporting.',
                ],
            ],
            'common_joins' => [
                [
                    'sql' => '{prefix}fct_orders o LEFT JOIN {prefix}fct_customers c ON o.customer_id = c.id',
                    'description' => 'Use for order-to-customer reporting.',
                ],
                [
                    'sql' => '{prefix}fct_orders o LEFT JOIN {prefix}fct_order_items oi ON o.id = oi.order_id',
                    'description' => 'Use for order-to-item reporting.',
                ],
                [
                    'sql' => '{prefix}fct_order_items oi LEFT JOIN {prefix}fct_product_details pd ON oi.post_id = pd.post_id',
                    'description' => 'Use when order items need product detail enrichment.',
                ],
                [
                    'sql' => '{prefix}fct_orders o LEFT JOIN {prefix}fct_order_addresses a ON o.id = a.order_id',
                    'description' => 'Use when billing or shipping geography is needed. Filter a.type explicitly.',
                ],
                [
                    'sql' => '{prefix}fct_orders o LEFT JOIN {prefix}fct_order_transactions t ON o.id = t.order_id',
                    'description' => 'Use for charge and refund transaction analysis.',
                ],
                [
                    'sql' => '{prefix}fct_orders o LEFT JOIN {prefix}fct_order_operations oo ON o.id = oo.order_id',
                    'description' => 'Use for marketing attribution and UTM reporting.',
                ],
            ],
            'common_filters' => [
                ['name' => 'sales_orders_only', 'sql' => "o.type = 'payment'"],
                ['name' => 'subscription_orders_only', 'sql' => "o.type = 'subscription'"],
                ['name' => 'billing_addresses_only', 'sql' => "a.type = 'billing'"],
                ['name' => 'successful_paid_sales', 'sql' => "o.payment_status IN ('paid', 'partially_paid', 'partially_refunded')"],
            ],
            'anti_patterns' => [
                'Do not assume customers has a customer_name column. Build it from first_name and last_name.',
                'Do not assume orders has gross_amount or refunded_amount columns. Use total_amount and total_refund.',
                'Do not assume product_details contains product_name for order reporting. Use order_items.post_title unless exact schema inspection shows another field you need.',
                'Do not assume every order of interest is type = payment. Use subscription or mixed order types only when the question requires it.',
                'Do not confuse coupon discounts with total discounts. Total discounts normally means manual_discount_total plus coupon_discount_total unless the user says otherwise.',
            ],
            'query_recipes' => [
                [
                    'name' => 'Revenue by month',
                    'sql' => "SELECT DATE_FORMAT(COALESCE(o.completed_at, o.created_at), '%Y-%m') AS month, ROUND(SUM(o.total_amount) / 100, 2) AS gross_revenue FROM {prefix}fct_orders o WHERE o.type = 'payment' AND o.payment_status IN ('paid', 'partially_paid', 'partially_refunded') GROUP BY month ORDER BY month",
                ],
                [
                    'name' => 'Customer order summary',
                    'sql' => "SELECT c.email, TRIM(CONCAT(COALESCE(c.first_name, ''), ' ', COALESCE(c.last_name, ''))) AS customer_name, COUNT(o.id) AS order_count, ROUND(SUM(o.total_amount) / 100, 2) AS gross_revenue FROM {prefix}fct_orders o LEFT JOIN {prefix}fct_customers c ON o.customer_id = c.id GROUP BY c.id, c.email, c.first_name, c.last_name ORDER BY gross_revenue DESC LIMIT 20",
                ],
                [
                    'name' => 'Order reconciliation check',
                    'sql' => "SELECT o.id AS order_id, o.total_amount / 100 AS order_total, IFNULL(SUM(oi.line_total), 0) / 100 AS items_total, o.total_refund / 100 AS refunded_amount FROM {prefix}fct_orders o LEFT JOIN {prefix}fct_order_items oi ON o.id = oi.order_id GROUP BY o.id, o.total_amount, o.total_refund HAVING ABS(o.total_amount - (IFNULL(SUM(oi.line_total), 0) + IFNULL(o.total_refund, 0))) > 1 LIMIT 20",
                ],
                [
                    'name' => 'Discounts by month',
                    'sql' => "SELECT DATE_FORMAT(COALESCE(o.completed_at, o.created_at), '%Y-%m') AS month, ROUND(SUM(o.manual_discount_total) / 100, 2) AS manual_discount_total, ROUND(SUM(o.coupon_discount_total) / 100, 2) AS coupon_discount_total, ROUND(SUM(o.manual_discount_total + o.coupon_discount_total) / 100, 2) AS total_discount FROM {prefix}fct_orders o WHERE o.type = 'payment' GROUP BY month ORDER BY month",
                ],
                [
                    'name' => 'Top UTM campaigns by paid revenue',
                    'sql' => "SELECT COALESCE(NULLIF(oo.utm_campaign, ''), 'Unknown') AS utm_campaign, ROUND(SUM(o.total_paid) / 100, 2) AS paid_revenue, COUNT(DISTINCT o.id) AS order_count FROM {prefix}fct_orders o LEFT JOIN {prefix}fct_order_operations oo ON o.id = oo.order_id WHERE o.type = 'payment' AND o.payment_status IN ('paid', 'partially_paid', 'partially_refunded') GROUP BY utm_campaign ORDER BY paid_revenue DESC LIMIT 10",
                ],
            ],
            'table_summaries' => [
                [
                    'table' => '{prefix}fct_customers',
                    'columns' => ['id', 'email', 'first_name', 'last_name', 'status', 'purchase_count', 'ltv', 'aov', 'first_purchase_date', 'last_purchase_date', 'country', 'city', 'state', 'postcode', 'user_id', 'uuid'],
                ],
                [
                    'table' => '{prefix}fct_orders',
                    'columns' => ['id', 'payment_status', 'status', 'type', 'parent_id', 'customer_id', 'subtotal', 'discount_tax', 'manual_discount_total', 'coupon_discount_total', 'shipping_total', 'tax_total', 'total_amount', 'total_paid', 'total_refund', 'completed_at', 'refunded_at', 'uuid', 'config'],
                ],
                [
                    'table' => '{prefix}fct_order_items',
                    'columns' => ['id', 'order_id', 'post_id', 'post_title', 'quantity', 'unit_price', 'subtotal', 'discount_total', 'tax_amount', 'line_total', 'refund_total', 'fulfilled_quantity', 'object_id', 'line_meta'],
                ],
                [
                    'table' => '{prefix}fct_subscriptions',
                    'columns' => ['id', 'uuid', 'customer_id', 'parent_order_id', 'product_id', 'variation_id', 'recurring_amount', 'recurring_tax_total', 'recurring_total', 'bill_count', 'bill_times', 'expire_at', 'trial_ends_at', 'canceled_at', 'status', 'collection_method'],
                ],
                [
                    'table' => '{prefix}fct_order_transactions',
                    'columns' => ['id', 'order_id', 'transaction_type', 'status', 'total', 'vendor_charge_id', 'card_last_4', 'card_brand', 'subscription_id'],
                ],
                [
                    'table' => '{prefix}fct_coupons',
                    'columns' => ['id', 'title', 'code', 'type', 'amount', 'use_count', 'status', 'conditions'],
                ],
                [
                    'table' => '{prefix}fct_order_addresses',
                    'columns' => ['order_id', 'type', 'name', 'address_1', 'city', 'state', 'postcode', 'country'],
                ],
                [
                    'table' => '{prefix}fct_product_variations',
                    'columns' => ['product catalog and variation details linked to WordPress posts'],
                ],
                [
                    'table' => '{prefix}fct_product_details',
                    'columns' => ['product catalog details linked by post_id'],
                ],
                [
                    'table' => '{prefix}fct_order_operations',
                    'columns' => ['order_id', 'utm_campaign', 'utm_source', 'utm_medium', 'utm_content', 'utm_id', 'created_at', 'updated_at'],
                ],
            ],
        ];
    }

    public function getStoreCurrencyContext(): array
    {
        return $this->storeContext->getCurrencyContext();
    }

    /**
     * @return array|\WP_Error
     */
    public function issueApprovalToken(string $reason)
    {
        if (!$this->settings->isSqlFallbackEnabled()) {
            return new \WP_Error('sql_fallback_disabled', 'SQL fallback is disabled.');
        }

        if (!current_user_can('manage_options')) {
            return new \WP_Error('sql_fallback_forbidden', 'You are not allowed to use SQL fallback.');
        }

        $reason = trim($reason);
        if ($reason === '') {
            return new \WP_Error('sql_fallback_reason_required', 'A short reason is required before using SQL fallback.');
        }

        $token = wp_generate_uuid4();
        set_transient($this->approvalTransientKey(), [
            'token' => $token,
            'issued_at' => time(),
            'reason' => $reason,
        ], 10 * MINUTE_IN_SECONDS);

        return [
            'approval_token' => $token,
            'expires_in_seconds' => 10 * MINUTE_IN_SECONDS,
        ];
    }

    /**
     * @return true|\WP_Error
     */
    public function verifyApprovalToken(string $token)
    {
        if (!$this->settings->isSqlFallbackEnabled()) {
            return new \WP_Error('sql_fallback_disabled', 'SQL fallback is disabled.');
        }

        if (!current_user_can('manage_options')) {
            return new \WP_Error('sql_fallback_forbidden', 'You are not allowed to use SQL fallback.');
        }

        $token = trim($token);
        if ($token === '') {
            return new \WP_Error('sql_fallback_token_required', 'An approval token is required before running SQL fallback.');
        }

        $payload = get_transient($this->approvalTransientKey());
        if (!is_array($payload) || empty($payload['token'])) {
            return new \WP_Error('sql_fallback_token_missing', 'SQL fallback approval has expired. Request SQL fallback again.');
        }

        if (!hash_equals((string) $payload['token'], $token)) {
            return new \WP_Error('sql_fallback_token_invalid', 'The SQL fallback approval token is invalid.');
        }

        return true;
    }

    /**
     * @return array|\WP_Error
     */
    private function runSingleQuery(array $query, int $index)
    {
        $name = sanitize_key((string) ($query['name'] ?? ('query_' . ($index + 1))));
        if ($name === '') {
            $name = 'query_' . ($index + 1);
        }

        $rawSql = trim((string) ($query['sql'] ?? ''));
        if ($rawSql === '') {
            return new \WP_Error('sql_query_missing', sprintf('Query %d is missing SQL.', $index + 1));
        }

        $prepared = $this->prepareReadonlyQuery($rawSql);
        if (is_wp_error($prepared)) {
            return $prepared;
        }

        $startedAt = microtime(true);
        $rows = $this->wpdb->get_results($prepared['sql'], ARRAY_A);
        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

        if ($this->wpdb->last_error) {
            return new \WP_Error('sql_query_failed', $this->wpdb->last_error, [
                'query_name' => $name,
                'sql' => $prepared['display_sql'],
                'hint' => $this->buildExecutionHint($this->wpdb->last_error),
            ]);
        }

        $maxRows = $this->settings->getSqlFallbackMaxRows();
        $truncated = false;
        if (count($rows) > $maxRows) {
            $rows = array_slice($rows, 0, $maxRows);
            $truncated = true;
        }

        return [
            'name' => $name,
            'sql' => $prepared['display_sql'],
            'row_count' => count($rows),
            'truncated' => $truncated,
            'guard_limit_applied' => $prepared['limit_enforced'],
            'duration_ms' => $durationMs,
            'columns' => isset($rows[0]) ? array_keys($rows[0]) : [],
            'rows' => $rows,
        ];
    }

    /**
     * @return array|\WP_Error
     */
    private function prepareReadonlyQuery(string $sql)
    {
        $maxQueryLength = $this->settings->getSqlFallbackMaxQueryLength();
        if (strlen($sql) > $maxQueryLength) {
            return new \WP_Error(
                'sql_query_too_long',
                sprintf('SQL queries must be %d characters or fewer.', $maxQueryLength)
            );
        }

        $normalizedSql = trim($this->replaceTablePrefixPlaceholder($sql));
        $normalizedSql = preg_replace('/;+\s*$/', '', $normalizedSql);
        $normalizedSql = trim((string) $normalizedSql);

        if ($normalizedSql === '') {
            return new \WP_Error('sql_query_empty', 'SQL query is empty.');
        }

        $sanitizedSql = $this->stripQuotedLiterals($normalizedSql);

        if (preg_match('/(--|#|\/\*)/', $sanitizedSql)) {
            return new \WP_Error('sql_query_comments_forbidden', 'SQL comments are not allowed in SQL fallback queries.');
        }

        if (strpos($normalizedSql, ';') !== false) {
            return new \WP_Error('sql_multiple_statements_forbidden', 'Only one SQL statement is allowed per query.');
        }

        if (!preg_match('/^\s*(SELECT|WITH)\b/i', $sanitizedSql)) {
            return new \WP_Error('sql_not_readonly', 'Only SELECT or WITH queries are allowed.');
        }

        if ($this->containsWildcardProjection($sanitizedSql)) {
            return new \WP_Error(
                'sql_wildcard_projection_forbidden',
                'SQL fallback queries must use explicit column lists instead of wildcard projections.',
                [
                    'hint' => 'Replace SELECT * or alias.* with the exact columns you need.',
                ]
            );
        }

        $forbiddenPatterns = [
            '/\b(INSERT|UPDATE|DELETE|DROP|CREATE|ALTER|TRUNCATE|REPLACE|RENAME|GRANT|REVOKE|LOCK|UNLOCK|SET|CALL|LOAD\s+DATA|IMPORT|OPTIMIZE|REPAIR|FLUSH|RESET|HANDLER|PREPARE|EXECUTE|DEALLOCATE|BEGIN|START\s+TRANSACTION|COMMIT|ROLLBACK|SAVEPOINT|XA|INSTALL|UNINSTALL)\b/i',
            '/\bINTO\s+(OUTFILE|DUMPFILE)\b/i',
            '/\b(SLEEP|BENCHMARK|LOAD_FILE)\s*\(/i',
            '/@@/',
            '/\b(information_schema|performance_schema|mysql|sys)\b/i',
        ];

        foreach ($forbiddenPatterns as $pattern) {
            if (preg_match($pattern, $sanitizedSql)) {
                return new \WP_Error('sql_query_forbidden', 'The SQL query contains a forbidden operation or schema.');
            }
        }

        $validation = $this->validateTables($sanitizedSql);
        if (is_wp_error($validation)) {
            return $validation;
        }

        $limitEnforced = false;
        $maxRows = $this->settings->getSqlFallbackMaxRows();
        $requestedLimit = $this->detectRequestedLimit($sanitizedSql);

        if ($requestedLimit !== null && $requestedLimit > $maxRows) {
            return new \WP_Error(
                'sql_limit_too_large',
                sprintf('SQL query limits cannot exceed %d rows.', $maxRows)
            );
        }

        if ($requestedLimit === null) {
            $normalizedSql .= ' LIMIT ' . ($maxRows + 1);
            $limitEnforced = true;
        }

        return [
            'sql' => $normalizedSql,
            'display_sql' => $this->collapseWhitespace($normalizedSql),
            'limit_enforced' => $limitEnforced,
        ];
    }

    /**
     * @return true|\WP_Error
     */
    private function validateTables(string $sql)
    {
        preg_match_all('/\bWITH(?:\s+RECURSIVE)?\s+([A-Za-z_][A-Za-z0-9_]*)\s+AS\s*\(|,\s*([A-Za-z_][A-Za-z0-9_]*)\s+AS\s*\(/i', $sql, $cteMatches);
        $cteNames = array_map('strtolower', array_filter(array_merge($cteMatches[1] ?? [], $cteMatches[2] ?? [])));

        preg_match_all('/\b(?:FROM|JOIN)\s+(`?[A-Za-z0-9_]+`?(?:\.`?[A-Za-z0-9_]+`?)?)/i', $sql, $tableMatches);
        $tableReferences = $tableMatches[1] ?? [];

        if (!$tableReferences) {
            return new \WP_Error('sql_no_tables_detected', 'SQL fallback queries must reference approved FluentCart tables.');
        }

        $allowedTables = array_map('strtolower', $this->getAllowedTables());

        foreach ($tableReferences as $tableReference) {
            $tableName = strtolower($this->normalizeTableReference($tableReference));

            if ($tableName === '' || in_array($tableName, $cteNames, true)) {
                continue;
            }

            if (!in_array($tableName, $allowedTables, true)) {
                return new \WP_Error(
                    'sql_table_not_allowed',
                    sprintf('The table "%s" is not approved for SQL fallback.', $tableName)
                );
            }
        }

        return true;
    }

    private function detectRequestedLimit(string $sql): ?int
    {
        if (!preg_match('/\bLIMIT\s+(\d+)(?:\s*,\s*(\d+)|\s+OFFSET\s+(\d+))?\b/i', $sql, $matches)) {
            return null;
        }

        if (!empty($matches[2])) {
            return (int) $matches[2];
        }

        return (int) $matches[1];
    }

    public function getAllowedTables(): array
    {
        $prefix = $this->wpdb->prefix;
        $tables = [
            $prefix . 'fct_customers',
            $prefix . 'fct_orders',
            $prefix . 'fct_order_items',
            $prefix . 'fct_subscriptions',
            $prefix . 'fct_order_transactions',
            $prefix . 'fct_coupons',
            $prefix . 'fct_order_addresses',
            $prefix . 'fct_product_variations',
            $prefix . 'fct_product_details',
            $prefix . 'fct_order_operations',
        ];

        $tables = apply_filters('fluent_toolkit_ai_allowed_sql_tables', $tables, $prefix);

        return array_values(array_unique(array_filter(array_map('strval', (array) $tables))));
    }

    public function getAllowedTablesForPrompt(): array
    {
        $prefix = $this->wpdb->prefix;

        return array_map(
            static fn (string $table): string => preg_replace('/^' . preg_quote($prefix, '/') . '/', '{prefix}', $table, 1),
            $this->getAllowedTables()
        );
    }

    private function replaceTablePrefixPlaceholder(string $sql): string
    {
        return str_replace('{prefix}', $this->wpdb->prefix, $sql);
    }

    private function normalizeTableReference(string $tableReference): string
    {
        $tableReference = str_replace('`', '', trim($tableReference));

        if (strpos($tableReference, '.') !== false) {
            $parts = explode('.', $tableReference);
            $tableReference = (string) end($parts);
        }

        return $tableReference;
    }

    private function stripQuotedLiterals(string $sql): string
    {
        return (string) preg_replace(
            "/'(?:''|\\\\'|[^'])*'|\"(?:\\\\\"|[^\"])*\"/s",
            "''",
            $sql
        );
    }

    private function collapseWhitespace(string $sql): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $sql));
    }

    private function containsWildcardProjection(string $sql): bool
    {
        if (!preg_match('/^\s*SELECT\s+(?:DISTINCT\s+)?(.+?)\bFROM\b/is', $sql, $matches)) {
            return false;
        }

        $selectClause = trim((string) ($matches[1] ?? ''));
        if ($selectClause === '') {
            return false;
        }

        if (preg_match('/(^|,)\s*\*\s*(,|$)/', $selectClause)) {
            return true;
        }

        if (preg_match('/(^|,)\s*[A-Za-z_][A-Za-z0-9_]*\.\*\s*(,|$)/', $selectClause)) {
            return true;
        }

        return false;
    }

    private function buildExecutionHint(string $error): string
    {
        $normalizedError = strtolower($error);

        if (str_contains($normalizedError, 'unknown column') || str_contains($normalizedError, 'unknown table')) {
            return 'Inspect the schema with get_sql_schema_reference and retry with exact table and column names.';
        }

        if (str_contains($normalizedError, 'group by')) {
            return 'Check aggregate columns and GROUP BY expressions, then retry with a valid grouped query.';
        }

        return 'Review the schema reference and retry with a narrower, corrected read-only query.';
    }

    private function approvalTransientKey(): string
    {
        return 'fluent_ai_sql_fallback_' . get_current_user_id();
    }
}
