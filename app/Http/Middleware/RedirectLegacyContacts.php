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
    public function handle(Request $request, Closure $next): Response
    {
        // (1) Só GET.
        if (! $request->isMethod('GET')) {
            return $next($request);
        }

        $path = trim($request->path(), '/');

        // (2) INDEX: /contacts?type=customer → /cliente
        if ($path === 'contacts' && $request->query('type') === 'customer') {
            if ($this->isClienteIndexEnabled()) {
                return redirect('/cliente', 301);
            }

            return $next($request);
        }

        // (3) SHOW: /contacts/{id} → /cliente/{id} (apenas se contact é customer/both)
        if (preg_match('#^contacts/(\d+)$#', $path, $m)) {
            $id = (int) $m[1];
            if ($this->isClienteShowEnabled() && $this->isContactCustomer($id)) {
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
