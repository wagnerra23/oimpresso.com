import '../css/inertia.css';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot, hydrateRoot } from 'react-dom/client';
import { Toaster } from 'sonner';
import { StrictMode } from 'react';

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
