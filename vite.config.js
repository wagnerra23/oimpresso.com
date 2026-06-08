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
  // Dev server: usado só localmente via `npm run dev` (HMR).
  // Prod canon = `https://oimpresso.com/` (Hostinger, deploy via quick-sync.yml).
  // Não há sandbox separado — ver memory/reference/sandbox-hostnames.md.
  server: {
    allowedHosts: ['oimpresso.com', 'localhost'],
    host: true,
    port: 5173,
    https: true,
    hmr: {
      protocol: 'wss',
      host: 'oimpresso.com',
      port: 5173,
    },
  },
});