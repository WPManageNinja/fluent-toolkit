import { defineConfig } from 'vite';
import { svelte } from '@sveltejs/vite-plugin-svelte';

export default defineConfig({
    plugins: [svelte()],
    css: {
        preprocessorOptions: {
            scss: {
                api: 'modern-compiler',
            },
            sass: {
                api: 'modern-compiler',
            },
        },
    },
    build: {
        outDir: 'dist/ai-widget',
        assetsDir: '.',
        emptyOutDir: true,
        rollupOptions: {
            input: 'ai-widget-src/main.js',
            output: {
                entryFileNames: 'js/app.js',
                chunkFileNames: 'js/[name].js',
                assetFileNames: ({ name }) => {
                    if (/\.css$/.test(name ?? '')) {
                        return 'css/app.css';
                    }

                    return '[name].[ext]';
                },
            },
        },
    },
});
