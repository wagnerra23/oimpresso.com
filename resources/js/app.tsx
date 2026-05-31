import '../css/inertia.css';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot, hydrateRoot } from 'react-dom/client';
import { Toaster } from 'sonner';
import { StrictMode } from 'react';
import { configureEcho } from '@laravel/echo-react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ReactQueryDevtools } from '@tanstack/react-query-devtools';

configureEcho({
    broadcaster: 'reverb',
});

const appName = import.meta.env.VITE_APP_NAME || 'OI Impresso';

// TanStack Query (Onda 4, ADR 0211 — R7 raiz). Factory em vez de singleton
// module-level: o `setup` do Inertia client roda 1× por carga de página (o
// QueryClient persiste através das navegações Inertia — cache compartilhado é
// desejado). MAS app.tsx também é importado no caminho SSR; criar dentro do
// `setup` garante que cada request SSR tenha sua própria instância e não vaze
// cache entre requests/tenants (Tier 0 ADR 0093). Ver também ssr.tsx.
//   - staleTime 60s: Larissa raramente recarrega no PDV → menos refetch
//   - gcTime 5min: mantém cache após unmount durante a sessão
//   - retry 1: endpoints UPOS falham geralmente de forma definitiva (401/422)
function makeQueryClient(): QueryClient {
    return new QueryClient({
        defaultOptions: {
            queries: {
                staleTime: 60_000,
                gcTime: 5 * 60_000,
                retry: 1,
            },
        },
    });
}

createInertiaApp({
  title: (title) => (title ? `${title} · ${appName}` : appName),
  resolve: (name) =>
    resolvePageComponent(
      `./Pages/${name}.tsx`,
      import.meta.glob('./Pages/**/*.tsx'),
    ),
  setup({ el, App, props }) {
    const queryClient = makeQueryClient();
    const tree = (
      <StrictMode>
        <QueryClientProvider client={queryClient}>
          <App {...props} />
          <Toaster position="top-right" richColors closeButton />
          {import.meta.env.DEV && (
            <ReactQueryDevtools initialIsOpen={false} />
          )}
        </QueryClientProvider>
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
