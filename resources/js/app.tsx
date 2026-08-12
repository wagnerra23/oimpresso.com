// FONTES SELF-HOSTED (ITEM 7 · 3c) — @font-face local, servido pelo nosso domínio.
// Substitui o <link> do Google Fonts que estava em layouts/inertia.blade.php:30-32.
// Os pesos abaixo são EXATAMENTE os que a URL do CDN pedia (sans 400;500;600;700 +
// mono 400;500) — nem um a mais, pra não inflar o bundle.
//
// POR QUE self-host: o gate visual injetava `* { font-family: Arial !important }` pra
// ter fonte determinística, e isso o cegava pra regressão de font-family (318
// declarações em resources/css, 0 com !important → o universal author-!important vence
// todas). Com @font-face local + `document.fonts.ready`, a fonte real carrega
// determinística no runner e o force sai (VisregThreshold + suítes de baseline).
// Instalar a fonte no ubuntu-24.04 NÃO resolveria: o @font-face do CDN vence o SO.
//
// Importado aqui (pipeline JS do Vite) e não via `@import` no CSS: o comentário do
// inertia.blade.php registra que `@import` dentro de CSS bundleado era descartado no
// build de produção. O import JS é o caminho recomendado pelo @fontsource e emite os
// .woff2 com hash pelo manifest do `build-inertia`.
import '@fontsource/ibm-plex-sans/400.css';
import '@fontsource/ibm-plex-sans/500.css';
import '@fontsource/ibm-plex-sans/600.css';
import '@fontsource/ibm-plex-sans/700.css';
import '@fontsource/ibm-plex-mono/400.css';
import '@fontsource/ibm-plex-mono/500.css';

import '../css/inertia.css';

import { createInertiaApp, router } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot, hydrateRoot } from 'react-dom/client';
import { Toaster, toast } from 'sonner';
import { StrictMode } from 'react';
import { configureEcho } from '@laravel/echo-react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ReactQueryDevtools } from '@tanstack/react-query-devtools';

configureEcho({
    broadcaster: 'reverb',
});

const appName = import.meta.env.VITE_APP_NAME || 'OI Impresso';

// ── Flash global → toast: padrão ÚNICO de alerta pra TODO o sistema ───────────
// Lê o flash compartilhado pelo HandleInertiaRequests (status.success/error/info,
// incluindo o status.msg do store UltimatePOS). Roda a cada visita Inertia bem
// sucedida, em QUALQUER página, independente de layout. Substitui handlers de
// flash espalhados por página. Catalogado 2026-06-04: vendas bloqueadas
// (PurchaseSellMismatch/estoque) falhavam em SILÊNCIO porque a msg do backend não
// chegava em toast nenhum. Agora todo controller que faz ->with('status', [...])
// ou ->with('error'/'success', ...) avisa o usuário automaticamente.
interface FlashBag {
  success?: unknown;
  error?: unknown;
  info?: unknown;
}
let lastFlashKey = '';
function showFlashToast(page?: { url?: string; props?: { flash?: FlashBag } }): void {
  const flash = page?.props?.flash;
  if (!flash) return;
  const error = typeof flash.error === 'string' ? flash.error : null;
  const success = typeof flash.success === 'string' ? flash.success : null;
  const info = typeof flash.info === 'string' ? flash.info : null;
  if (!error && !success && !info) return;
  // Dedupe: não repete o mesmo alerta pro mesmo estado de página.
  const key = `${page?.url ?? ''}|${error ?? ''}|${success ?? ''}|${info ?? ''}`;
  if (key === lastFlashKey) return;
  lastFlashKey = key;
  if (error) {
    toast.error(error, { duration: 8000 });
  } else if (success) {
    toast.success(success);
  } else if (info) {
    toast.info(info);
  }
}
if (typeof window !== 'undefined') {
  router.on('success', (event) => showFlashToast(event.detail.page));
}

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

    // Flash presente já no primeiro carregamento (redirect full-page) — o
    // router.on('success') só cobre visitas Inertia subsequentes.
    showFlashToast((props as { initialPage?: { url?: string; props?: { flash?: FlashBag } } }).initialPage);
  },
  progress: {
    color: '#4f46e5',
    showSpinner: false,
  },
});

// ── Rede de segurança pós-deploy: chunk que sumiu não pode matar a tela ──────
// Cada página é um chunk lazy (o `import.meta.glob('./Pages/**/*.tsx')` acima).
// Quem está com o sistema aberto quando sai um deploy continua com o manifest
// ANTIGO em memória — a próxima navegação pede um arquivo cujo hash mudou. Antes,
// o deploy trocava o diretório de bundles inteiro, então esse arquivo simplesmente
// não existia mais: 404 → "Failed to fetch dynamically imported module" → tela
// morta até o usuário descobrir sozinho que precisa dar F5.
//
// A defesa PRINCIPAL é no servidor: o deploy agora MESCLA os assets em vez de
// trocar o diretório, e só poda chunk morto há +3 dias (ver deploy.yml, passo
// "Publicar bundles"). Isto aqui é a SEGUNDA linha — cobre o chunk podado por
// idade, o cache/CDN que erra, e a aba esquecida aberta por dias.
//
// Regra de ouro: nunca recarregar por cima de dado não salvo. Só navegamos
// sozinhos quando a visita que falhou era um GET — aí o usuário já estava saindo
// da tela, e a carga completa o leva justamente aonde ele queria ir, com o
// manifest novo. Em POST/PUT a mutação já foi processada pelo servidor e um
// reload não repete o request: nesse caso só avisamos e deixamos a decisão com
// ele, pra não descartar um formulário preenchido.
if (typeof window !== 'undefined') {
    const CHAVE_RECARGA = 'oi:recarga-pos-deploy';
    let ultimoDestino: { url: string; ehGet: boolean } | null = null;

    router.on('before', (evento) => {
        const visita = evento.detail.visit;
        ultimoDestino = {
            url: String(visita.url),
            ehGet: String(visita.method).toLowerCase() === 'get',
        };
    });

    const recuperar = (motivo: string): void => {
        // Anti-loop: se já tentamos há menos de 20s, o problema não é chunk
        // stale (recarregar de novo só faria o usuário girar em falso).
        try {
            const ultima = Number(window.sessionStorage.getItem(CHAVE_RECARGA) ?? '0');
            if (Date.now() - ultima < 20_000) return;
            window.sessionStorage.setItem(CHAVE_RECARGA, String(Date.now()));
        } catch {
            return; // sessionStorage bloqueado → sem guarda, não arrisca loop
        }

        console.warn(`[deploy] chunk indisponível (${motivo}) — recuperando`);

        if (ultimoDestino?.ehGet) {
            window.location.assign(ultimoDestino.url);
            return;
        }
        toast.error(
            'Uma nova versão do sistema foi publicada. Atualize a página (F5) para continuar.',
            { duration: Infinity },
        );
    };

    // Vite avisa quando o preload de um chunk falha. Sem o preventDefault, ele
    // deixa o erro estourar e recarrega a página por conta própria.
    window.addEventListener('vite:preloadError', (evento) => {
        evento.preventDefault();
        recuperar('vite:preloadError');
    });

    // Import dinâmico que falha chega como promise rejeitada não tratada. A
    // mensagem varia por navegador — Chrome/Firefox falam em "dynamically
    // imported module", Safari em "Importing a module script failed", e o helper
    // de preload do Vite em "Unable to preload". O último normalmente já é pego
    // pelo listener acima; fica aqui também porque cobrir duas vezes é barato e
    // o custo de NÃO cobrir é a tela morta que este bloco existe pra evitar.
    window.addEventListener('unhandledrejection', (evento) => {
        const msg = String((evento.reason as Error | undefined)?.message ?? evento.reason ?? '');
        if (/dynamically imported module|Importing a module script failed|Unable to preload|Loading chunk \S+ failed/i.test(msg)) {
            recuperar('import dinâmico');
        }
    });
}

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
