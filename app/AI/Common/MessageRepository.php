<?php

namespace FluentToolkit\AI\Common;

use FluentToolkit\AI\Common\MarkdownRenderer;

class MessageRepository
{
    private \wpdb $wpdb;
    private MarkdownRenderer $renderer;

    public function __construct(?MarkdownRenderer $renderer = null, ?\wpdb $database = null)
    {
        global $wpdb;

        $this->wpdb = $database ?: $wpdb;
        $this->renderer = $renderer ?: new MarkdownRenderer();
    }

    public function append(int $sessionId, array $message)
    {
        $role = sanitize_key((string) ($message['role'] ?? 'user'));
        $kind = sanitize_key((string) ($message['kind'] ?? $this->defaultKindForRole($role)));
        $contentRaw = isset($message['content_raw']) ? (string) $message['content_raw'] : '';
        $contentHtml = $message['content_html'] ?? null;

        if ($contentHtml === null && $role === 'assistant' && $contentRaw !== '') {
            $contentHtml = $this->renderer->render($contentRaw);
        }

        $payload = [
            'session_id'           => $sessionId,
            'role'                 => in_array($role, ['system', 'user', 'assistant', 'tool'], true) ? $role : 'user',
            'kind'                 => $kind ?: 'input',
            'content_raw'          => $contentRaw,
            'content_html'         => $contentHtml,
            'tool_name'            => $message['tool_name'] ?? null,
            'tool_call_id'         => $message['tool_call_id'] ?? null,
            'provider_message_id'  => $message['provider_message_id'] ?? null,
            'input_tokens'         => isset($message['input_tokens']) ? absint($message['input_tokens']) : null,
            'output_tokens'        => isset($message['output_tokens']) ? absint($message['output_tokens']) : null,
            'metadata'             => isset($message['metadata']) ? wp_json_encode($message['metadata']) : null,
            'created_at'           => $message['created_at'] ?? current_time('mysql'),
        ];

        $inserted = $this->wpdb->insert($this->table(), $payload);

        if ($inserted === false) {
            return new \WP_Error('message_insert_failed', 'Could not store the message.');
        }

        $this->wpdb->update(
            $this->wpdb->prefix . 'fai_sessions',
            [
                'last_message_at' => $payload['created_at'],
                'updated_at'      => current_time('mysql'),
            ],
            ['id' => $sessionId],
            ['%s', '%s'],
            ['%d']
        );

        return (int) $this->wpdb->insert_id;
    }

    public function listForSession(int $sessionId, array $args = []): array
    {
        $limit = isset($args['limit']) ? max(1, min(200, absint($args['limit']))) : 100;
        $offset = isset($args['offset']) ? max(0, absint($args['offset'])) : 0;
        $includeSystem = !isset($args['include_system']) || (bool) $args['include_system'];

        $where = 'session_id = %d';
        $bind = [$sessionId];

        if (!$includeSystem) {
            $where .= " AND role != 'system'";
        }

        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->table()}
            WHERE {$where}
            ORDER BY id ASC
            LIMIT %d OFFSET %d",
            ...array_merge($bind, [$limit, $offset])
        );

        return $this->decodeRows($this->wpdb->get_results($sql, ARRAY_A));
    }

    public function listForPrompt(int $sessionId, int $limit = 12): array
    {
        $limit = max(1, min(40, $limit));

        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM (
                    SELECT * FROM {$this->table()}
                    WHERE session_id = %d
                    ORDER BY id DESC
                    LIMIT %d
                ) AS prompt_messages
                ORDER BY id ASC",
                $sessionId,
                $limit
            ),
            ARRAY_A
        );

        return $this->decodeRows($rows);
    }

    public function latestForSession(int $sessionId): ?array
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table()} WHERE session_id = %d ORDER BY id DESC LIMIT 1",
                $sessionId
            ),
            ARRAY_A
        );

        if (!$row) {
            return null;
        }

        $decoded = $this->decodeRows([$row]);

        return $decoded ? $decoded[0] : null;
    }

    private function decodeRows(array $rows): array
    {
        foreach ($rows as &$row) {
            $row['metadata'] = !empty($row['metadata']) ? json_decode($row['metadata'], true) : null;
        }

        return $rows;
    }

    private function defaultKindForRole(string $role): string
    {
        return match ($role) {
            'assistant' => 'output_text',
            'tool' => 'tool_result',
            'system' => 'summary',
            default => 'input',
        };
    }

    private function table(): string
    {
        return $this->wpdb->prefix . 'fai_messages';
    }
}
