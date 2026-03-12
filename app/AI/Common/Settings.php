<?php

namespace FluentToolkit\AI\Common;

class Settings
{
    public const OPTION_KEY = 'fluent_toolkit_ai_settings';

    public function defaults(): array
    {
        return [
            'enabled'                  => 'no',
            'openai_api_key'           => '',
            'openai_model'             => 'gpt-4.1-mini',
            'system_prompt'            => '',
            'sql_fallback'             => 'yes',
            'store_provider_responses' => 'yes',
        ];
    }

    public function all(): array
    {
        $saved = get_option(self::OPTION_KEY, []);

        if (!is_array($saved)) {
            $saved = [];
        }

        return wp_parse_args($saved, $this->defaults());
    }

    public function save(array $input): array
    {
        $existing = $this->all();
        $payload = $existing;

        $payload['enabled'] = $this->normalizeBooleanFlag($input['enabled'] ?? $existing['enabled']) ? 'yes' : 'no';

        if (!$this->hasOverride('openai_model')) {
            $model = trim((string) ($input['openai_model'] ?? ''));
            $payload['openai_model'] = $model !== '' ? sanitize_text_field($model) : $existing['openai_model'];
        }

        if (!$this->hasOverride('system_prompt')) {
            $payload['system_prompt'] = sanitize_textarea_field((string) ($input['system_prompt'] ?? $existing['system_prompt']));
        }

        if (!$this->hasOverride('sql_fallback')) {
            $payload['sql_fallback'] = $this->normalizeBooleanFlag($input['sql_fallback'] ?? $existing['sql_fallback']) ? 'yes' : 'no';
        }

        if (!$this->hasOverride('store_provider_responses')) {
            $payload['store_provider_responses'] = $this->normalizeBooleanFlag($input['store_provider_responses'] ?? $existing['store_provider_responses']) ? 'yes' : 'no';
        }

        if (!$this->hasOverride('openai_api_key')) {
            if ($this->normalizeBooleanFlag($input['clear_api_key'] ?? 'no')) {
                $payload['openai_api_key'] = '';
            } else {
                $apiKey = trim((string) ($input['openai_api_key'] ?? ''));
                if ($apiKey !== '') {
                    $payload['openai_api_key'] = sanitize_text_field($apiKey);
                }
            }
        }

        update_option(self::OPTION_KEY, $payload, false);

        return $this->all();
    }

    public function isEnabled(): bool
    {
        return $this->all()['enabled'] === 'yes';
    }

    public function getOpenAiApiKey(): string
    {
        if (defined('FLUENT_TOOLKIT_AI_OPENAI_API_KEY')) {
            return trim((string) FLUENT_TOOLKIT_AI_OPENAI_API_KEY);
        }

        $envValue = getenv('FLUENT_TOOLKIT_AI_OPENAI_API_KEY');
        if ($envValue) {
            return trim((string) $envValue);
        }

        return trim((string) $this->all()['openai_api_key']);
    }

    public function getOpenAiModel(): string
    {
        if (defined('FLUENT_TOOLKIT_AI_OPENAI_MODEL')) {
            return trim((string) FLUENT_TOOLKIT_AI_OPENAI_MODEL);
        }

        $envValue = getenv('FLUENT_TOOLKIT_AI_OPENAI_MODEL');
        if ($envValue) {
            return trim((string) $envValue);
        }

        return trim((string) $this->all()['openai_model']);
    }

    public function shouldStoreProviderResponses(): bool
    {
        if (defined('FLUENT_TOOLKIT_AI_STORE_PROVIDER_RESPONSES')) {
            return (bool) FLUENT_TOOLKIT_AI_STORE_PROVIDER_RESPONSES;
        }

        $envValue = getenv('FLUENT_TOOLKIT_AI_STORE_PROVIDER_RESPONSES');
        if ($envValue !== false && $envValue !== '') {
            return $this->normalizeBooleanFlag($envValue);
        }

        return $this->all()['store_provider_responses'] === 'yes';
    }

    public function getPromptWindow(): int
    {
        $window = defined('FLUENT_TOOLKIT_AI_PROMPT_WINDOW')
            ? absint(FLUENT_TOOLKIT_AI_PROMPT_WINDOW)
            : 12;

        return max(4, min(30, $window));
    }

    public function getMaxToolRounds(): int
    {
        $rounds = defined('FLUENT_TOOLKIT_AI_MAX_TOOL_ROUNDS')
            ? absint(FLUENT_TOOLKIT_AI_MAX_TOOL_ROUNDS)
            : 4;

        return max(1, min(8, $rounds));
    }

    public function getSystemPrompt(): string
    {
        if (defined('FLUENT_TOOLKIT_AI_SYSTEM_PROMPT')) {
            return trim((string) FLUENT_TOOLKIT_AI_SYSTEM_PROMPT);
        }

        $envValue = getenv('FLUENT_TOOLKIT_AI_SYSTEM_PROMPT');
        if ($envValue) {
            return trim((string) $envValue);
        }

        return trim((string) $this->all()['system_prompt']);
    }

    public function isSqlFallbackEnabled(): bool
    {
        if (defined('FLUENT_TOOLKIT_AI_ENABLE_SQL_FALLBACK')) {
            return (bool) FLUENT_TOOLKIT_AI_ENABLE_SQL_FALLBACK;
        }

        $envValue = getenv('FLUENT_TOOLKIT_AI_ENABLE_SQL_FALLBACK');
        if ($envValue !== false && $envValue !== '') {
            return $this->normalizeBooleanFlag($envValue);
        }

        return $this->all()['sql_fallback'] === 'yes';
    }

    public function getSqlFallbackMaxQueries(): int
    {
        $value = defined('FLUENT_TOOLKIT_AI_SQL_FALLBACK_MAX_QUERIES')
            ? absint(FLUENT_TOOLKIT_AI_SQL_FALLBACK_MAX_QUERIES)
            : 4;

        return max(1, min(8, $value));
    }

    public function getSqlFallbackMaxRows(): int
    {
        $value = defined('FLUENT_TOOLKIT_AI_SQL_FALLBACK_MAX_ROWS')
            ? absint(FLUENT_TOOLKIT_AI_SQL_FALLBACK_MAX_ROWS)
            : 100;

        return max(10, min(500, $value));
    }

    public function getSqlFallbackMaxQueryLength(): int
    {
        $value = defined('FLUENT_TOOLKIT_AI_SQL_FALLBACK_MAX_QUERY_LENGTH')
            ? absint(FLUENT_TOOLKIT_AI_SQL_FALLBACK_MAX_QUERY_LENGTH)
            : 4000;

        return max(500, min(20000, $value));
    }

    public function hasCredentials(): bool
    {
        return $this->getOpenAiApiKey() !== '';
    }

    public function hasStoredApiKey(): bool
    {
        return trim((string) $this->all()['openai_api_key']) !== '';
    }

    public function getApiKeySource(): string
    {
        if ($this->hasOverride('openai_api_key')) {
            return 'constant';
        }

        if ($this->hasStoredApiKey()) {
            return 'database';
        }

        return 'none';
    }

    public function getApiKeyPreview(): string
    {
        if (!$this->hasCredentials()) {
            return '';
        }

        if ($this->hasOverride('openai_api_key')) {
            return 'Defined via FLUENT_TOOLKIT_AI_OPENAI_API_KEY';
        }

        $apiKey = $this->getOpenAiApiKey();
        $length = strlen($apiKey);

        if ($length <= 8) {
            return str_repeat('*', max(0, $length - 2)) . substr($apiKey, -2);
        }

        return substr($apiKey, 0, 4) . str_repeat('*', max(0, $length - 8)) . substr($apiKey, -4);
    }

    public function getOverrideState(): array
    {
        return [
            'openai_api_key'           => $this->hasOverride('openai_api_key'),
            'openai_model'             => $this->hasOverride('openai_model'),
            'system_prompt'            => $this->hasOverride('system_prompt'),
            'sql_fallback'             => $this->hasOverride('sql_fallback'),
            'store_provider_responses' => $this->hasOverride('store_provider_responses'),
        ];
    }

    private function hasOverride(string $field): bool
    {
        return match ($field) {
            'openai_api_key' => defined('FLUENT_TOOLKIT_AI_OPENAI_API_KEY') || getenv('FLUENT_TOOLKIT_AI_OPENAI_API_KEY') !== false,
            'openai_model' => defined('FLUENT_TOOLKIT_AI_OPENAI_MODEL') || getenv('FLUENT_TOOLKIT_AI_OPENAI_MODEL') !== false,
            'system_prompt' => defined('FLUENT_TOOLKIT_AI_SYSTEM_PROMPT') || getenv('FLUENT_TOOLKIT_AI_SYSTEM_PROMPT') !== false,
            'sql_fallback' => defined('FLUENT_TOOLKIT_AI_ENABLE_SQL_FALLBACK') || getenv('FLUENT_TOOLKIT_AI_ENABLE_SQL_FALLBACK') !== false,
            'store_provider_responses' => defined('FLUENT_TOOLKIT_AI_STORE_PROVIDER_RESPONSES') || getenv('FLUENT_TOOLKIT_AI_STORE_PROVIDER_RESPONSES') !== false,
            default => false,
        };
    }

    private function normalizeBooleanFlag($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }
}
