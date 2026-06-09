import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');

    // VITE_BUILD_BASE controls the public URL prefix of built assets.
    // Default /build/ works for standard setups (document root = public/).
    // Shared-hosting sub-path example: VITE_BUILD_BASE=/hamandish/build/
    const buildBase = env.VITE_BUILD_BASE || '/build/';

    // Derive the build directory name from the last segment of the base path.
    // /build/ → 'build' | /hamandish/build/ → 'build'
    const buildDir = buildBase.replace(/\/$/, '').split('/').pop() || 'build';

    return {
        base: buildBase,
        plugins: [
            laravel({
                input: ['resources/css/app.css', 'resources/js/app.js'],
                refresh: true,
                buildDirectory: buildDir,
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
