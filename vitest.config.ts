/// <reference types="vitest" />
// Vitest config — Onda 4 / ADR 0211 (Frente 1 ação F1-C).
//
// Setup MÍNIMO pra rodar testes de componente React em jsdom. NÃO-blocker:
// Pest PHP continua sendo o canon de testes do projeto (ProductSearchAutocomplete
// tem 11 assertions estruturais em tests/Feature/Sells/). Este config habilita
// os testes de race condition determinísticos (vi.useFakeTimers + MSW) que o
// Pest estrutural não consegue exercitar comportamentalmente.
//
// Roda separado do build Inertia — NÃO toca vite.config.js nem
// vite.inertia.config.mjs (Wave B). Alias `@` espelha o tsconfig.json.

import { defineConfig } from 'vitest/config';
import react from '@vitejs/plugin-react';
import { fileURLToPath } from 'node:url';

export default defineConfig({
  plugins: [react()],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
    },
  },
  test: {
    globals: true,
    environment: 'jsdom',
    setupFiles: ['./tests/js/setup.ts'],
    // Só os testes JS/TSX deste diretório — não tenta rodar os .php do Pest.
    include: ['tests/**/*.{test,spec}.{ts,tsx}'],
    css: false,
  },
});
