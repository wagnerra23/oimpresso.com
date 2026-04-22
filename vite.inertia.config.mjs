import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import path from 'node:path';

export default defineConfig({
  plugins: [
    laravel({
      input: ['resources/css/inertia.css', 'resources/js/app.tsx'],
      ssr: 'resources/js/ssr.tsx',
      refresh: true,
      buildDirectory: 'build-inertia',
    }),
    react(),
    tailwindcss(),
  ],
  css: {
    postcss: { plugins: [] },
  },
  resolve: {
    alias: {
      '@': path.resolve(process.cwd(), 'resources/js'),
    },
  },
  server: {
    allowedHosts: ['oi.wr2.com.br', 'oimpresso.test', 'localhost'],
    host: true,
    port: 5174,
    strictPort: true,
    hmr: { host: 'localhost' },
  },
});
