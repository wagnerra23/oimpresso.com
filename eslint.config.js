// ESLint 9 flat config — Onda 1.2 prevenção bugs MWART (ADR 0209).
//
// Modo ratchet: padrão idêntico ao `ui-lint.yml` PHP-side. Baseline JSON
// `.eslintrc-baseline.json` absorve violações pre-existentes. CI workflow
// `eslint-gate.yml` falha só em REGRESSÃO (delta > 0).
//
// Comando local: `npm run lint`
//
// Refs:
//   - ADR 0209 — ESLint 9 flat-config baseline ratchet
//   - memory/sessions/2026-05-28-arte-prevencao-bugs-mwart-larissa.md Frente 5 F5-B
//   - react.dev — https://react.dev/reference/eslint-plugin-react-hooks/lints/exhaustive-deps

import js from '@eslint/js';
import tsParser from '@typescript-eslint/parser';
import tsPlugin from '@typescript-eslint/eslint-plugin';
import reactHooks from 'eslint-plugin-react-hooks';
import jsxA11y from 'eslint-plugin-jsx-a11y';
import reactRefresh from 'eslint-plugin-react-refresh';
import globals from 'globals';

export default [
  // Ignore patterns globais — evita análise em dist/, vendor/, etc
  {
    ignores: [
      'node_modules/**',
      'vendor/**',
      'public/**',
      'storage/**',
      'bootstrap/cache/**',
      // Build outputs
      'public/build/**',
      'public/build-inertia/**',
      // Generated Wayfinder types (futuro ADR 0210)
      'resources/js/types/wayfinder/**',
      // Bundle entry compiled
      'resources/js/types/**/*.d.ts',
      // Vendor JS legacy UPOS
      'public/js/**',
      'public/vendor/**',
    ],
  },

  // Base recommended pra todos os arquivos JS/TS
  js.configs.recommended,

  // TypeScript files
  {
    files: ['resources/js/**/*.{ts,tsx}'],
    languageOptions: {
      parser: tsParser,
      ecmaVersion: 'latest',
      sourceType: 'module',
      parserOptions: {
        ecmaFeatures: { jsx: true },
      },
      globals: {
        ...globals.browser,
        ...globals.es2024,
        // Globals Inertia/Laravel
        route: 'readonly',
        Ziggy: 'readonly',
        // UPOS legacy globals
        _: 'readonly',  // lodash
        $: 'readonly',  // jQuery
        jQuery: 'readonly',
        moment: 'readonly',
        toastr: 'readonly',
        swal: 'readonly',
        Swal: 'readonly',
        axios: 'readonly',
        echo: 'readonly',
        Echo: 'readonly',
        Pusher: 'readonly',
        // Tier 0 multi-tenant signals
        __current_business_id: 'readonly',
        __mc_business_id: 'readonly',
      },
    },
    plugins: {
      '@typescript-eslint': tsPlugin,
      'react-hooks': reactHooks,
      'jsx-a11y': jsxA11y,
      'react-refresh': reactRefresh,
    },
    rules: {
      // === TypeScript recommended subset (pragmático, não-pedante) ===
      ...tsPlugin.configs.recommended.rules,
      '@typescript-eslint/no-explicit-any': 'warn',
      '@typescript-eslint/no-unused-vars': ['warn', {
        argsIgnorePattern: '^_',
        varsIgnorePattern: '^_',
      }],
      // Permite `!` (non-null assertion) — UPOS usa em vários lugares
      '@typescript-eslint/no-non-null-assertion': 'off',

      // === React Hooks recommended (CRÍTICO — captura R7-class bugs) ===
      // ADR 0209: `exhaustive-deps` sozinho teria detectado o useEffect race
      // condition do R7 ANTES do PR mergear.
      ...reactHooks.configs.recommended.rules,

      // === A11y recommended subset (sem deprecated) ===
      ...jsxA11y.configs.recommended.rules,
      // Larissa opera teclado/scanner — a11y de input crítica
      'jsx-a11y/no-autofocus': 'off', // necessário em Sells/Create input search
      'jsx-a11y/click-events-have-key-events': 'warn', // common pattern shadcn
      'jsx-a11y/no-static-element-interactions': 'warn',

      // === React Refresh (Vite HMR compat) ===
      'react-refresh/only-export-components': ['warn', { allowConstantExport: true }],
    },
  },

  // JS files (vite.config.js, etc) — config legibility only
  {
    files: ['**/*.{js,mjs,cjs}'],
    languageOptions: {
      ecmaVersion: 'latest',
      sourceType: 'module',
      globals: {
        ...globals.node,
        ...globals.es2024,
      },
    },
    rules: {
      'no-unused-vars': ['warn', { argsIgnorePattern: '^_' }],
    },
  },
];
