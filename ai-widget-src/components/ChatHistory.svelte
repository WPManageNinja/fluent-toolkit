<script>
    import { createEventDispatcher } from 'svelte';

    export let sessions = [];
    export let activeSessionId = '';
    
    const dispatch = createEventDispatcher();

    function selectSession(session) {
        dispatch('select', session);
    }

    function createNew() {
        dispatch('new');
    }

    function formatDate(dateString) {
        if (!dateString) return '';
        const date = new Date(dateString);
        // If today, show time, else show date
        const today = new Date();
        if (date.toDateString() === today.toDateString()) {
            return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        }
        return date.toLocaleDateString();
    }
</script>

<div class="flex flex-col h-full bg-white border-r border-gray-100 w-64 shrink-0 transition-all duration-300">
    <!-- Header -->
    <div class="p-4 border-b border-gray-100 flex items-center justify-between shrink-0">
        <h3 class="font-semibold text-gray-700">History</h3>
        <button 
            class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors text-sm font-medium flex items-center gap-1"
            on:click={createNew}
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            New
        </button>
    </div>

    <!-- Session List -->
    <div class="flex-1 overflow-y-auto p-2 space-y-1">
        {#if sessions.length === 0}
            <div class="text-center py-8 px-4 text-gray-400 text-sm">
                <p>No previous chats found.</p>
            </div>
        {/if}

        {#each sessions as session (session.id)}
            <button
                class="w-full text-left p-3 rounded-xl transition-all duration-200 group relative
                {activeSessionId === session.id 
                    ? 'bg-blue-50 text-blue-700 shadow-sm ring-1 ring-blue-100' 
                    : 'hover:bg-gray-50 text-gray-600 hover:text-gray-900'}"
                on:click={() => selectSession(session)}
            >
                <div class="flex flex-col gap-1">
                    <span class="font-medium text-sm truncate pr-2 block">
                        {session.title || 'New Conversation'}
                    </span>
                    <span class="text-[10px] opacity-60">
                        {formatDate(session.updated_at || session.created_at)}
                    </span>
                </div>
            </button>
        {/each}
    </div>
</div>

<style>
    /* Custom scrollbar for webkit */
    div::-webkit-scrollbar {
        width: 4px;
    }
    div::-webkit-scrollbar-track {
        background: transparent;
    }
    div::-webkit-scrollbar-thumb {
        background-color: #e5e7eb;
        border-radius: 20px;
    }
</style>
