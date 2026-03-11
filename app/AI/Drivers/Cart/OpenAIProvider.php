<?php

namespace FluentToolkit\AI\Drivers\Cart;

use FluentToolkit\AI\Common\Settings;

class OpenAIProvider implements ProviderInterface
{
    private Settings $settings;

    public function __construct(?Settings $settings = null)
    {
        $this->settings = $settings ?: new Settings();
    }

    public function getName(): string
    {
        return 'openai';
    }

    public function createTurn(array $payload)
    {
        return $this->request($this->buildPayload($payload));
    }

    public function continueWithToolOutputs(string $previousResponseId, array $toolOutputs, array $payload = [])
    {
        $payload['previous_response_id'] = $previousResponseId;
        $payload['input'] = $toolOutputs;

        return $this->request($this->buildPayload($payload));
    }

    private function buildPayload(array $payload): array
    {
        $shouldStore = array_key_exists('store', $payload)
            ? (bool) $payload['store']
            : $this->settings->shouldStoreProviderResponses();

        if (!empty($payload['previous_response_id']) || !empty($payload['tools'])) {
            $shouldStore = true;
        }

        return array_filter([
            'model' => $payload['model'] ?? $this->settings->getOpenAiModel(),
            'instructions' => $payload['instructions'] ?? null,
            'input' => $payload['input'] ?? [],
            'tools' => $payload['tools'] ?? [],
            'metadata' => $payload['metadata'] ?? [],
            'previous_response_id' => $payload['previous_response_id'] ?? null,
            'store' => $shouldStore,
        ], static fn ($value) => $value !== null);
    }

    private function request(array $payload)
    {
        $apiKey = $this->settings->getOpenAiApiKey();
        $recoveredMissingPreviousResponse = false;

        if ($apiKey === '') {
            return new \WP_Error('missing_openai_key', 'OpenAI API key is not configured.');
        }

        $response = wp_remote_post('https://api.openai.com/v1/responses', [
            'timeout' => 60,
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($payload),
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);

        if ($this->shouldRetryWithoutPreviousResponseId($statusCode, $decoded, $payload)) {
            $recoveredMissingPreviousResponse = true;
            unset($payload['previous_response_id']);
            $payload['store'] = true;

            $retryResponse = wp_remote_post('https://api.openai.com/v1/responses', [
                'timeout' => 60,
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ],
                'body' => wp_json_encode($payload),
            ]);

            if (is_wp_error($retryResponse)) {
                return $retryResponse;
            }

            $statusCode = wp_remote_retrieve_response_code($retryResponse);
            $body = wp_remote_retrieve_body($retryResponse);
            $decoded = json_decode($body, true);
        }

        if ($statusCode < 200 || $statusCode >= 300 || !is_array($decoded)) {
            return new \WP_Error('openai_request_failed', 'OpenAI request failed.', [
                'status_code' => $statusCode,
                'body' => $body,
            ]);
        }

        return [
            'response_id' => $decoded['id'] ?? null,
            'output_text' => $this->extractOutputText($decoded),
            'tool_calls' => $this->extractToolCalls($decoded),
            'usage' => $decoded['usage'] ?? [],
            'provider_response' => $decoded,
            'recovered_from_missing_previous_response' => $recoveredMissingPreviousResponse,
        ];
    }

    private function shouldRetryWithoutPreviousResponseId(int $statusCode, ?array $decoded, array $payload): bool
    {
        if ($statusCode !== 400 || empty($payload['previous_response_id']) || !is_array($decoded)) {
            return false;
        }

        return ($decoded['error']['code'] ?? '') === 'previous_response_not_found';
    }

    private function extractOutputText(array $response): string
    {
        $parts = [];

        foreach ($response['output'] ?? [] as $item) {
            if (($item['type'] ?? '') !== 'message') {
                continue;
            }

            foreach ($item['content'] ?? [] as $content) {
                $type = $content['type'] ?? '';
                if ($type === 'output_text' || $type === 'text' || $type === 'input_text') {
                    $text = $content['text'] ?? '';
                    if ($text !== '') {
                        $parts[] = $text;
                    }
                }
            }
        }

        return trim(implode("\n\n", $parts));
    }

    private function extractToolCalls(array $response): array
    {
        $calls = [];

        foreach ($response['output'] ?? [] as $item) {
            if (($item['type'] ?? '') !== 'function_call') {
                continue;
            }

            $argumentsJson = (string) ($item['arguments'] ?? '{}');
            $decodedArguments = json_decode($argumentsJson, true);

            $calls[] = [
                'id' => $item['id'] ?? null,
                'call_id' => $item['call_id'] ?? ($item['id'] ?? wp_generate_uuid4()),
                'name' => $item['name'] ?? '',
                'arguments_json' => $argumentsJson,
                'arguments' => is_array($decodedArguments) ? $decodedArguments : [],
            ];
        }

        return $calls;
    }
}
