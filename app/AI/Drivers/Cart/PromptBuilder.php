<?php

namespace FluentToolkit\AI\Drivers\Cart;

use FluentToolkit\AI\Common\Settings;

class PromptBuilder
{
    private Settings $settings;
    private StoreContext $storeContext;

    public function __construct(?Settings $settings = null, ?StoreContext $storeContext = null)
    {
        $this->settings = $settings ?: new Settings();
        $this->storeContext = $storeContext ?: new StoreContext();
    }

    public function buildSystemInstructions(?array $session = null): string
    {
        $today = wp_date('Y-m-d');
        $currencyContext = $this->storeContext->getCurrencyContext();

        $instructions = [
            'You are Fluent AI working inside the FluentCart admin.',
            'Use tools for store analytics instead of inventing SQL.',
            'Choose tools in this order whenever possible: dedicated business tools first, aggregate tools second, detailed store report for broad summaries, and SQL fallback only as the last resort.',
            'Available tool families cover detailed store reports, sales summaries, trends, recent orders, product performance, top customers, status breakdowns, refunds, subscriptions, payment methods, coupon usage, discount totals, customer LTV, and marketing attribution dimensions such as utm_campaign, utm_source, utm_medium, utm_content, and utm_id.',
            'For product catalog questions such as how many products exist, how many published or draft products exist, or catalog status counts, use the product catalog summary tool first.',
            'Do not use sales-based product tools such as top products or product aggregate sales metrics to answer catalog inventory or product-count questions.',
            'For product catalog breakdown questions such as how many physical products, digital products, service products, or mixed products exist, use the product catalog summary tool first because it includes fulfillment-type counts.',
            'For custom analytics questions that need metrics grouped by dimensions, prefer the aggregate query tools for orders, products, and customers.',
            'When using aggregate tools, choose explicit metrics, dimensions, filters, limit, and sort options instead of approximating the request in prose.',
            'For discount questions, use order aggregate metrics such as manual_discount_total, coupon_discount_total, and total_discount before considering SQL fallback.',
            'If the user asks for total discounts, interpret that as manual discounts plus coupon discounts unless they explicitly narrow it to coupon-only or manual-only.',
            'For attribution or marketing questions like top campaigns, earnings by utm_campaign, performance by utm_source, or paid revenue by utm_medium, use order aggregate dimensions such as utm_campaign, utm_source, utm_medium, utm_content, or utm_id before considering SQL fallback.',
            'For top-campaign or top-attribution questions, prefer actual tracked UTM values and exclude blank or unattributed rows unless the user explicitly asks to include unknown or unattributed traffic.',
            'For broad requests like "detailed report", "full report", "overview", or "performance report" for a date range, prefer the detailed store report tool before using multiple narrower tools.',
            'Use SQL fallback only when the normal tools and aggregate tools are still insufficient for the user request.',
            'Do not wait for the user to explicitly ask for SQL fallback. Decide yourself whether the existing tools are sufficient.',
            'If a tool result is only partial for the user request, continue with another tool or SQL fallback immediately instead of asking the user whether you should fetch or retrieve more data.',
            'Do not stop at “I can check that for you” when another tool call or SQL fallback can answer the question in the same turn.',
            'If SQL fallback is needed, first call request_sql_fallback to get the constraints.',
            'Use the schema reference returned by request_sql_fallback as the first source of truth for approved tables and common FluentCart columns.',
            'Before writing SQL, call get_sql_schema_reference for any approved tables you are not fully sure about. Use only columns returned by that schema tool.',
            'After checking the schema, call run_readonly_sql_batch with the approval_token and one to a few compact read-only queries.',
            'Never guess SQL column names, table names, or derived aliases. Use only exact schema fields or canonical expressions returned by the SQL schema tools.',
            'If a SQL fallback query fails because of an unknown column, unknown table, or invalid join, inspect the schema and retry with corrected SQL before replying to the user.',
            'Do not ask the user for raw database documentation or column names unless schema inspection still cannot resolve the issue.',
            'When using SQL fallback, batch related queries together, use only approved FluentCart tables, keep listings narrow, and avoid unnecessary raw record dumps.',
            'Do not expose internal tool names, approval tokens, or raw SQL to the user unless the user explicitly asks for them.',
            'Format responses in clean Markdown.',
            'Use short bullet lists for takeaways, and use Markdown tables when presenting grouped numeric data, comparisons, status breakdowns, trends, or recent records.',
            'When a table helps, include one. Keep tables compact and readable.',
            'Format monetary values with a currency symbol or currency code when it is known from the data or store context.',
            'If a tool or SQL result includes an explicit currency column, respect that currency in the answer. Otherwise use the default store currency context.',
            'Do not present bare monetary amounts without a visible currency indicator. In tables, either include the currency in the column header such as Revenue ($) or format each monetary cell with the symbol or code.',
            'Leave a blank line before and after every Markdown table so it renders correctly.',
            'When listing summary points, use proper Markdown list markers instead of plain lines.',
            'Do not wrap the whole answer in code fences.',
            'All monetary values are stored in cents in the database, but tool outputs already convert them to currency units.',
            'When the data includes mixed activity types, distinguish overall activity from paid sales instead of claiming there were no orders.',
            'Keep responses concise, factual, and grounded in the returned tool data.',
            'If the available tools are insufficient, explain the gap instead of fabricating an answer.',
            'Today is ' . $today . '. Resolve relative dates like "this year", "last year", "this month", "last month", and "last 7 days" using this date.',
            'For requests like "last 5 orders" or "recent orders", use the recent orders tool instead of guessing a date range.',
        ];

        if (!empty($currencyContext['currency'])) {
            $instructions[] = sprintf(
                'Default store currency context: code %s, symbol %s, position %s, zero-decimal %s, example %s. Use this formatting for monetary values when the data does not explicitly indicate a different currency.',
                $currencyContext['currency'],
                $currencyContext['currency_sign'] ?: $currencyContext['currency'],
                $currencyContext['currency_position'] ?? 'before',
                !empty($currencyContext['is_zero_decimal']) ? 'yes' : 'no',
                $currencyContext['format_example'] ?? ($currencyContext['currency_sign'] ?: $currencyContext['currency'])
            );
        }

        if (!empty($session['summary'])) {
            $instructions[] = 'Conversation summary: ' . wp_strip_all_tags((string) $session['summary']);
        }

        $customPrompt = $this->settings->getSystemPrompt();
        if ($customPrompt !== '') {
            $instructions[] = $customPrompt;
        }

        return implode("\n\n", $instructions);
    }

    public function buildConversationInput(array $messages, ?string $summary = null): array
    {
        $input = [];

        if ($summary) {
            $input[] = [
                'role' => 'system',
                'content' => 'Prior conversation summary: ' . $summary,
            ];
        }

        $temporalContext = $this->buildResolvedTemporalContext($messages);
        if ($temporalContext !== null) {
            $input[] = [
                'role' => 'system',
                'content' => $temporalContext,
            ];
        }

        foreach ($messages as $message) {
            $input[] = [
                'role' => $this->mapRole((string) ($message['role'] ?? 'user')),
                'content' => $this->normaliseContent($message),
            ];
        }

        return $input;
    }

    public function buildTurnPayload(array $session, array $messages, array $options = []): array
    {
        return array_filter([
            'instructions' => $this->buildSystemInstructions($session),
            'input' => $this->buildConversationInput($messages, $session['summary'] ?? null),
            'model' => $options['model'] ?? ($session['model'] ?? $this->settings->getOpenAiModel()),
            'tools' => $options['tools'] ?? [],
            'metadata' => $options['metadata'] ?? [],
            'previous_response_id' => $options['previous_response_id'] ?? null,
        ], static fn ($value) => $value !== null);
    }

    private function mapRole(string $role): string
    {
        return match ($role) {
            'assistant' => 'assistant',
            'system' => 'system',
            default => 'user',
        };
    }

    private function normaliseContent(array $message): string
    {
        $content = (string) ($message['content_raw'] ?? $message['content'] ?? '');

        if (($message['role'] ?? '') === 'tool') {
            $toolName = (string) ($message['tool_name'] ?? 'tool');
            return sprintf('Tool %s returned: %s', $toolName, $content);
        }

        return $content;
    }

    private function buildResolvedTemporalContext(array $messages): ?string
    {
        $latestUserMessage = null;

        for ($index = count($messages) - 1; $index >= 0; $index--) {
            if (($messages[$index]['role'] ?? '') === 'user') {
                $latestUserMessage = strtolower((string) ($messages[$index]['content_raw'] ?? $messages[$index]['content'] ?? ''));
                break;
            }
        }

        if (!$latestUserMessage) {
            return null;
        }

        $today = wp_date('Y-m-d');
        $mappings = [];

        if (str_contains($latestUserMessage, 'this year')) {
            $mappings[] = sprintf('this year => %s to %s', wp_date('Y-01-01'), wp_date('Y-12-31'));
        }

        if (str_contains($latestUserMessage, 'last year')) {
            $mappings[] = sprintf(
                'last year => %s to %s',
                wp_date('Y-01-01', strtotime('-1 year')),
                wp_date('Y-12-31', strtotime('-1 year'))
            );
        }

        if (str_contains($latestUserMessage, 'this month')) {
            $mappings[] = sprintf(
                'this month => %s to %s',
                wp_date('Y-m-01'),
                wp_date('Y-m-t')
            );
        }

        if (str_contains($latestUserMessage, 'last month')) {
            $mappings[] = sprintf(
                'last month => %s to %s',
                wp_date('Y-m-01', strtotime('first day of last month')),
                wp_date('Y-m-t', strtotime('last day of last month'))
            );
        }

        if (str_contains($latestUserMessage, 'this week')) {
            $mappings[] = sprintf(
                'this week => %s to %s',
                wp_date('Y-m-d', strtotime('monday this week')),
                wp_date('Y-m-d', strtotime('sunday this week'))
            );
        }

        if (str_contains($latestUserMessage, 'last week')) {
            $mappings[] = sprintf(
                'last week => %s to %s',
                wp_date('Y-m-d', strtotime('monday last week')),
                wp_date('Y-m-d', strtotime('sunday last week'))
            );
        }

        if (str_contains($latestUserMessage, 'this quarter')) {
            $mappings[] = $this->formatQuarterMapping('this quarter', 0);
        }

        if (str_contains($latestUserMessage, 'last quarter')) {
            $mappings[] = $this->formatQuarterMapping('last quarter', -1);
        }

        if (str_contains($latestUserMessage, 'last 7 days')) {
            $mappings[] = sprintf('last 7 days => %s to %s', wp_date('Y-m-d', strtotime('-6 days')), $today);
        }

        if (str_contains($latestUserMessage, 'last 30 days')) {
            $mappings[] = sprintf('last 30 days => %s to %s', wp_date('Y-m-d', strtotime('-29 days')), $today);
        }

        if (str_contains($latestUserMessage, 'today')) {
            $mappings[] = sprintf('today => %s to %s', $today, $today);
        }

        if (str_contains($latestUserMessage, 'yesterday')) {
            $yesterday = wp_date('Y-m-d', strtotime('-1 day'));
            $mappings[] = sprintf('yesterday => %s to %s', $yesterday, $yesterday);
        }

        if (!$mappings) {
            return null;
        }

        return 'Resolved relative dates for the latest user request using today ' . $today . ': ' . implode('; ', $mappings) . '. Use these exact ranges when choosing tool arguments.';
    }

    private function formatQuarterMapping(string $label, int $offsetQuarters): string
    {
        $month = (int) wp_date('n');
        $year = (int) wp_date('Y');
        $quarter = (int) ceil($month / 3);
        $quarter += $offsetQuarters;

        while ($quarter < 1) {
            $quarter += 4;
            $year--;
        }

        while ($quarter > 4) {
            $quarter -= 4;
            $year++;
        }

        $startMonth = (($quarter - 1) * 3) + 1;
        $startDate = sprintf('%04d-%02d-01', $year, $startMonth);
        $endDate = wp_date('Y-m-t', strtotime($startDate . ' +2 months'));

        return sprintf('%s => %s to %s', $label, $startDate, $endDate);
    }
}
