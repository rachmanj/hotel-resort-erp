import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.jsx'],
            refresh: true,
        }),
        react(),
        tailwindcss(),
        VitePWA({
            registerType: 'autoUpdate',
            injectRegister: 'auto',
            scope: '/',
            includeAssets: [
                'apple-touch-icon.png',
                'pwa-192x192.png',
                'pwa-512x512.png',
            ],
            manifest: {
                name: 'Pratasaba ERP',
                short_name: 'Pratasaba',
                description: 'Hotel Resort ERP — Pratasaba',
                theme_color: '#0f4c5c',
                background_color: '#0f4c5c',
                display: 'standalone',
                start_url: '/',
                scope: '/',
                icons: [
                    {
                        src: '/pwa-192x192.png',
                        sizes: '192x192',
                        type: 'image/png',
                        purpose: 'any',
                    },
                    {
                        src: '/pwa-512x512.png',
                        sizes: '512x512',
                        type: 'image/png',
                        purpose: 'any',
                    },
                    {
                        src: '/pwa-512x512.png',
                        sizes: '512x512',
                        type: 'image/png',
                        purpose: 'maskable',
                    },
                ],
            },
            workbox: {
                globPatterns: ['**/*.{js,css,html,ico,png,svg,woff,woff2}'],
                navigateFallback: '/',
                cleanupOutdatedCaches: true,
                runtimeCaching: [
                    {
                        urlPattern: ({ request, url }) => {
                            if (request.method !== 'GET') {
                                return false;
                            }
                            if (url.origin !== self.location.origin) {
                                return false;
                            }
                            const path = url.pathname;
                            if (
                                path === '/login' ||
                                path === '/logout' ||
                                path.startsWith('/login/') ||
                                path.startsWith('/logout/')
                            ) {
                                return false;
                            }
                            return request.headers.get('X-Inertia') !== null;
                        },
                        handler: 'StaleWhileRevalidate',
                        options: {
                            cacheName: 'inertia-pages',
                            expiration: {
                                maxEntries: 100,
                                maxAgeSeconds: 60 * 60 * 24,
                            },
                            cacheableResponse: {
                                statuses: [0, 200],
                            },
                            plugins: [
                                {
                                    cacheWillUpdate: async ({ response }) => {
                                        if (response?.headers?.has('Set-Cookie')) {
                                            return null;
                                        }

                                        return response;
                                    },
                                },
                            ],
                        },
                    },
                    {
                        urlPattern: ({ request, url }) => {
                            if (request.method !== 'GET') {
                                return false;
                            }
                            if (url.origin !== self.location.origin) {
                                return false;
                            }
                            const path = url.pathname;
                            if (
                                path === '/login' ||
                                path === '/logout' ||
                                path.startsWith('/login/') ||
                                path.startsWith('/logout/')
                            ) {
                                return false;
                            }
                            if (request.headers.get('X-Inertia') !== null) {
                                return false;
                            }
                            if (request.destination === 'document') {
                                return false;
                            }

                            return true;
                        },
                        handler: 'NetworkFirst',
                        options: {
                            cacheName: 'get-data',
                            networkTimeoutSeconds: 3,
                            expiration: {
                                maxEntries: 200,
                                maxAgeSeconds: 60 * 60 * 24,
                            },
                            cacheableResponse: {
                                statuses: [0, 200],
                            },
                            plugins: [
                                {
                                    cacheWillUpdate: async ({ response }) => {
                                        if (response?.headers?.has('Set-Cookie')) {
                                            return null;
                                        }

                                        return response;
                                    },
                                },
                            ],
                        },
                    },
                ],
            },
        }),
    ],
    resolve: {
        alias: {
            '@': '/resources/js',
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
