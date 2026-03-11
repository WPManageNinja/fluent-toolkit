<?php

namespace FluentToolkit\AI\Drivers\Cart;

abstract class ToolBase implements ToolInterface
{
    protected \wpdb $wpdb;

    public function __construct(?\wpdb $database = null)
    {
        global $wpdb;

        $this->wpdb = $database ?: $wpdb;
    }

    protected function table(string $suffix): string
    {
        return $this->wpdb->prefix . $suffix;
    }

    protected function sanitizeDate(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        $timestamp = strtotime($value);

        if (!$timestamp) {
            return null;
        }

        return gmdate('Y-m-d', $timestamp);
    }

    protected function sanitizeLimit($value, int $default = 10, int $max = 100): int
    {
        $limit = absint($value);

        if ($limit < 1) {
            $limit = $default;
        }

        return min($limit, $max);
    }

    protected function successfulPaymentStatuses(): string
    {
        return "'paid','partially_paid','partially_refunded'";
    }
}
