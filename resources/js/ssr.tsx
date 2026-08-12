import ReactDOMServer from 'react-dom/server';
import { createInertiaApp } from '@inertiajs/react';
import createServer from '@inertiajs/react/server';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';

const appName = import.meta.env.VITE_APP_NAME || 'OI Impresso';

// ── Descoberta de páginas: núcleo + módulos (ESPELHO de app.tsx) ──────────────
// Sincronizado À MÃO com `app.tsx`. A segunda ponta existe porque trocar o glob só no client
// passava verde até 2026-08-12 — o UC-2 do `CoworkBundleIntegralTest` fechou esse buraco, e o
// UC-4 cobre o glob de módulos. Raciocínio completo no comentário de `app.tsx`.
const paginasDoNucleo = import.meta.glob('./Pages/**/*.tsx');
const paginasDeModulos = import.meta.glob('../../Modules/*/Resources/js/Pages/**/*.tsx');

function montarPaginas(): Record<string, () => Promise<unknown>> {
  const mapa: Record<string, () => Promise<unknown>> = { ...paginasDoNucleo };
  for (const [caminho, carregar] of Object.entries(paginasDeModulos)) {
    const m = caminho.match(/^\.\.\/\.\.\/Modules\/[^/]+\/[Rr]esources\/js\/(Pages\/.+)$/);
    if (m) mapa[`./${m[1]}`] = carregar;
  }
  return mapa;
}
const paginas = montarPaginas();

createServer((page) =>
  createInertiaApp({
    page,
    render: ReactDOMServer.renderToString,
    title: (title) => (title ? `${title} · ${appName}` : appName),
    resolve: (name) => resolvePageComponent(`./Pages/${name}.tsx`, paginas),
    // TanStack Query no SSR (ADR 0211): QueryClient criado DENTRO do setup,
    // 1× por request, NUNCA singleton module-level — cache não pode vazar entre
    // requests SSR / tenants (Tier 0 ADR 0093). Sem DevTools no server.
    setup: ({ App, props }) => {
      const queryClient = new QueryClient({
        defaultOptions: {
          queries: { staleTime: 60_000, gcTime: 5 * 60_000, retry: 1 },
        },
      });
      return (
        <QueryClientProvider client={queryClient}>
          <App {...props} />
        </QueryClientProvider>
      );
    },
  }),
);
