import ReactDOMServer from 'react-dom/server';
import { createInertiaApp } from '@inertiajs/react';
import createServer from '@inertiajs/react/server';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';

const appName = import.meta.env.VITE_APP_NAME || 'OI Impresso';

createServer((page) =>
  createInertiaApp({
    page,
    render: ReactDOMServer.renderToString,
    title: (title) => (title ? `${title} · ${appName}` : appName),
    resolve: (name) =>
      resolvePageComponent(
        `./Pages/${name}.tsx`,
        import.meta.glob('./Pages/**/*.tsx'),
      ),
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
