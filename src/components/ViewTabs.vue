<template>
    <nav class="ft-view-tabs" aria-label="Fluent Toolkit sections">
        <button
            class="ft-view-tab"
            aria-selected="true"
            @click="$emit('navigate', navTarget)"
        >
            {{ navLabel }}
        </button>
    </nav>
</template>

<script>
export default {
    name: 'ViewTabs',
    props: {
        activeView: {
            type: String,
            default: 'dashboard',
        },
    },
    emits: ['navigate'],
    computed: {
        navTarget() {
            return this.activeView === 'mcp' ? 'dashboard' : 'mcp';
        },
        navLabel() {
            return this.activeView === 'mcp' ? 'Dashboard' : 'MCP';
        },
    },
};
</script>
