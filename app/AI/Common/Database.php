<?php

namespace FluentToolkit\AI\Common;

class Database
{
    public function install(): void
    {
        global $wpdb;

        $charsetCollate = $wpdb->get_charset_collate();
        $sessionsTable = $this->table('fai_sessions');
        $messagesTable = $this->table('fai_messages');
        $toolCallsTable = $this->table('fai_tool_calls');

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $previousSuppressState = $wpdb->suppress_errors();
        $wpdb->suppress_errors(true);

        $this->createTableIfMissing($sessionsTable, "CREATE TABLE {$sessionsTable} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            driver varchar(50) NOT NULL DEFAULT 'cart',
            user_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
            title varchar(255) NOT NULL DEFAULT 'New Chat',
            current_state varchar(100) DEFAULT NULL,
            provider varchar(50) DEFAULT 'openai',
            model varchar(100) DEFAULT NULL,
            provider_conversation_id varchar(191) DEFAULT NULL,
            last_response_id varchar(191) DEFAULT NULL,
            status varchar(50) DEFAULT 'draft',
            summary LONGTEXT DEFAULT NULL,
            last_error LONGTEXT DEFAULT NULL,
            last_message_at datetime DEFAULT NULL,
            deleted_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY driver_user_updated (driver, user_id, updated_at),
            KEY user_id (user_id),
            KEY status (status),
            KEY last_message_at (last_message_at)
        ) {$charsetCollate};");

        $this->createTableIfMissing($messagesTable, "CREATE TABLE {$messagesTable} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id bigint(20) UNSIGNED NOT NULL,
            role varchar(20) NOT NULL,
            kind varchar(30) NOT NULL DEFAULT 'input',
            content_raw LONGTEXT DEFAULT NULL,
            content_html LONGTEXT DEFAULT NULL,
            tool_name varchar(191) DEFAULT NULL,
            tool_call_id varchar(191) DEFAULT NULL,
            provider_message_id varchar(191) DEFAULT NULL,
            input_tokens bigint(20) UNSIGNED DEFAULT NULL,
            output_tokens bigint(20) UNSIGNED DEFAULT NULL,
            metadata LONGTEXT DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY session_id (session_id),
            KEY tool_call_id (tool_call_id),
            KEY provider_message_id (provider_message_id)
        ) {$charsetCollate};");

        $this->createTableIfMissing($toolCallsTable, "CREATE TABLE {$toolCallsTable} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id bigint(20) UNSIGNED NOT NULL,
            message_id bigint(20) UNSIGNED DEFAULT NULL,
            tool_call_id varchar(191) NOT NULL,
            tool_name varchar(191) NOT NULL,
            arguments_json LONGTEXT DEFAULT NULL,
            result_json LONGTEXT DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'requested',
            error_text LONGTEXT DEFAULT NULL,
            started_at datetime DEFAULT NULL,
            finished_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY tool_call_id (tool_call_id),
            KEY session_id (session_id),
            KEY status (status)
        ) {$charsetCollate};");

        $wpdb->suppress_errors($previousSuppressState);

    }

    private function createTableIfMissing(string $table, string $sql): void
    {
        if ($this->tableExists($table)) {
            return;
        }

        dbDelta($sql);
    }

    private function tableExists(string $table): bool
    {
        global $wpdb;

        $foundTable = $wpdb->get_var(
            $wpdb->prepare('SHOW TABLES LIKE %s', $table) // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
        );

        return $foundTable === $table;
    }

    private function table(string $suffix): string
    {
        global $wpdb;

        return $wpdb->prefix . $suffix;
    }
}
