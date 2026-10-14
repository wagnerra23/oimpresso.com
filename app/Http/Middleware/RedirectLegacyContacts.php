<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Redireciona rotas GET legacy de /contacts (Customer view) pras URLs canônicas
 * /cliente. Wagner 2026-05-21 Fase 2 deprecação legacy Cliente — espelha
 * RedirectLegacyFinanceiro (PR #1283).
 *
 * Escopo original (#1289, 2026-05): INDEX e SHOW, dormente no Kernel até o canário do [W].
 *
 * 2026-10-13 (thread cliente/04, decisões D1/D3 [W]): ligado no Kernel para aposentar o
 * dual-render da LISTA. O ramo do SHOW foi retirado até o [W] decidir o cutover dele.
 *
 * Comportamento:
 *   1. Só intercepta GET (POST/PUT/DELETE seguem pro core).
 *   2. Early return rápido se path não é a lista (99% requests).
 *   3. Lista: /contacts?type=<um dos 6 tipos do /cliente> → 302 /cliente?<a mesma query>,
 *      só para navegação de página (AJAX legado e partial reload do Inertia passam direto)
 *      sem flag: a casca Blade da lista foi apagada (thread cliente/04, [W] 2026-10-14) e
 *      `/contacts` só serve React, então não há o que escolher.
 *
 * Ver:
 *   - app/Http/Controllers/ContactController.php@index (só React)
 */
class RedirectLegacyContacts
{
    /**
     * Tipos que o `/cliente` aceita (whitelist da rota em routes/web.php, ADR 0188/0246).
     * Thread cliente/04 (D1 + D3 [W]): a lista de fornecedores também vai para
     * `/cliente?type=supplier` — mesmo Cliente/Index.
     */
    private const TIPOS_INDEX = ['customer', 'supplier', 'employee', 'representative', 'other', 'all'];

    public function handle(Request $request, Closure $next): Response
    {
        // (1) Só GET.
        if (! $request->isMethod('GET')) {
            return $next($request);
        }

        $path = trim($request->path(), '/');

        // (2) INDEX: /contacts?type=<tipo> → /cliente?<a mesma query>. Thread cliente/04.
        //   - Só navegação de página: AJAX legado (X-Requested-With sem X-Inertia, DataTables
        //     JSON) e partial reload do Inertia seguem servidos aqui — o `/cliente` chama o mesmo
        //     ContactController::index, mas quem já está na página não troca de URL no meio.
        //   - 302, não 301: o browser guarda o 301 sem prazo, e um 301 não se desfaz se a lista
        //     precisar voltar a morar em /contacts.
        if ($path === 'contacts' && in_array($request->query('type'), self::TIPOS_INDEX, true)) {
            $legacyAjax = $request->ajax() && ! $request->hasHeader('X-Inertia');
            $partialReload = $request->hasHeader('X-Inertia-Partial-Data');
            if (! $legacyAjax && ! $partialReload) {
                return redirect('/cliente?'.$request->getQueryString(), 302);
            }

            return $next($request);
        }

        // (3) SHOW (/contacts/{id} → /cliente/{id}) foi RETIRADO por decisão [W] 2026-10-13: a
        //     thread cliente/04 aposenta só o dual-render da LISTA, e o cutover do Show é outra
        //     decisão. A versão com o Show (flag cliente_show + lookup customer/both escopado por
        //     business_id) está no histórico deste arquivo antes do commit da thread 04.

        return $next($request);
    }
}
