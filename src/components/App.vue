<template>
    <McpOAuthPage v-if="currentView === 'mcp_oauth'" />
    <Dashboard v-else />
</template>

<script>
import Dashboard from './Dashboard.vue';
import McpOAuthPage from './McpOAuthPage.vue';

export default {
    name: 'FluentToolkitApp',
    components: {
        Dashboard,
        McpOAuthPage,
    },
    data() {
        return {
            hash: window.location.hash,
        };
    },
    computed: {
        currentView() {
            return this.hash.replace(/^#\/?/, '') === 'mcp-oauth' ? 'mcp_oauth' : 'dashboard';
        },
    },
    mounted() {
        window.addEventListener('hashchange', this.syncHash);
    },
    beforeUnmount() {
        window.removeEventListener('hashchange', this.syncHash);
    },
    methods: {
        syncHash() {
            this.hash = window.location.hash;
        },
    },
};
</script>
