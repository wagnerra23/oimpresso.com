import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
// Onda 3 (ADR 0210) — Wayfinder gera tipos TS de rotas/FormRequests/Inertia props
// em watch durante `npm run dev:inertia`, eliminando R8 (type drift backend↔frontend).
// Pacote npm publicado é `@laravel/vite-plugin-wayfinder` (export nomeado `wayfinder`),
// NÃO `@laravel/wayfinder/vite`. Validado contra dist v0.1.7 (registry npm 2026-05-28).
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import path from 'node:path';
import fs from 'node:fs';

// Wayfinder roda `php artisan wayfinder:generate` no build/dev — exige PHP +
// vendor/autoload.php presentes. O job CI "Frontend / Vite build" é JS-only
// (sem composer install) e o artisan aborta (fatal: vendor/autoload.php
// ausente), derrubando o build inteiro. Guard: só habilita o plugin quando o
// backend está instalado. Dev local + deploy prod (composer install → npm
// build) geram os tipos normalmente; CI JS-only pula sem quebrar. Nada importa
// rotas geradas pelo Wayfinder ainda (consumo é FASE 2 — Sells/Create piloto).
const backendInstalled = fs.existsSync(
  path.resolve(process.cwd(), 'vendor/autoload.php'),
);

export default defineConfig({
  plugins: [
    laravel({
      input: ['resources/css/inertia.css', 'resources/js/app.tsx'],
      ssr: 'resources/js/ssr.tsx',
      refresh: true,
      buildDirectory: 'build-inertia',
    }),
    // Gera helpers de rotas + actions tipados. `formVariants` habilita
    // helpers .form() pra submits Inertia type-safe (ADR 0210 Fase 2 piloto).
    ...(backendInstalled ? [wayfinder({ formVariants: true })] : []),
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
