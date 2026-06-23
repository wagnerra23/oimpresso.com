<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Forca o ESTADO ISOLADO de uma tela pro gate L2 de regressao visual (snapshots de estado).
 *
 * Le a flag `_visreg_state` que a rota env-guarded /_visreg-state/{tela}/{estado}
 * (routes/web.php) gravou na sessao, e aplica o lever DETERMINISTICO de cada estado SEM
 * tocar nenhum controller nem nenhuma Page .tsx (mesmo principio nao-invasivo do gate L3
 * Tier0RenderIsolation — o estado vem do contexto de request/auth, nao de edicao de codigo):
 *
 *   - `dark`    → seta `auth()->user()->ui_theme = 'dark'` EM MEMORIA (sem persistir). O
 *                 blade root (resources/views/layouts/inertia.blade.php) le exatamente
 *                 `auth()->user()->ui_theme` pra decidir `<html class="dark">`, e o
 *                 HandleInertiaRequests compartilha `auth.user.ui_theme` pro useTheme do
 *                 React — os dois leem a MESMA instancia de User cacheada pelo guard de
 *                 sessao nesta request, entao o override em memoria pinta a tela inteira
 *                 em dark. Snapshot = A/B real do `default` (mesmo dado, so o tema muda).
 *
 *   - `loading` → CONGELA o skeleton do Inertia::defer. O cliente Inertia, ao montar um
 *                 `<Deferred>`, dispara um partial-reload SO com as props deferred no header
 *                 `X-Inertia-Partial-Data` (o "only"). O PropsResolver (vendor inertiajs/
 *                 inertia-laravel/src/PropsResolver.php:138/311) so resolve uma prop deferred
 *                 se o path dela casar esse "only". Reescrevendo o header pra um SENTINELA
 *                 que nao casa NENHUM path real, o partial-reload volta 200 SEM as props
 *                 deferred → o `<Deferred>` fica no `fallback` (skeleton) pra sempre, sem
 *                 loop (a request conclui → networkidle assenta → screenshot estavel).
 *                 NÃO mexe no header X-Inertia-Partial-Component → `isPartial` segue true
 *                 (sem isso viraria full-visit e os deferred seriam excluidos de novo,
 *                 disparando re-fetch em loop).
 *
 *   - `error`   → NÃO precisa de middleware: a rota usa redirect()->with('error', ...) e o
 *                 HandleInertiaRequests expoe `flash.error` → app.tsx mostra toast.error
 *                 (duration 8000) no load inicial. Aqui so registramos a flag (informativa).
 *
 *   - `empty`/`default`/`long-data` → sem lever de middleware: o estado vem do TENANT logado
 *                 (empty = admin do biz=98 VisregEmptyTenantSeeder; default/long-data = biz=1).
 *
 * ENV-GUARDED: no-op em producao (a rota /_visreg-state tambem nao existe la — isProduction
 * guard em routes/web.php). Early-return barato (le sessao 1×) no caminho sem flag (99% das
 * requests). Registrado no grupo `web` (app/Http/Kernel.php) DEPOIS do StartSession (precisa
 * sessao ativa) — a fase "before" roda antes do controller, entao o override de header/tema
 * ja esta no lugar quando o Inertia::render resolve as props.
 *
 * @see routes/web.php (rota /_visreg-state que grava a flag)
 * @see tests/Browser/CoreScreens/IsolatedStatesBaselineTest.php (consumidor)
 * @see vendor/inertiajs/inertia-laravel/src/PropsResolver.php (mecanica do partial/defer)
 * @see resources/views/layouts/inertia.blade.php (le auth()->user()->ui_theme pro dark)
 */
class VisregStateMiddleware
{
    /** Chave de sessao gravada pela rota /_visreg-state. */
    public const SESSION_KEY = '_visreg_state';

    /**
     * Sentinela do "only" no estado `loading`: nao casa nenhum path de prop real, entao o
     * PropsResolver exclui TODAS as props deferred do partial-reload → skeleton congela.
     */
    private const LOADING_FREEZE_SENTINEL = '__visreg_loading_freeze__';

    public function handle(Request $request, Closure $next): Response
    {
        // Defesa-em-profundidade: NUNCA atua em producao (a rota tambem nao existe la).
        if (app()->isProduction()) {
            return $next($request);
        }

        $state = $request->session()->get(self::SESSION_KEY);
        if (! is_string($state) || $state === '') {
            return $next($request);
        }

        if ($state === 'dark') {
            // Override em memoria (sem ->save()): blade + share leem a mesma instancia.
            $user = $request->user();
            if ($user !== null) {
                $user->ui_theme = 'dark';
            }
        }

        if ($state === 'loading'
            && $request->headers->has('X-Inertia-Partial-Component')) {
            // Congela o skeleton: o "only" passa a nao casar nenhuma prop deferred.
            $request->headers->set('X-Inertia-Partial-Data', self::LOADING_FREEZE_SENTINEL);
            // Limpa o "except" (defensivo: cliente nao manda pra defer, mas garante que o
            // sentinela seja a unica regra de filtro do partial).
            $request->headers->remove('X-Inertia-Partial-Except');
        }

        return $next($request);
    }
}
