import '../css/inertia.css';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot, hydrateRoot } from 'react-dom/client';
import { Toaster } from 'sonner';
import { StrictMode } from 'react';
import { configureEcho } from '@laravel/echo-react';

configureEcho({
    broadcaster: 'reverb',
});

const appName = import.meta.env.VITE_APP_NAME || 'OI Impresso';

createInertiaApp({
  title: (title) => (title ? `${title} · ${appName}` : appName),
  resolve: (name) =>
    resolvePageComponent(
      `./Pages/${name}.tsx`,
      import.meta.glob('./Pages/**/*.tsx'),
    ),
  setup({ el, App, props }) {
    const tree = (
      <StrictMode>
        <App {...props} />
        <Toaster position="top-right" richColors closeButton />
      </StrictMode>
    );

    if (import.meta.env.SSR) {
      hydrateRoot(el, tree);
      return;
    }

    createRoot(el).render(tree);
  },
  progress: {
    color: '#4f46e5',
    showSpinner: false,
  },
});

// ── PWA Service Worker registration (US-FIN-036, Onda 30) ─────────────────
// Registra sw-financeiro.js APENAS quando o usuário está em /financeiro/*.
// Lazy load no DOMContentLoaded pra não bloquear hydration. Falha silenciosa
// (PWA é progressivo — desktop/browser velho continua funcionando normal).
if (typeof window !== 'undefined' && 'serviceWorker' in navigator) {
    const registerSw = () => {
        if (!window.location.pathname.startsWith('/financeiro')) return;
        navigator.serviceWorker
            .register('/sw-financeiro.js', { scope: '/financeiro/' })
            .catch(() => {
                // Falha silenciosa — UX não pode quebrar por causa de PWA
            });
    };
    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        registerSw();
    } else {
        window.addEventListener('DOMContentLoaded', registerSw, { once: true });
    }
}
