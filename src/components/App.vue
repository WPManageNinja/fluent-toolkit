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
            const route = this.hash.replace(/^#\/?/, '');

            if (route === 'mcp-auth' || route === 'mcp-oauth') {
                return 'mcp_oauth';
            }

            return 'dashboard';
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
