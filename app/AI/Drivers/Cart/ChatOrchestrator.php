<?php

namespace FluentToolkit\AI\Drivers\Cart;

use FluentToolkit\AI\Common\MessageRepository;
use FluentToolkit\AI\Common\Settings;
use FluentToolkit\AI\Common\SessionRepository;
use FluentToolkit\AI\Common\ToolCallRepository;

class ChatOrchestrator
{
    private string $driver;
    private Settings $settings;
    private StoreContext $storeContext;
    private SessionRepository $sessions;
    private MessageRepository $messages;
    private ToolCallRepository $toolCalls;
    private PromptBuilder $promptBuilder;
    private ProviderInterface $provider;
    private ToolRegistry $toolRegistry;

    public function __construct(
        string $driver = 'cart',
        ?Settings $settings = null,
        ?StoreContext $storeContext = null,
        ?SessionRepository $sessions = null,
        ?MessageRepository $messages = null,
        ?ToolCallRepository $toolCalls = null,
        ?PromptBuilder $promptBuilder = null,
        ?ProviderInterface $provider = null,
        ?ToolRegistry $toolRegistry = null
    ) {
        $this->driver = sanitize_key($driver) ?: 'cart';
        $this->settings = $settings ?: new Settings();
        $this->storeContext = $storeContext ?: new StoreContext();
        $this->sessions = $sessions ?: new SessionRepository();
        $this->messages = $messages ?: new MessageRepository();
        $this->toolCalls = $toolCalls ?: new ToolCallRepository();
        $this->promptBuilder = $promptBuilder ?: new PromptBuilder($this->settings, $this->storeContext);
        $this->provider = $provider ?: new OpenAIProvider($this->settings);
        $sqlGuard = new SqlGuard($this->settings, $this->storeContext);
        $this->toolRegistry = $toolRegistry ?: new ToolRegistry([
            new GetProductCatalogSummary(),
            new GetSalesSummary(),
            new GetDetailedStoreReport(),
            new GetSalesTrend(),
            new GetTopProducts(),
            new GetTopCustomers(),
            new GetOrderStatusBreakdown(),
            new GetPaymentMethodBreakdown(),
            new GetRefundSummary(),
            new GetSubscriptionSummary(),
            new GetCouponUsage(),
            new GetOrdersByDateRange(),
            new GetRecentOrders(),
            new GetCustomerLtv(),
            new QueryOrdersAggregate(),
            new QueryProductsAggregate(),
            new QueryCustomersAggregate(),
            new GetSqlSchemaReference($sqlGuard),
            new RequestSqlFallback($sqlGuard),
            new RunReadonlySqlBatch($sqlGuard),
        ]);
    }

    /**
     * @return array|\WP_Error
     */
    public function submitUserMessage(string $message, int $sessionId = 0, ?int $userId = null)
    {
        $userId = $userId ?? get_current_user_id();
        $message = trim($message);

        if ($message === '') {
            return new \WP_Error('empty_message', 'A message is required.');
        }

        $session = $sessionId ? $this->sessions->find($sessionId, $userId, $this->driver) : null;

        if (!$session) {
            $sessionId = $this->sessions->create([
                'driver' => $this->driver,
                'user_id' => $userId,
                'provider' => $this->provider->getName(),
                'model' => $this->settings->getOpenAiModel(),
                'status' => 'queued',
                'current_state' => 'queued',
            ]);
            $session = $this->sessions->find($sessionId, $userId, $this->driver);
        }

        $inserted = $this->messages->append($sessionId, [
            'role' => 'user',
            'kind' => 'input',
            'content_raw' => $message,
        ]);

        if (is_wp_error($inserted)) {
            return $inserted;
        }

        return $this->processSession($sessionId, $userId);
    }

    /**
     * @return array|\WP_Error
     */
    public function processSession(int $sessionId, ?int $userId = null)
    {
        $session = $this->sessions->find($sessionId, $userId, $this->driver);

        if (!$session) {
            return new \WP_Error('session_not_found', 'Session not found.');
        }

        $this->sessions->update($sessionId, [
            'status' => 'running',
            'current_state' => 'running',
            'last_error' => null,
        ]);

        $messages = $this->messages->listForPrompt($sessionId, $this->settings->getPromptWindow());
        $currencyContext = $this->storeContext->getCurrencyContext();
        $payload = $this->promptBuilder->buildTurnPayload($session, $messages, [
            'tools' => $this->toolRegistry->getDefinitions(),
            'metadata' => [
                'session_id' => (string) $sessionId,
                'site_url' => get_site_url(),
                'store_currency' => $currencyContext['currency'] ?? '',
                'store_currency_sign' => $currencyContext['currency_sign'] ?? '',
                'store_currency_position' => $currencyContext['currency_position'] ?? '',
            ],
        ]);

        $response = $this->provider->createTurn($payload);

        if (is_wp_error($response)) {
            $this->sessions->markError($sessionId, $response->get_error_message());
            return $response;
        }

        $toolRounds = 0;
        $maxToolRounds = $this->settings->getMaxToolRounds();

        while (!empty($response['tool_calls']) && $toolRounds < $maxToolRounds) {
            $this->sessions->update($sessionId, [
                'status' => 'waiting_tool',
                'current_state' => 'waiting_tool',
                'last_response_id' => $response['response_id'] ?? $session['last_response_id'],
            ]);

            $toolOutputs = [];

            foreach ($response['tool_calls'] as $call) {
                $toolCallId = (string) ($call['call_id'] ?? wp_generate_uuid4());
                $toolName = (string) ($call['name'] ?? '');
                $arguments = is_array($call['arguments'] ?? null) ? $call['arguments'] : [];

                $this->toolCalls->create([
                    'session_id' => $sessionId,
                    'tool_call_id' => $toolCallId,
                    'tool_name' => $toolName,
                    'arguments' => $arguments,
                ]);
                $this->toolCalls->markRunning($toolCallId);

                $result = $this->toolRegistry->execute($toolName, $arguments);

                if (is_wp_error($result)) {
                    $errorMessage = $result->get_error_message();
                    $this->toolCalls->markFailed($toolCallId, $errorMessage);
                    $toolOutput = [
                        'error' => [
                            'code' => $result->get_error_code(),
                            'message' => $errorMessage,
                            'data' => $result->get_error_data(),
                        ],
                    ];
                } else {
                    $this->toolCalls->markSucceeded($toolCallId, $result);
                    $toolOutput = $result;
                }

                $this->messages->append($sessionId, [
                    'role' => 'tool',
                    'kind' => 'tool_result',
                    'tool_name' => $toolName,
                    'tool_call_id' => $toolCallId,
                    'content_raw' => wp_json_encode($toolOutput),
                    'metadata' => [
                        'arguments' => $arguments,
                    ],
                ]);

                $toolOutputs[] = [
                    'type' => 'function_call_output',
                    'call_id' => $toolCallId,
                    'output' => wp_json_encode($toolOutput),
                ];
            }

            $response = $this->provider->continueWithToolOutputs(
                (string) ($response['response_id'] ?? ''),
                $toolOutputs,
                [
                    'model' => $session['model'] ?? $this->settings->getOpenAiModel(),
                    'instructions' => $this->promptBuilder->buildSystemInstructions($session),
                    'tools' => $this->toolRegistry->getDefinitions(),
                    'metadata' => [
                        'session_id' => (string) $sessionId,
                        'store_currency' => $currencyContext['currency'] ?? '',
                        'store_currency_sign' => $currencyContext['currency_sign'] ?? '',
                        'store_currency_position' => $currencyContext['currency_position'] ?? '',
                    ],
                ]
            );

            if (is_wp_error($response)) {
                $this->sessions->markError($sessionId, $response->get_error_message());
                return $response;
            }

            $toolRounds++;
        }

        if (!empty($response['tool_calls'])) {
            $error = new \WP_Error('tool_round_limit', 'The AI exceeded the maximum number of tool rounds.');
            $this->sessions->markError($sessionId, $error->get_error_message());
            return $error;
        }

        $assistantMessage = trim((string) ($response['output_text'] ?? ''));

        if ($assistantMessage !== '') {
            $inserted = $this->messages->append($sessionId, [
                'role' => 'assistant',
                'kind' => 'output_text',
                'content_raw' => $assistantMessage,
                'provider_message_id' => $response['response_id'] ?? null,
                'input_tokens' => $response['usage']['input_tokens'] ?? null,
                'output_tokens' => $response['usage']['output_tokens'] ?? null,
            ]);

            if (is_wp_error($inserted)) {
                $this->sessions->markError($sessionId, $inserted->get_error_message());
                return $inserted;
            }
        }

        $this->sessions->update($sessionId, [
            'provider' => $this->provider->getName(),
            'model' => $session['model'] ?? $this->settings->getOpenAiModel(),
            'last_response_id' => $response['response_id'] ?? $session['last_response_id'],
            'status' => 'completed',
            'current_state' => 'completed',
            'last_error' => null,
        ]);

        return [
            'session_id' => $sessionId,
            'message' => $assistantMessage,
            'response' => $response,
            'session' => $this->sessions->find($sessionId, $userId, $this->driver),
        ];
    }
}
