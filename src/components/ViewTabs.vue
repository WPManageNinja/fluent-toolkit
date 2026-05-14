<template>
    <nav class="ft-view-tabs" aria-label="Fluent Toolkit sections">
        <button
            class="ft-view-tab"
            :aria-selected="activeView === 'dashboard'"
            @click="$emit('navigate', 'dashboard')"
        >
            Beta & Add-ons
        </button>
        <button
            class="ft-view-tab"
            :aria-selected="activeView === 'mcp'"
            @click="$emit('navigate', 'mcp')"
        >
            MCP
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
};
</script>
