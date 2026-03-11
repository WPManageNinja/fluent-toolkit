<?php

namespace FluentToolkit\AI\Drivers\Cart;

class RequestSqlFallback implements ToolInterface
{
    private SqlGuard $sqlGuard;

    public function __construct(?SqlGuard $sqlGuard = null)
    {
        $this->sqlGuard = $sqlGuard ?: new SqlGuard();
    }

    public function getName(): string
    {
        return 'request_sql_fallback';
    }

    public function getDefinition(): array
    {
        return [
            'type' => 'function',
            'name' => $this->getName(),
            'description' => 'Use this only when the existing store tools and aggregate tools are not enough. It returns the SQL fallback constraints, approved tables, schema reference, canonical FluentCart expressions, and SQL authoring rules before you plan read-only SQL queries.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'reason' => [
                        'type' => 'string',
                        'description' => 'Short explanation of why existing tools are insufficient.',
                    ],
                ],
                'required' => ['reason'],
                'additionalProperties' => false,
            ],
            'strict' => false,
        ];
    }

    public function execute(array $arguments)
    {
        $reason = trim((string) ($arguments['reason'] ?? ''));
        if ($reason === '') {
            return new \WP_Error('sql_fallback_reason_required', 'A short reason is required before using SQL fallback.');
        }

        $approval = $this->sqlGuard->issueApprovalToken($reason);
        if (is_wp_error($approval)) {
            return $approval;
        }

        $context = $this->sqlGuard->getFallbackContext();

        return [
            'approved' => (bool) ($context['enabled'] ?? false),
            'reason' => $reason,
            'approval_token' => $approval['approval_token'],
            'expires_in_seconds' => $approval['expires_in_seconds'],
            'constraints' => $context,
            'next_step' => 'If SQL is still necessary, inspect the exact schema with get_sql_schema_reference, then call run_readonly_sql_batch with this approval_token and one to a few compact read-only queries.',
        ];
    }
}
