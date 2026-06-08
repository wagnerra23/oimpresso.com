/**
 * Service Worker — oimpresso Financeiro PWA
 *
 * Estratégia: stale-while-revalidate pra rotas GET /financeiro/* (read-only)
 * com sanitização de response e fallback offline minimal.
 *
 * Tier 0 (US-FIN-036):
 * - SOMENTE GET. POST/PUT/DELETE NUNCA cacheado.
 * - Rotas write/mutate (`/baixar`, `/aprovar`, `/rejeitar`,
 *   `/solicitar-aprovacao`, `/conciliacao/upload`) sempre network-first.
 * - Response com `cache-control: private` ou `set-cookie` NÃO entra no cache
 *   (LGPD — evita persistir sessão/CSRF).
 * - Não toca dados de outros módulos (escopo isolado a /financeiro/*).
 *
 * Cache version: financeiro-v1 (bump pra invalidar tudo após mudanças
 * arquiteturais — força clientes a baixar nova versão na próxima visita).
 */

const CACHE_NAME = 'financeiro-v1';

// Rotas que NUNCA podem ser servidas do cache (mutate / side-effect / write).
// Match por substring no pathname.
const NETWORK_ONLY_PATTERNS = [
    '/baixar',
    '/aprovar',
    '/rejeitar',
    '/solicitar-aprovacao',
    '/conciliacao/upload',
    '/logout',
    '/sanctum',
    '/api/auth',
];

/**
 * Decide se a request deve ser network-only (skip cache).
 * - Method != GET → network-only
 * - URL casa com NETWORK_ONLY_PATTERNS → network-only
 * - URL fora de /financeiro/ → não intercepta (passa direto)
 */
function isNetworkOnly(request) {
    if (request.method !== 'GET') return true;
    const url = new URL(request.url);
    if (!url.pathname.startsWith('/financeiro')) return true; // fora do scope
    for (const pat of NETWORK_ONLY_PATTERNS) {
        if (url.pathname.includes(pat)) return true;
    }
    return false;
}

/**
 * Decide se response pode ser persistido em cache.
 * - Status 200/304 apenas
 * - SEM header `cache-control: private`
 * - SEM `set-cookie` (sessão Laravel)
 * - SEM `authorization` ecoado
 * - Content-Type: text/html, application/json, image/*, font/*, text/css, application/javascript
 */
function isCacheable(response) {
    if (!response || !response.ok) return false;
    if (response.status !== 200) return false;
    if (response.type !== 'basic' && response.type !== 'default') return false;

    const cacheControl = (response.headers.get('cache-control') || '').toLowerCase();
    if (cacheControl.includes('private')) return false;
    if (cacheControl.includes('no-store')) return false;

    if (response.headers.get('set-cookie')) return false;

    const contentType = (response.headers.get('content-type') || '').toLowerCase();
    const allowed = [
        'text/html',
        'application/json',
        'application/javascript',
        'text/css',
        'image/',
        'font/',
        'application/font',
    ];
    return allowed.some((prefix) => contentType.startsWith(prefix));
}

/**
 * Sanitiza response antes do cache.put — remove headers sensíveis defensivamente.
 * (response.clone() é necessário porque body é stream consumível 1x.)
 */
async function sanitizeForCache(response) {
    const cloned = response.clone();
    const headers = new Headers(cloned.headers);
    // Limpa headers sensíveis (defesa em profundidade — isCacheable já bloqueia
    // a maioria, mas se algo passar, garantimos sanitização)
    headers.delete('set-cookie');
    headers.delete('authorization');
    headers.delete('x-csrf-token');
    headers.delete('x-xsrf-token');

    const body = await cloned.blob();
    return new Response(body, {
        status: cloned.status,
        statusText: cloned.statusText,
        headers,
    });
}

/**
 * Fallback offline pra fetches de API/JSON.
 * Pra HTML, devolve resposta HTML minimal com mensagem em PT-BR.
 */
function offlineFallback(request) {
    const accept = request.headers.get('accept') || '';
    if (accept.includes('application/json')) {
        return new Response(
            JSON.stringify({ offline: true, message: 'Sem conexão. Tente novamente quando voltar online.' }),
            {
                status: 503,
                statusText: 'Offline',
                headers: { 'Content-Type': 'application/json' },
            }
        );
    }
    return new Response(
        '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="utf-8"><title>Offline — Financeiro</title>' +
            '<meta name="viewport" content="width=device-width,initial-scale=1">' +
            '<style>body{font-family:ui-sans-serif,system-ui,sans-serif;background:#fafaf9;color:#1c1917;margin:0;padding:24px;display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:100vh}h1{font-size:20px;margin:0 0 8px}p{color:#57534e;font-size:14px;text-align:center;max-width:320px}button{margin-top:16px;background:#1c1917;color:#fafaf9;border:none;padding:10px 20px;border-radius:8px;font-size:14px;cursor:pointer}</style>' +
            '</head><body><h1>Sem conexão</h1><p>O Financeiro precisa de internet pra carregar dados novos. Tente de novo quando voltar online.</p><button onclick="location.reload()">Tentar novamente</button></body></html>',
        {
            status: 503,
            statusText: 'Offline',
            headers: { 'Content-Type': 'text/html; charset=utf-8' },
        }
    );
}

// ── INSTALL ────────────────────────────────────────────────────────────────
self.addEventListener('install', (event) => {
    // skipWaiting pra ativar nova versão imediatamente — combina com clients.claim
    self.skipWaiting();
    event.waitUntil(Promise.resolve());
});

// ── ACTIVATE ───────────────────────────────────────────────────────────────
self.addEventListener('activate', (event) => {
    event.waitUntil(
        (async () => {
            // Limpa caches de versões antigas (financeiro-v0, financeiro-vN-1 etc)
            const keys = await caches.keys();
            await Promise.all(
                keys
                    .filter((k) => k.startsWith('financeiro-') && k !== CACHE_NAME)
                    .map((k) => caches.delete(k))
            );
            await self.clients.claim();
        })()
    );
});

// ── FETCH (stale-while-revalidate) ────────────────────────────────────────
self.addEventListener('fetch', (event) => {
    const { request } = event;

    // Network-only: bypass total (POST/PUT/DELETE, rotas write, fora de /financeiro/)
    if (isNetworkOnly(request)) {
        // Fora do scope (/financeiro/): NÃO intercepta — deixa browser tratar.
        const url = new URL(request.url);
        if (!url.pathname.startsWith('/financeiro')) return;

        // Dentro de /financeiro mas mutate: tenta network direto, fallback offline.
        event.respondWith(
            fetch(request).catch(() => offlineFallback(request))
        );
        return;
    }

    // Stale-while-revalidate
    event.respondWith(
        (async () => {
            const cache = await caches.open(CACHE_NAME);
            const cached = await cache.match(request);

            const networkFetch = fetch(request)
                .then(async (response) => {
                    if (isCacheable(response)) {
                        try {
                            const sanitized = await sanitizeForCache(response);
                            await cache.put(request, sanitized);
                        } catch (e) {
                            // Sanitize/put falhou — ignora silenciosamente, não quebra UX
                        }
                    }
                    return response;
                })
                .catch(() => null);

            // Se tem cache: devolve cache imediato + revalida em background
            if (cached) {
                event.waitUntil(networkFetch);
                return cached;
            }

            // Sem cache: espera network ou fallback offline
            const fresh = await networkFetch;
            if (fresh) return fresh;
            return offlineFallback(request);
        })()
    );
});

// ── MESSAGE (canal pra UI pedir skipWaiting / clear cache) ────────────────
self.addEventListener('message', (event) => {
    if (!event.data) return;
    if (event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
    if (event.data.type === 'CLEAR_CACHE') {
        event.waitUntil(caches.delete(CACHE_NAME));
    }
});
