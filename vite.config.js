import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
  plugins: [
    laravel({
      input: ['resources/sass/tailwind/tailwind.scss'],
    }),
  ],
  build: {
    outDir: 'public',
    manifest: false,
    emptyOutDir: false,
    assetsDir: 'fonts',
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
    allowedHosts: ['oi.wr2.com.br', 'localhost'],
    host: true,
    port: 5173,
    https: true,
    hmr: {
      protocol: 'wss',
      host: 'oi.wr2.com.br',
      port: 5173,
    },
  },
});