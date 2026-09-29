import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        tailwindcss(),
        laravel({
            input: 'resources/js/app.ts',
            refresh: true,
            // Vitest boots its own Vite server; without a separate hot file it
            // overwrites public/hot and deletes it on exit, which unhooks the
            // running dev server from Laravel.
            hotFile: process.env.VITEST ? 'storage/framework/vitest.hot' : undefined,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    test: {
        environment: 'jsdom',
        globals: true,
        setupFiles: ['./resources/js/test/setup.ts'],
        include: ['resources/js/test/**/*.test.ts'],
    },
});
