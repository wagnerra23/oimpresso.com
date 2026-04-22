import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
  plugins: [
    react(),
    laravel({
      input: [
        'resources/js/app.jsx',
        'resources/css/app.css', // Tailwind importado aqui
      ],
      refresh: true,
    }),
  ],
  build: {
    outDir: 'public/build',
    manifest: true,
    emptyOutDir: true,
    assetsDir: 'assets',
    rollupOptions: {
      output: {
        assetFileNames: (assetInfo) => {
          if (assetInfo.name.endsWith('.css')) {
            if (assetInfo.name.includes('tailwind')) return 'css/tailwind.css';
            return 'css/[name].css';
          }
          return 'assets/[name].[ext]';
        },
      },
    },
  },
  resolve: {
    alias: {
      '@': '/resources/js',
      '@public': '/public',
    },
  },
  server: {
    host: '0.0.0.0',
    port: 5173,
    strictPort: true,
    cors: true,
    allowedHosts: 'all',
    hmr: {
      host: 'oidev.wr2.com.br',
      port: 5173,
      protocol: 'wss',   // pode trocar para 'ws' se não quiser HTTPS no dev
      clientPort: 443,
    },
  },
});
