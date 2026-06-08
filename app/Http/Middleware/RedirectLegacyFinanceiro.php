<?php

namespace App\Http\Middleware;

use App\Utils\ModuleUtil;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Redireciona rotas GET legacy de Expense/Account (core UltimatePOS) pras
 * telas canônicas do Modules/Financeiro. Wagner 2026-05-20 Fase 2 deprecação
 * legacy (Fase 1 escondeu do sidebar; Fase 2 captura bookmarks/deeplinks).
 *
 * Comportamento:
 *   1. Só intercepta GET (POST/PUT/DELETE seguem pro core — preserva
 *      formulários e API que o Modules/Financeiro ainda não absorveu).
 *   2. Early return rápido se path não está na lista (99% dos requests).
 *   3. Só redireciona se Financeiro habilitado pro user (subscription package
 *      + permission `financeiro.access`). Businesses sem Financeiro continuam
 *      usando legacy normalmente — regressão zero.
 *
 * Pattern de gate replicado de Modules/Financeiro/Http/Controllers/DataController
 * (linhas 67-77, 90) pra consistência. Mesma checagem que decide se mostra
 * o menu Financeiro.
 *
 * Ver mapeamento legacy → canon no array REDIRECTS.
 */
class RedirectLegacyFinanceiro
{
    /**
     * Path legacy (sem slash inicial) => path canônico Financeiro.
     *
     * Notes:
     *   - balance-sheet + trial-balance fundem em /financeiro/dre até Fase 4
     *     (que adiciona tabs Balanço/Balancete).
     *   - payment-account-report aponta pra contas-bancarias (não /extrato/{id}
     *     porque exige ID; usuário escolhe a conta no índice).
     *   - account/{id}, account/fund-transfer, account/deposit, account/close
     *     etc NÃO são redirecionados: mantêm fluxos legacy de mutação.
     */
    private const REDIRECTS = [
        'expenses'                       => '/financeiro/unificado',
        'expense-categories'             => '/financeiro/categorias',
        'account'                        => '/financeiro/contas-bancarias',
        'account/cash-flow'              => '/financeiro/fluxo',
        'account/balance-sheet'          => '/financeiro/dre',
        'account/trial-balance'          => '/financeiro/dre',
        'account/payment-account-report' => '/financeiro/contas-bancarias',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        // (1) Early return: só GET. POST/PUT/DELETE seguem pro core legacy.
        if (! $request->isMethod('GET')) {
            return $next($request);
        }

        // (2) Early return: só paths mapeados.
        $path = trim($request->path(), '/');
        if (! array_key_exists($path, self::REDIRECTS)) {
            return $next($request);
        }

        // (3) Financeiro habilitado pro user? Se não, segue legacy.
        if (! $this->isFinanceiroEnabled()) {
            return $next($request);
        }

        return redirect(self::REDIRECTS[$path], 301);
    }

    /**
     * Mesmo gate usado em Modules/Financeiro/.../DataController::modifyAdminMenu()
     * pra coerência total: se o user vê o menu Financeiro, recebe o redirect;
     * caso contrário continua no legacy.
     */
    private function isFinanceiroEnabled(): bool
    {
        if (! auth()->check()) {
            return false;
        }

        $moduleUtil = new ModuleUtil();

        if (auth()->user()->can('superadmin')) {
            return $moduleUtil->isModuleInstalled('Financeiro');
        }

        return (bool) $moduleUtil->hasThePermissionInSubscription(
            session('user.business_id'),
            'financeiro_module',
            'superadmin_package'
        ) && auth()->user()->can('financeiro.access');
    }
}
