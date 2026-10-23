<template>
    <!-- Keep the dashboard alive so a running "Update all" queue survives a tab switch. -->
    <KeepAlive include="Dashboard">
        <Dashboard
            v-if="activeView === 'dashboard'"
            :active-view="activeView"
            @navigate="navigate"
        />
        <McpPage
            v-else
            :active-view="activeView"
            @navigate="navigate"
        />
    </KeepAlive>
</template>

<script>
import Dashboard from './Dashboard.vue';
import McpPage from './McpPage.vue';

export default {
    name: 'FluentToolkitApp',
    components: {
        Dashboard,
        McpPage,
    },
    data() {
        return {
            activeView: this.getViewFromHash(),
        };
    },
    methods: {
        getViewFromHash() {
            return window.location.hash === '#mcp' ? 'mcp' : 'dashboard';
        },
        navigate(view) {
            window.location.hash = view === 'mcp' ? '#mcp' : '#dashboard';
            this.activeView = view;
        },
        syncView() {
            this.activeView = this.getViewFromHash();
        },
    },
    mounted() {
        window.addEventListener('hashchange', this.syncView);
    },
    beforeUnmount() {
        window.removeEventListener('hashchange', this.syncView);
    },
};
</script>
