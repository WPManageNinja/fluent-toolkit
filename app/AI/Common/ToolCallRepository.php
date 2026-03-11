<?php

namespace FluentToolkit\AI\Common;

class ToolCallRepository
{
    private \wpdb $wpdb;

    public function __construct(?\wpdb $database = null)
    {
        global $wpdb;

        $this->wpdb = $database ?: $wpdb;
    }

    public function create(array $data): int
    {
        $payload = [
            'session_id'      => absint($data['session_id'] ?? 0),
            'message_id'      => isset($data['message_id']) ? absint($data['message_id']) : null,
            'tool_call_id'    => (string) ($data['tool_call_id'] ?? wp_generate_uuid4()),
            'tool_name'       => (string) ($data['tool_name'] ?? ''),
            'arguments_json'  => isset($data['arguments']) ? wp_json_encode($data['arguments']) : null,
            'result_json'     => isset($data['result']) ? wp_json_encode($data['result']) : null,
            'status'          => (string) ($data['status'] ?? 'requested'),
            'error_text'      => $data['error_text'] ?? null,
            'started_at'      => $data['started_at'] ?? null,
            'finished_at'     => $data['finished_at'] ?? null,
            'created_at'      => $data['created_at'] ?? current_time('mysql'),
        ];

        $this->wpdb->insert($this->table(), $payload);

        return (int) $this->wpdb->insert_id;
    }

    public function markRunning(string $toolCallId): void
    {
        $this->wpdb->update(
            $this->table(),
            [
                'status'     => 'running',
                'started_at' => current_time('mysql'),
            ],
            ['tool_call_id' => $toolCallId],
            ['%s', '%s'],
            ['%s']
        );
    }

    public function markSucceeded(string $toolCallId, $result): void
    {
        $this->wpdb->update(
            $this->table(),
            [
                'status'       => 'succeeded',
                'result_json'  => wp_json_encode($result),
                'finished_at'  => current_time('mysql'),
                'error_text'   => null,
            ],
            ['tool_call_id' => $toolCallId],
            ['%s', '%s', '%s', '%s'],
            ['%s']
        );
    }

    public function markFailed(string $toolCallId, string $message): void
    {
        $this->wpdb->update(
            $this->table(),
            [
                'status'      => 'failed',
                'error_text'  => $message,
                'finished_at' => current_time('mysql'),
            ],
            ['tool_call_id' => $toolCallId],
            ['%s', '%s', '%s'],
            ['%s']
        );
    }

    public function listForSession(int $sessionId): array
    {
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table()} WHERE session_id = %d ORDER BY id ASC",
                $sessionId
            ),
            ARRAY_A
        );
    }

    private function table(): string
    {
        return $this->wpdb->prefix . 'fai_tool_calls';
    }
}
