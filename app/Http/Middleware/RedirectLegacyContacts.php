<?php

namespace App\Http\Middleware;

use App\Contact;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Redireciona rotas GET legacy de /contacts (Customer view) pras URLs canônicas
 * /cliente. Wagner 2026-05-21 Fase 2 deprecação legacy Cliente — espelha
 * RedirectLegacyFinanceiro (PR #1283).
 *
 * Escopo canary: APENAS INDEX e SHOW (read-only). Create/Edit/Import/Ledger/Map
 * seguem legacy até G-CLI-02 (autosave) fechar.
 *
 * 2026-10-13 (thread cliente/04, decisões D1/D3 [W]): ligado no Kernel para aposentar o
 * dual-render da LISTA. Index cobre os 6 tipos do /cliente e preserva a query; o SHOW fica
 * desligado (SHOW_ATIVO) até o [W] decidir o cutover dele.
 *
 * Comportamento:
 *   1. Só intercepta GET (POST/PUT/DELETE seguem pro core).
 *   2. Early return rápido se path não está nos casos mapeados (99% requests).
 *   3. Só redireciona se flag mwart.cliente_* ON pro business atual.
 *      Businesses fora do canary continuam usando legacy normalmente — regressão zero.
 *   4. SHOW: lookup leve Contact::where('id')->whereIn('type', ['customer','both'])
 *      pra evitar redirecionar supplier (que NÃO tem tela canon /cliente).
 *
 * Ver:
 *   - config/mwart.php (flags cliente_index + cliente_show)
 *   - app/Http/Controllers/ContactController.php@index linhas 132-142 (dual render JÁ pronto)
 *   - ADR 0107 (gate F1.5 visual) — Wagner aprova screenshot ANTES de canary biz=1
 */
class RedirectLegacyContacts
{
    /**
     * Tipos que o `/cliente` aceita (whitelist da rota em routes/web.php, ADR 0188/0246).
     * Thread cliente/04 (D1 + D3 [W]): a lista de fornecedores também vai para
     * `/cliente?type=supplier` — mesmo Cliente/Index.
     */
    private const TIPOS_INDEX = ['customer', 'supplier', 'employee', 'representative', 'other', 'all'];

    /**
     * Redirect do SHOW (/contacts/{id} → /cliente/{id}) DESLIGADO por decisão [W] 2026-10-13:
     * a thread cliente/04 aposenta só o dual-render da LISTA. O cutover do Show é outra decisão.
     * O código fica para quando ela vier — virar `true` aqui é o ato.
     */
    private const SHOW_ATIVO = false;

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
        //   - 302, não 301: o browser guarda o 301 sem prazo, e desligar a flag não desfaria o
        //     redirect pra quem já passou por aqui.
        if ($path === 'contacts' && in_array($request->query('type'), self::TIPOS_INDEX, true)) {
            $legacyAjax = $request->ajax() && ! $request->hasHeader('X-Inertia');
            $partialReload = $request->hasHeader('X-Inertia-Partial-Data');
            if (! $legacyAjax && ! $partialReload && $this->isClienteIndexEnabled()) {
                return redirect('/cliente?'.$request->getQueryString(), 302);
            }

            return $next($request);
        }

        // (3) SHOW: /contacts/{id} → /cliente/{id} (apenas se contact é customer/both)
        if (preg_match('#^contacts/(\d+)$#', $path, $m)) {
            $id = (int) $m[1];
            if (self::SHOW_ATIVO && $this->isClienteShowEnabled() && $this->isContactCustomer($id)) {
                return redirect("/cliente/{$id}", 301);
            }

            return $next($request);
        }

        return $next($request);
    }

    private function isClienteIndexEnabled(): bool
    {
        return $this->flagOnForCurrentBusiness('cliente_index');
    }

    private function isClienteShowEnabled(): bool
    {
        return $this->flagOnForCurrentBusiness('cliente_show');
    }

    private function flagOnForCurrentBusiness(string $flag): bool
    {
        if (! auth()->check()) {
            return false;
        }

        if (! config("mwart.{$flag}.enabled")) {
            return false;
        }

        $allowedBizIds = config("mwart.{$flag}.business_ids", []);
        if (empty($allowedBizIds)) {
            return true;
        }

        $businessId = (int) session('user.business_id');

        return in_array($businessId, $allowedBizIds, true);
    }

    /**
     * Confirma que o contact é customer (ou both), scoped por business_id
     * (multi-tenant Tier 0 — ADR 0093). Supplier NUNCA redireciona.
     */
    private function isContactCustomer(int $id): bool
    {
        $businessId = (int) session('user.business_id');
        if ($businessId <= 0) {
            return false;
        }

        return Contact::where('business_id', $businessId)
            ->where('id', $id)
            ->whereIn('type', ['customer', 'both'])
            ->exists();
    }
}
