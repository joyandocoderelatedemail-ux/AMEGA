import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    experimental: {
        // Files referenced from CSS (the Font Awesome web fonts) sit next to the
        // stylesheet in build/assets, so link them relatively. An absolute
        // /build/... URL breaks when the app is served from a sub-folder, as it
        // is locally under /htdocs2/amegatravelandtour/public/.
        renderBuiltUrl(filename, { hostType }) {
            if (hostType === 'css') {
                return { relative: true };
            }
        },
    },
});
