import { fileURLToPath, URL } from 'node:url'

import { loadEnv } from 'vite'
import { defineConfig } from 'vitest/config'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'

// https://vite.dev/config/
export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '')

  // index.html uses %VITE_SITE_URL% for absolute link preview URLs; without a
  // setting (local development, tests) it points to the dev server.
  process.env.VITE_SITE_URL = (env.VITE_SITE_URL || 'http://localhost:5173').replace(/\/$/, '')

  return {
    plugins: [vue(), tailwindcss()],
    resolve: {
      alias: {
        '@': fileURLToPath(new URL('./src', import.meta.url)),
      },
    },
    server: {
      host: true,
      port: 5173,
      // Forward API calls to Laravel so the browser only talks to one origin.
      proxy: {
        '/api': {
          target: env.API_PROXY_TARGET || 'http://localhost:8000',
          changeOrigin: true,
        },
      },
    },
    build: {
      // MapLibre GL (the map renderer, ~1.2 MB) is in its own chunk that only map pages load.
      chunkSizeWarningLimit: 1300,
    },
    test: {
      environment: 'jsdom',
      setupFiles: ['./src/test/setup.js'],
    },
  }
})
