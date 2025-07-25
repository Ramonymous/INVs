import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    build: {
        outDir: 'public/build',
        manifest: true, // ✅ Wajib di sini
        cssCodeSplit: true,
        sourcemap: false,
        minify: 'esbuild',
        rollupOptions: {
            output: {
                entryFileNames: 'RDev-[hash].js',
                chunkFileNames: 'RDev-[hash].js',
                assetFileNames: 'RDev-[hash][extname]',
            },
        },
    },
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
});
