<?php

namespace FluentToolkit\AI\Drivers\Cart;

class GetSqlSchemaReference implements ToolInterface
{
    private SqlGuard $sqlGuard;
    private \wpdb $wpdb;

    public function __construct(?SqlGuard $sqlGuard = null, ?\wpdb $database = null)
    {
        global $wpdb;

        $this->sqlGuard = $sqlGuard ?: new SqlGuard();
        $this->wpdb = $database ?: $wpdb;
    }

    public function getName(): string
    {
        return 'get_sql_schema_reference';
    }

    public function getDefinition(): array
    {
        return [
            'type' => 'function',
            'name' => $this->getName(),
            'description' => 'Inspect the exact schema for approved FluentCart tables before composing SQL fallback queries. Use this whenever you are not certain about column names, joins, or canonical FluentCart expressions, and use it again if a SQL query fails with a schema-related error.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'tables' => [
                        'type' => 'array',
                        'description' => 'Optional subset of approved tables to inspect. Use {prefix} placeholders or full table names.',
                        'items' => [
                            'type' => 'string',
                        ],
                    ],
                ],
                'additionalProperties' => false,
            ],
            'strict' => false,
        ];
    }

    public function execute(array $arguments)
    {
        $requestedTables = is_array($arguments['tables'] ?? null) ? $arguments['tables'] : [];
        $allowedTables = $this->sqlGuard->getAllowedTables();
        $allowedLookup = array_fill_keys(array_map('strtolower', $allowedTables), true);

        $tables = [];
        foreach ($requestedTables as $table) {
            $normalized = $this->normalizeTableName((string) $table);
            if ($normalized !== '' && isset($allowedLookup[strtolower($normalized)])) {
                $tables[] = $normalized;
            }
        }

        if (!$tables) {
            $tables = $allowedTables;
        }

        $schema = [];
        foreach ($tables as $table) {
            $columns = $this->wpdb->get_results('DESCRIBE ' . $table, ARRAY_A);
            if ($this->wpdb->last_error) {
                return new \WP_Error('schema_reference_failed', $this->wpdb->last_error, [
                    'table' => $table,
                ]);
            }

            $schema[] = [
                'table' => $table,
                'placeholder_table' => preg_replace('/^' . preg_quote($this->wpdb->prefix, '/') . '/', '{prefix}', $table, 1),
                'columns' => array_map(static function (array $column): array {
                    return [
                        'name' => (string) ($column['Field'] ?? ''),
                        'type' => (string) ($column['Type'] ?? ''),
                        'nullable' => (($column['Null'] ?? '') === 'YES'),
                        'key' => (string) ($column['Key'] ?? ''),
                    ];
                }, $columns),
            ];
        }

        return [
            'prefix' => $this->wpdb->prefix,
            'store_currency' => $this->sqlGuard->getStoreCurrencyContext(),
            'reference_summary' => $this->sqlGuard->getSchemaReference(),
            'tables' => $schema,
            'join_hints' => $this->joinHints(),
            'notes' => [
                'Monetary values are typically stored as integer cents in FluentCart tables.',
                'If a query result does not include an explicit currency column, format money using the default store currency context returned by this tool.',
                'Customer names should usually be composed from first_name and last_name.',
                'Order item product titles are available on fct_order_items.post_title.',
                'Use only columns returned by this schema reference when building SQL fallback queries.',
            ],
            'recommended_process' => [
                'Start with the schema reference summary for common FluentCart patterns.',
                'Use the live table schema in this tool for exact columns and types.',
                'Compose a narrow read-only query with explicit columns only.',
                'If the first query fails, inspect the schema again and retry with corrected fields or joins.',
            ],
        ];
    }

    private function normalizeTableName(string $table): string
    {
        $table = trim($table);
        if ($table === '') {
            return '';
        }

        $table = str_replace('{prefix}', $this->wpdb->prefix, $table);
        $table = str_replace('`', '', $table);

        return $table;
    }

    private function joinHints(): array
    {
        return [
            [
                'left' => '{prefix}fct_orders.customer_id',
                'right' => '{prefix}fct_customers.id',
                'description' => 'Orders belong to customers.',
            ],
            [
                'left' => '{prefix}fct_order_items.order_id',
                'right' => '{prefix}fct_orders.id',
                'description' => 'Order items belong to orders.',
            ],
            [
                'left' => '{prefix}fct_order_items.post_id',
                'right' => '{prefix}fct_product_details.post_id',
                'description' => 'Order items can be matched to product details by post_id.',
            ],
            [
                'left' => '{prefix}fct_order_addresses.order_id',
                'right' => '{prefix}fct_orders.id',
                'description' => 'Order addresses belong to orders.',
            ],
            [
                'left' => '{prefix}fct_subscriptions.parent_order_id',
                'right' => '{prefix}fct_orders.id',
                'description' => 'Subscriptions can reference their parent order.',
            ],
            [
                'left' => '{prefix}fct_order_transactions.order_id',
                'right' => '{prefix}fct_orders.id',
                'description' => 'Transactions belong to orders.',
            ],
        ];
    }
}
