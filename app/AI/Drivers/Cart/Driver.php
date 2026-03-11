<?php

namespace FluentToolkit\AI\Drivers\Cart;

use FluentToolkit\AI\Common\DriverInterface;
use FluentToolkit\AI\Common\MessageRepository;
use FluentToolkit\AI\Common\SessionRepository;
use FluentToolkit\AI\Common\Settings;

class Driver implements DriverInterface
{
    public function slug(): string
    {
        return 'cart';
    }

    public function label(): string
    {
        return 'FluentCart';
    }

    public function canBoot(): bool
    {
        return defined('FLUENTCART_VERSION');
    }

    public function register(): void
    {
        add_action('fluent_cart/admin_js_loaded', function () {
            $scriptPath = FLUENT_TOOLKIT_PLUGIN_PATH . 'dist/ai-widget/js/app.js';
            $stylePath = FLUENT_TOOLKIT_PLUGIN_PATH . 'dist/ai-widget/css/app.css';

            if (!file_exists($scriptPath) || !file_exists($stylePath)) {
                return;
            }

            $urlBase = FLUENT_TOOLKIT_PLUGIN_URL;
            $chatHandle = 'fluent-ai';
            wp_enqueue_script($chatHandle, $urlBase . 'dist/ai-widget/js/app.js', [], FLUENT_TOOLKIT_VERSION, true);
            wp_enqueue_style($chatHandle, $urlBase . 'dist/ai-widget/css/app.css', [], FLUENT_TOOLKIT_VERSION);

            wp_localize_script($chatHandle, 'fluent_ai_vars', [
                'nonce'           => wp_create_nonce('wp_rest'),
                'api_base'        => esc_url_raw(rest_url('fluent-ai/v1/' . $this->slug())),
                'driver'          => $this->slug(),
                'storage_key'     => 'fluent_ai_chat_id_' . $this->slug(),
                'assistant_label' => 'Fluent AI',
                'welcome_message' => 'Ask me anything about your store\'s data.',
            ]);
        });
    }

    public function checkPermission(): bool
    {
        return current_user_can('manage_options');
    }

    public function handleChatRequest(\WP_REST_Request $request)
    {
        $params = $request->get_params();

        $message = sanitize_text_field($params['message'] ?? '');
        $sessionId = absint($params['session_id'] ?? 0);
        $userId = get_current_user_id();

        return $this->handleNativeChatRequest($message, $sessionId, $userId);
    }

    public function createSession(\WP_REST_Request $request)
    {
        $title = 'New Conversation at ' . wp_date('M d, H:i');
        $id = $this->createNewSession($title);

        return rest_ensure_response([
            'id'            => $id,
            'title'         => $title,
            'current_state' => 'draft',
            'created_at'    => current_time('mysql')
        ]);
    }

    public function getSessions(\WP_REST_Request $request)
    {
        $user_id = get_current_user_id();
        $repository = new SessionRepository();

        $perPage = $request->get_param('per_page') ? absint($request->get_param('per_page')) : 50;
        $page = $request->get_param('page') ? absint($request->get_param('page')) : 1;

        $sessions = $repository->listByUser($user_id, $perPage, $page, $this->slug());
        $total = $repository->countByUser($user_id, $this->slug());

        return rest_ensure_response([
            'sessions' => $sessions,
            'total'    => (int)$total,
            'page'     => $page,
            'per_page' => $perPage
        ]);
    }

    public function getMessages(\WP_REST_Request $request)
    {
        $session_id = (int)$request->get_param('id');
        $user_id = get_current_user_id();
        $session = (new SessionRepository())->find($session_id, $user_id, $this->slug());

        if (!$session) {
            return new \WP_Error('session_not_found', 'Session not found', [
                'status' => 404
            ]);
        }

        $filteredMessages = $this->getNormalizedMessages((int) $session['id']);

        return rest_ensure_response([
            'id'            => $session['id'],
            'messages'      => $filteredMessages,
            'current_state' => ($session['current_state'] ?? '') ?: ($session['status'] ?? 'draft')
        ]);
    }

    private function createNewSession($title = 'New Chat')
    {
        return (new SessionRepository())->create([
            'driver'          => $this->slug(),
            'user_id'         => get_current_user_id(),
            'title'           => $title,
            'provider'        => 'openai',
            'model'           => (new Settings())->getOpenAiModel(),
            'current_state'   => 'draft',
            'created_at'      => current_time('mysql'),
            'updated_at'      => current_time('mysql'),
            'last_message_at' => current_time('mysql'),
        ]);
    }

    private function handleErrorResponse(\WP_Error $error)
    {
        return rest_ensure_response($error);
    }

    private function handleNativeChatRequest(string $message, int $sessionId, int $userId)
    {
        if ($message === '') {
            return $this->getNativeChatStatus($sessionId, $userId);
        }

        $orchestrator = new ChatOrchestrator($this->slug());
        $result = $orchestrator->submitUserMessage($message, $sessionId, $userId);

        if (is_wp_error($result)) {
            return $this->handleErrorResponse($result);
        }

        $session = $result['session'] ?? null;
        $currentState = $session['status'] ?? ($session['current_state'] ?? 'completed');
        $returnedSessionId = (int) ($result['session_id'] ?? $sessionId);
        $latestMessage = (new MessageRepository())->latestForSession($returnedSessionId);
        $renderedMessage = $result['message'] ?? '';

        if ($latestMessage && ($latestMessage['role'] ?? '') === 'assistant') {
            $renderedMessage = (string) ($latestMessage['content_html'] ?: $latestMessage['content_raw']);
        }

        return rest_ensure_response([
            'message'       => $renderedMessage,
            'session_id'    => $returnedSessionId,
            'current_state' => $currentState,
            'status'        => 'success',
            'session'       => $session,
            'is_new'        => !$sessionId || $returnedSessionId !== $sessionId,
        ]);
    }

    private function getNativeChatStatus(int $sessionId, int $userId)
    {
        if (!$sessionId) {
            return rest_ensure_response([
                'message'       => '',
                'session_id'    => 0,
                'current_state' => 'draft',
                'status'        => 'success',
                'is_new'        => false,
            ]);
        }

        $session = (new SessionRepository())->find($sessionId, $userId, $this->slug());
        if (!$session) {
            return $this->handleErrorResponse(new \WP_Error('session_not_found', 'Session not found', [
                'status' => 404
            ]));
        }

        $lastMessage = (new MessageRepository())->latestForSession($sessionId);
        $message = '';

        if ($lastMessage && ($lastMessage['role'] ?? '') === 'assistant') {
            $message = (string) ($lastMessage['content_html'] ?: $lastMessage['content_raw']);
        }

        return rest_ensure_response([
            'message'       => $message,
            'session_id'    => $sessionId,
            'current_state' => $session['status'] ?? ($session['current_state'] ?? 'completed'),
            'status'        => 'success',
            'session'       => $session,
            'is_new'        => false,
        ]);
    }

    private function getNormalizedMessages(int $sessionId): array
    {
        $messages = (new MessageRepository())->listForSession($sessionId, [
            'include_system' => false,
            'limit' => 200,
        ]);

        $formatted = [];

        foreach ($messages as $message) {
            if (!in_array($message['role'], ['user', 'assistant'], true)) {
                continue;
            }

            $content = $message['role'] === 'assistant'
                ? (string) ($message['content_html'] ?: $message['content_raw'])
                : (string) $message['content_raw'];

            $formatted[] = [
                'role' => $message['role'],
                'content' => $content,
                'date' => $message['created_at'],
            ];
        }

        return $formatted;
    }

}
