<?php

namespace FluentToolkit\AI\Drivers\Cart;

class RunReadonlySqlBatch implements ToolInterface
{
    private SqlGuard $sqlGuard;

    public function __construct(?SqlGuard $sqlGuard = null)
    {
        $this->sqlGuard = $sqlGuard ?: new SqlGuard();
    }

    public function getName(): string
    {
        return 'run_readonly_sql_batch';
    }

    public function getDefinition(): array
    {
        return [
            'type' => 'function',
            'name' => $this->getName(),
            'description' => 'Run one or more guarded read-only SQL queries against approved FluentCart tables. Use this only after request_sql_fallback and usually after get_sql_schema_reference. Queries must use explicit columns, approved tables, and compact read-only analytical shapes.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'approval_token' => [
                        'type' => 'string',
                        'description' => 'Short-lived token returned by request_sql_fallback.',
                    ],
                    'reason' => [
                        'type' => 'string',
                        'description' => 'Short explanation of why SQL fallback is needed.',
                    ],
                    'queries' => [
                        'type' => 'array',
                        'description' => 'One to a few read-only SELECT or WITH queries. Each query must target only approved FluentCart tables.',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'name' => [
                                    'type' => 'string',
                                    'description' => 'Short stable label for the query result.',
                                ],
                                'sql' => [
                                    'type' => 'string',
                                    'description' => 'A single read-only SELECT or WITH query. {prefix} placeholders are allowed.',
                                ],
                            ],
                            'required' => ['name', 'sql'],
                            'additionalProperties' => false,
                        ],
                    ],
                ],
                'required' => ['approval_token', 'reason', 'queries'],
                'additionalProperties' => false,
            ],
            'strict' => false,
        ];
    }

    public function execute(array $arguments)
    {
        $approvalCheck = $this->sqlGuard->verifyApprovalToken((string) ($arguments['approval_token'] ?? ''));
        if (is_wp_error($approvalCheck)) {
            return $approvalCheck;
        }

        $reason = trim((string) ($arguments['reason'] ?? ''));
        if ($reason === '') {
            return new \WP_Error('sql_fallback_reason_required', 'A short reason is required before running SQL fallback.');
        }

        $queries = is_array($arguments['queries'] ?? null) ? $arguments['queries'] : [];
        $result = $this->sqlGuard->runBatch($queries);
        if (is_wp_error($result)) {
            return $result;
        }

        $result['reason'] = $reason;

        return $result;
    }
}
