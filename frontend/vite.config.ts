import { fileURLToPath, URL } from 'node:url'

import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'
import { VitePWA } from 'vite-plugin-pwa'

// https://vite.dev/config/
export default defineConfig({
  plugins: [
    vue(),
    tailwindcss(),
    VitePWA({
      registerType: 'autoUpdate',
      includeAssets: ['favicon.ico', 'icons/*.png', 'icons/*.svg'],
      manifest: {
        name: 'Al-Jawami Smart Campus',
        short_name: 'ASC',
        description: 'Sistem Informasi Akademik Terpadu Al-Jawami',
        theme_color: '#2563eb',
        background_color: '#ffffff',
        display: 'standalone',
        orientation: 'portrait',
        scope: '/',
        start_url: '/',
        lang: 'id',
        icons: [
          {
            src: 'icons/pwa-192x192.png',
            sizes: '192x192',
            type: 'image/png',
            purpose: 'any',
          },
          {
            src: 'icons/pwa-192x192.png',
            sizes: '192x192',
            type: 'image/png',
            purpose: 'maskable',
          },
          {
            src: 'icons/pwa-512x512.png',
            sizes: '512x512',
            type: 'image/png',
            purpose: 'any',
          },
          {
            src: 'icons/pwa-512x512.png',
            sizes: '512x512',
            type: 'image/png',
            purpose: 'maskable',
          },
        ],
      },
      workbox: {
        // Cache strategi untuk assets statis
        globPatterns: ['**/*.{js,css,html,ico,png,svg,woff2}'],
        // Exclude file asli yang besar — hanya icon yang sudah di-resize yang di-cache
        globIgnores: ['Icon ASC.png', 'icons/pwa-icon.svg'],
        // Jangan cache API calls — data akademik harus selalu fresh
        navigateFallback: '/index.html',
        navigateFallbackDenylist: [/^\/api\//],
        runtimeCaching: [
          {
            // Cache API institution/public (logo & nama institusi)
            urlPattern: /\/api\/institution\/public/,
            handler: 'StaleWhileRevalidate',
            options: {
              cacheName: 'institution-cache',
              expiration: { maxAgeSeconds: 60 * 60 * 24 }, // 1 hari
            },
          },
        ],
      },
      devOptions: {
        enabled: false, // nonaktifkan di dev mode agar tidak ganggu HMR
      },
    }),
  ],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
  server: {
    port: 3000,
    proxy: {
      '/api': {
        target: 'http://localhost:8000',
        changeOrigin: true,
      },
    },
  },
  envPrefix: 'VITE_',
})
