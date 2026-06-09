import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');

    // VITE_BUILD_BASE controls the public URL prefix of built assets.
    // On shared hosting at a sub-path (e.g. /hamandish/) set:
    //   VITE_BUILD_BASE=/hamandish/build/
    // Defaults to /build/ (standard Laravel setup with document root = public/).
    const buildBase = env.VITE_BUILD_BASE ?? '/build/';

    return {
        base: buildBase,
        plugins: [
            laravel({
                input: ['resources/css/app.css', 'resources/js/app.js'],
                refresh: true,
            }),
            vue(),
            tailwindcss(),
        ],
        server: {
            watch: {
                ignored: ['**/storage/framework/views/**'],
            },
        },
    };
});
