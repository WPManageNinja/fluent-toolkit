<script>
    import {onMount, onDestroy, tick} from 'svelte';
    import {fade, fly} from 'svelte/transition';
    import {api} from '../lib/api';
    import ChatHistory from './ChatHistory.svelte';

    const MAX_STATUS_RETRIES = 6;
    const STATUS_POLL_DELAY = 8000;
    const POLLABLE_STATES = ['queued', 'running', 'waiting_tool'];
    const AI_CONFIG = typeof window !== 'undefined' ? (window.fluent_ai_vars || {}) : {};
    const SESSION_STORAGE_KEY = AI_CONFIG.storage_key || 'fluent_ai_chat_id';
    const ASSISTANT_LABEL = AI_CONFIG.assistant_label || 'Fluent AI';
    const WELCOME_MESSAGE = AI_CONFIG.welcome_message || 'Ask me anything about your data.';

    let isOpen = false;
    let messages = [];
    let userInput = '';
    let current_state = '';
    let isThinking = false;
    let chatContainer;
    let sessionId = '';
    let showSessions = false;
    let isFullScreen = false;
    let sessions = [];
    let retryCount = 0;
    let hasError = false;
    let statusTimer = null;
    let messagesAbortController = null;

    onMount(() => {
        const savedSessionId = localStorage.getItem(SESSION_STORAGE_KEY);

        if (savedSessionId) {
            sessionId = savedSessionId;
            fetchMessages();
        }
    });

    $: if (typeof document !== 'undefined') {
        document.body.style.overflow = isOpen && isFullScreen ? 'hidden' : '';
    }

    onDestroy(() => {
        clearStatusTimer();
        abortMessagesRequest();

        if (typeof document !== 'undefined') {
            document.body.style.overflow = '';
        }
    });

    function isPollableState(state = '') {
        return POLLABLE_STATES.includes(state);
    }

    function clearStatusTimer() {
        if (statusTimer) {
            clearTimeout(statusTimer);
            statusTimer = null;
        }
    }

    function abortMessagesRequest() {
        if (messagesAbortController) {
            messagesAbortController.abort();
            messagesAbortController = null;
        }
    }

    function updateCurrentState(nextState = '') {
        current_state = nextState || 'draft';

        if (!isPollableState(current_state)) {
            retryCount = 0;
        }
    }

    function syncSessionId(nextSessionId) {
        if (!nextSessionId) {
            return false;
        }

        const normalizedSessionId = String(nextSessionId);

        if (sessionId === normalizedSessionId) {
            return false;
        }

        sessionId = normalizedSessionId;
        localStorage.setItem(SESSION_STORAGE_KEY, sessionId);

        return true;
    }

    function clearSavedSession() {
        sessionId = '';
        localStorage.removeItem(SESSION_STORAGE_KEY);
    }

    function clearTrailingErrors() {
        let endIndex = messages.length;

        while (endIndex > 0 && messages[endIndex - 1]?.role === 'error') {
            endIndex -= 1;
        }

        if (endIndex !== messages.length) {
            messages = messages.slice(0, endIndex);
        }
    }

    function appendErrorMessage(content) {
        const lastMessage = messages[messages.length - 1];

        if (lastMessage?.role === 'error' && lastMessage.content === content) {
            return;
        }

        messages = [...messages, {
            role: 'error',
            content
        }];
    }

    function upsertAssistantMessage(content) {
        if (!content) {
            return;
        }

        clearTrailingErrors();

        let lastUserIndex = -1;
        for (let index = messages.length - 1; index >= 0; index -= 1) {
            if (messages[index]?.role === 'user') {
                lastUserIndex = index;
                break;
            }
        }

        let assistantIndex = -1;
        for (let index = messages.length - 1; index > lastUserIndex; index -= 1) {
            if (messages[index]?.role === 'assistant') {
                assistantIndex = index;
                break;
            }
        }

        if (assistantIndex === -1) {
            messages = [...messages, {role: 'assistant', content}];
            return;
        }

        if (messages[assistantIndex].content === content) {
            return;
        }

        messages = messages.map((message, index) => (
            index === assistantIndex
                ? {...message, content}
                : message
        ));
    }

    function scheduleStatusPoll() {
        clearStatusTimer();

        if (
            !isOpen ||
            !sessionId ||
            hasError ||
            !isPollableState(current_state) ||
            retryCount >= MAX_STATUS_RETRIES
        ) {
            return;
        }

        statusTimer = setTimeout(async () => {
            retryCount += 1;
            await pollStatus();
        }, STATUS_POLL_DELAY);
    }

    async function requestChat(message = '', isStatus = false) {
        try {
            const response = await api.request('chat', {
                message,
                session_id: sessionId
            });

            if (syncSessionId(response.session_id)) {
                fetchSessions();
            }

            if (response.current_state) {
                updateCurrentState(response.current_state);
            }

            if (response.message) {
                upsertAssistantMessage(response.message);
            }

            hasError = false;
        } catch (error) {
            if (error?.name === 'AbortError') {
                return;
            }

            if (error?.status === 404) {
                hasError = true;
                createNewSession();
                appendErrorMessage('This chat session is no longer available. Please start a new chat session.');
                return;
            }

            hasError = !isStatus;

            appendErrorMessage(
                isStatus
                    ? 'The assistant is still working, but the latest status check failed. Please wait a moment or retry.'
                    : 'Sorry, something went wrong. Please try again or create a new chat session.'
            );
        } finally {
            isThinking = false;
            await scrollToBottom();
            scheduleStatusPoll();
        }
    }

    async function submitMessage() {
        const question = userInput.trim();

        if (!question || isThinking) {
            return;
        }

        clearStatusTimer();
        hasError = false;
        retryCount = 0;
        messages = [...messages, {role: 'user', content: question}];
        userInput = '';
        isThinking = true;
        await scrollToBottom();
        await requestChat(question, false);
    }

    async function pollStatus() {
        if (!sessionId || hasError || !isPollableState(current_state)) {
            return;
        }

        clearStatusTimer();
        isThinking = true;
        await requestChat('', true);
    }

    async function scrollToBottom() {
        await tick();

        if (chatContainer) {
            chatContainer.scrollTop = chatContainer.scrollHeight;
        }
    }

    function toggleFullScreen() {
        isFullScreen = !isFullScreen;
        setTimeout(scrollToBottom, 300);
    }

    function toggleChat() {
        isOpen = !isOpen;

        if (isOpen) {
            scrollToBottom();

            if (!messages.length && sessionId) {
                fetchMessages();
            } else {
                scheduleStatusPoll();
            }

            return;
        }

        clearStatusTimer();
    }

    async function fetchMessages() {
        if (!sessionId) {
            return;
        }

        clearStatusTimer();
        abortMessagesRequest();
        isThinking = true;

        const requestedSessionId = sessionId;
        const controller = new AbortController();
        messagesAbortController = controller;

        try {
            const response = await api.request(
                'sessions/' + requestedSessionId + '/messages',
                null,
                'GET',
                {signal: controller.signal}
            );

            if (requestedSessionId !== sessionId) {
                return;
            }

            messages = response.messages || [];
            updateCurrentState(response.current_state);
            hasError = false;
        } catch (error) {
            if (error?.name === 'AbortError') {
                return;
            }

            if (error?.status === 404) {
                createNewSession();
                return;
            }

            console.error('Failed to fetch messages:', error);
        } finally {
            if (messagesAbortController === controller) {
                messagesAbortController = null;
            }

            isThinking = false;
            await scrollToBottom();
            scheduleStatusPoll();
        }
    }

    async function fetchSessions() {
        try {
            const response = await api.request('sessions', null, 'GET');

            if (response?.sessions) {
                sessions = response.sessions;
            }
        } catch (error) {
            console.error('Failed to fetch sessions', error);
        }
    }

    function handleSessionSelect(event) {
        const session = event.detail;

        if (String(session.id) === sessionId) {
            showSessions = false;
            scheduleStatusPoll();
            return;
        }

        clearStatusTimer();
        abortMessagesRequest();
        sessionId = String(session.id);
        localStorage.setItem(SESSION_STORAGE_KEY, sessionId);
        messages = [];
        showSessions = false;
        hasError = false;
        retryCount = 0;
        current_state = 'draft';
        fetchMessages();
    }

    function createNewSession() {
        clearStatusTimer();
        abortMessagesRequest();
        messages = [];
        clearSavedSession();
        userInput = '';
        showSessions = false;
        isThinking = false;
        hasError = false;
        current_state = 'draft';
        retryCount = 0;
    }

    function toggleSessions() {
        showSessions = !showSessions;

        if (showSessions) {
            fetchSessions();
        }
    }
</script>

<button
    class="w-14 h-14 bg-blue-600 hover:bg-blue-700 text-white rounded-full shadow-lg flex items-center justify-center transition-all duration-300 transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
    on:click={toggleChat}
    aria-label="Open Chat"
    style="z-index: 9998"
>
    {#if isOpen}
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
        </svg>
    {:else}
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
        </svg>
    {/if}
</button>

{#if isOpen}
    <div
        class="fixed bg-white flex flex-col font-sans transition-all duration-300 ease-in-out shadow-2xl overflow-hidden {isFullScreen ? 'inset-0 z-[9999] rounded-none' : 'bottom-24 right-6 w-96 h-[600px] rounded-2xl border border-gray-100 z-[9999]'}"
        transition:fly={{ y: 20, duration: 300 }}
    >
        <div
            class="bg-gradient-to-r from-blue-600 to-blue-700 p-4 text-white flex justify-between items-center shadow-md shrink-0 relative z-20">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 bg-white/20 rounded-full flex items-center justify-center backdrop-blur-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                         stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                </div>
                <div class="ai_header_info">
                    <h3 class="font-semibold text-sm text-white">{ASSISTANT_LABEL}</h3>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <button
                    class="p-1.5 hover:bg-white/10 rounded-lg transition-colors"
                    on:click={toggleFullScreen}
                    title={isFullScreen ? 'Exit Full Screen' : 'Full Screen'}
                >
                    {#if isFullScreen}
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                             stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M4 10V4a1 1 0 011-1h6m-1 12h6a1 1 0 011 1v6m-6-13l6 6M4 20l6-6"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M4 14h6v6M20 10h-6V4"/>
                        </svg>
                    {:else}
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                             stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/>
                        </svg>
                    {/if}
                </button>

                <button
                    class="p-1.5 rounded-lg transition-colors {showSessions ? 'bg-white/20' : 'hover:bg-white/10'}"
                    on:click={toggleSessions}
                    title="History"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                         stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </button>

                <button
                    class="p-1.5 hover:bg-white/10 rounded-lg transition-colors"
                    on:click={toggleChat}
                    title="Close"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                         stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>

        <div class="flex-1 overflow-hidden relative flex">
            {#if showSessions}
                <div
                    class="{isFullScreen ? 'relative z-10' : 'absolute inset-0 z-20'} flex h-full"
                    transition:fade={{duration: 150}}
                >
                    <ChatHistory
                        sessions={sessions}
                        activeSessionId={sessionId}
                        on:select={handleSessionSelect}
                        on:new={createNewSession}
                    />

                    {#if !isFullScreen}
                        <button
                            class="flex-1 bg-black/20 backdrop-blur-sm w-full cursor-default"
                            on:click={() => showSessions = false}
                            aria-label="Close history"
                        ></button>
                    {/if}
                </div>
            {/if}

            <div class="flex-1 flex flex-col h-full overflow-hidden bg-gray-50 relative">
                <div class="flex-1 overflow-y-auto p-4 space-y-4" bind:this={chatContainer}>
                    <div class="{isFullScreen ? 'max-w-3xl mx-auto w-full' : 'w-full'}">
                        {#if messages.length === 0}
                            <div
                                class="flex flex-col items-center justify-center h-full text-gray-400 text-center px-6 min-h-[400px]">
                                <div
                                    class="w-16 h-16 bg-blue-50 rounded-full flex items-center justify-center mb-4 text-blue-500">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24"
                                         stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                                    </svg>
                                </div>
                                <p class="text-sm">Welcome to {ASSISTANT_LABEL}! <br>{WELCOME_MESSAGE}</p>
                            </div>
                        {/if}

                        <div class="space-y-4 pb-4">
                            {#each messages as msg}
                                <div class="flex {msg.role === 'user' ? 'justify-end' : 'justify-start'}">
                                    <div
                                        class="max-w-[85%] rounded-2xl px-4 py-2.5 text-sm shadow-sm {msg.role === 'user' ? 'bg-blue-600 text-white rounded-br-none' : 'bg-white text-gray-800 border border-gray-100 rounded-bl-none'}">
                                        {#if msg.role === 'error'}
                                            <span class="text-red-500">{msg.content}</span>
                                        {:else if msg.role === 'assistant'}
                                            <div class="message-rich-content">
                                                {@html msg.content}
                                            </div>
                                        {:else}
                                            <span class="message-plain-content">{msg.content}</span>
                                        {/if}
                                    </div>
                                </div>
                            {/each}
                        </div>

                        {#if isThinking}
                            <div class="flex justify-start">
                                <div class="bg-white border border-gray-100 rounded-2xl rounded-bl-none px-4 py-3 shadow-sm">
                                    <div class="flex gap-1.5">
                                        <span class="w-1.5 h-1.5 bg-gray-400 rounded-full animate-bounce"></span>
                                        <span class="w-1.5 h-1.5 bg-gray-400 rounded-full animate-bounce"
                                              style="animation-delay: 0.1s"></span>
                                        <span class="w-1.5 h-1.5 bg-gray-400 rounded-full animate-bounce"
                                              style="animation-delay: 0.2s"></span>
                                    </div>
                                </div>
                            </div>
                        {/if}
                    </div>
                </div>

                <div class="p-4 bg-white border-t border-gray-100 shrink-0 relative z-30">
                    <div class="{isFullScreen ? 'max-w-3xl mx-auto w-full' : 'w-full'}">
                        <form on:submit|preventDefault={submitMessage} class="relative">
                            <input
                                type="text"
                                bind:value={userInput}
                                placeholder="Ask a question..."
                                class="w-full pl-4 pr-12 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all text-sm"
                                disabled={isThinking}
                            />
                            <button
                                type="submit"
                                class="absolute right-2 top-1/2 -translate-y-1/2 p-1.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                                disabled={!userInput.trim() || isThinking}
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                                     stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                                </svg>
                            </button>
                        </form>
                        <div class="text-center mt-2">
                            <span class="text-[10px] text-gray-400">Powered by {ASSISTANT_LABEL} {retryCount ? `(${retryCount})` : ''}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
{/if}
