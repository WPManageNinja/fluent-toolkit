<?php

namespace FluentToolkit\AI\Common;

class SessionRepository
{
    private \wpdb $wpdb;

    public function __construct(?\wpdb $database = null)
    {
        global $wpdb;

        $this->wpdb = $database ?: $wpdb;
    }

    public function create(array $data = []): int
    {
        $defaults = [
            'user_id'                   => get_current_user_id(),
            'title'                     => 'New Chat',
            'provider'                  => 'openai',
            'model'                     => null,
            'provider_conversation_id'  => null,
            'last_response_id'          => null,
            'status'                    => 'draft',
            'current_state'             => 'draft',
            'summary'                   => null,
            'last_error'                => null,
            'last_message_at'           => current_time('mysql'),
            'created_at'                => current_time('mysql'),
            'updated_at'                => current_time('mysql'),
        ];

        $allowed = array_merge(['driver' => true], array_fill_keys(array_keys($defaults), true));
        $payload = array_merge($defaults, array_intersect_key($data, $allowed));

        $this->wpdb->insert($this->table(), $payload);

        return (int) $this->wpdb->insert_id;
    }

    public function find(int $sessionId, ?int $userId = null, ?string $driver = null): ?array
    {
        if ($userId !== null && $driver !== null) {
            $row = $this->wpdb->get_row(
                $this->wpdb->prepare(
                    "SELECT * FROM {$this->table()} WHERE id = %d AND user_id = %d AND driver = %s",
                    $sessionId,
                    $userId,
                    $driver
                ),
                ARRAY_A
            );
        } elseif ($userId !== null) {
            $row = $this->wpdb->get_row(
                $this->wpdb->prepare(
                    "SELECT * FROM {$this->table()} WHERE id = %d AND user_id = %d",
                    $sessionId,
                    $userId
                ),
                ARRAY_A
            );
        } elseif ($driver !== null) {
            $row = $this->wpdb->get_row(
                $this->wpdb->prepare(
                    "SELECT * FROM {$this->table()} WHERE id = %d AND driver = %s",
                    $sessionId,
                    $driver
                ),
                ARRAY_A
            );
        } else {
            $row = $this->wpdb->get_row(
                $this->wpdb->prepare(
                    "SELECT * FROM {$this->table()} WHERE id = %d",
                    $sessionId
                ),
                ARRAY_A
            );
        }

        return $row ?: null;
    }

    public function listByUser(int $userId, int $perPage = 50, int $page = 1, ?string $driver = null): array
    {
        $perPage = max(1, min(100, $perPage));
        $page = max(1, $page);

        $where = 'user_id = %d AND deleted_at IS NULL';
        $bindings = [$userId];

        if ($driver !== null) {
            $where .= ' AND driver = %s';
            $bindings[] = $driver;
        }

        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table()}
                WHERE {$where}
                ORDER BY updated_at DESC
                LIMIT %d OFFSET %d",
                ...array_merge($bindings, [$perPage, ($page - 1) * $perPage])
            ),
            ARRAY_A
        );
    }

    public function countByUser(int $userId, ?string $driver = null): int
    {
        $where = 'user_id = %d AND deleted_at IS NULL';
        $bindings = [$userId];

        if ($driver !== null) {
            $where .= ' AND driver = %s';
            $bindings[] = $driver;
        }

        return (int) $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table()} WHERE {$where}",
                ...$bindings
            )
        );
    }

    public function update(int $sessionId, array $data): bool
    {
        $allowed = [
            'driver',
            'title',
            'provider',
            'model',
            'provider_conversation_id',
            'last_response_id',
            'status',
            'current_state',
            'summary',
            'last_error',
            'last_message_at',
            'updated_at',
            'deleted_at',
        ];

        $payload = array_intersect_key($data, array_flip($allowed));

        if (!$payload) {
            return false;
        }

        if (!isset($payload['updated_at'])) {
            $payload['updated_at'] = current_time('mysql');
        }

        return false !== $this->wpdb->update(
            $this->table(),
            $payload,
            ['id' => $sessionId]
        );
    }

    public function touch(int $sessionId, ?string $status = null): void
    {
        $payload = [
            'updated_at' => current_time('mysql'),
        ];

        if ($status !== null) {
            $payload['status'] = $status;
        }

        $this->update($sessionId, $payload);
    }

    public function markError(int $sessionId, string $message): void
    {
        $this->update($sessionId, [
            'status'      => 'error',
            'last_error'  => $message,
            'updated_at'  => current_time('mysql'),
        ]);
    }

    private function table(): string
    {
        return $this->wpdb->prefix . 'fai_sessions';
    }
}
