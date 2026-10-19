/**
 * Service Worker — portal do paciente Fisio (/fisio/p/{token}) · etapa 02 §3.6 (T-13)
 *
 * O que guarda: a página do dia, para o programa abrir sem rede, e os arquivos do build.
 * Quem decide se a página pode ficar guardada é o SERVIDOR, pelo cabeçalho
 * `X-Fisio-Offline: guardar` (PortalController::show). Ele só vem na tela "Hoje" de aparelho
 * lembrado. Qualquer outra resposta (PIN, consentimento, link inválido, revogado) APAGA a cópia
 * guardada daquele link: o PIN e o consentimento continuam valendo também offline.
 *
 * Regras duras:
 * - Só GET. POST/DELETE (PIN, consentimento, check-in) nunca passam pelo cache.
 * - `set-cookie` é removido antes de guardar (a sessão nunca fica no aparelho).
 * - Não intercepta nada fora do portal e do build (escopo /fisio/p/).
 *
 * Versão: suba `fisio-portal-v1` para descartar o cache de todos os aparelhos.
 */

const CACHE_NAME = 'fisio-portal-v1';
const PREFIXO_PORTAL = '/fisio/p/';
const PREFIXO_BUILD = '/build-inertia/';
const CABECALHO = 'x-fisio-offline';

function chaveDaPagina(url) {
    const u = new URL(url);
    return u.origin + u.pathname.replace(/\/+$/, '');
}

async function semCookie(response) {
    const headers = new Headers(response.headers);
    headers.delete('set-cookie');
    const corpo = await response.clone().blob();
    return new Response(corpo, { status: response.status, statusText: response.statusText, headers });
}

async function paginaDoPortal(request) {
    const cache = await caches.open(CACHE_NAME);
    const chave = chaveDaPagina(request.url);
    try {
        const resposta = await fetch(request);
        if (resposta.status === 200 && resposta.headers.get(CABECALHO) === 'guardar') {
            await cache.put(chave, await semCookie(resposta));
        } else {
            await cache.delete(chave);
        }
        return resposta;
    } catch (e) {
        const guardada = await cache.match(chave);
        return guardada || semRede();
    }
}

async function arquivoDoBuild(request) {
    const cache = await caches.open(CACHE_NAME);
    const guardado = await cache.match(request);
    if (guardado) return guardado;
    const resposta = await fetch(request);
    if (resposta.ok && resposta.type === 'basic') {
        await cache.put(request, resposta.clone());
    }
    return resposta;
}

function semRede() {
    return new Response(
        '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="utf-8"><title>Sem internet</title>' +
            '<meta name="viewport" content="width=device-width,initial-scale=1">' +
            '<style>body{font-family:system-ui,sans-serif;margin:0;padding:24px;display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:100vh;text-align:center}p{max-width:320px}</style>' +
            '</head><body><h1>Sem internet</h1><p>Abra o link do seu programa com internet uma vez neste aparelho, marcando "lembrar este aparelho". Depois ele abre mesmo sem conexão.</p>' +
            '<button onclick="location.reload()">Tentar de novo</button></body></html>',
        { status: 503, statusText: 'Offline', headers: { 'Content-Type': 'text/html; charset=utf-8' } }
    );
}

self.addEventListener('install', () => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((nomes) => Promise.all(nomes.filter((n) => n.startsWith('fisio-portal-') && n !== CACHE_NAME).map((n) => caches.delete(n))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    if (request.method !== 'GET') return;
    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    if (request.mode === 'navigate' && url.pathname.startsWith(PREFIXO_PORTAL)) {
        event.respondWith(paginaDoPortal(request));
        return;
    }
    if (url.pathname.startsWith(PREFIXO_BUILD)) {
        event.respondWith(arquivoDoBuild(request));
    }
});
