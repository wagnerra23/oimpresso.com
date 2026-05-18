<?php

declare(strict_types=1);

namespace Modules\Financeiro\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\ShellMenuBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoint JSON pro Mock Cowork — sidebar REAL respeitando 3 camadas
 * habilitação (subscription_package + business.enabled_modules + Spatie
 * permissions) via reuse do ShellMenuBuilder + AdminSidebarMenu middleware.
 *
 * Wagner 2026-05-18: "era para estar filtrando pela empresa selecionada?".
 * Sim — bridge `_oimpresso-bridge-sidebar.js` agora fetcha esse endpoint
 * em vez de hardcoded. UNIVERSAL pra todos os businesses — sem hardcode
 * biz=4 (regra Tier 0 IRREVOGÁVEL ADR 0093).
 *
 * Tier 0 garantido por CONSTRUÇÃO:
 *   - Reusa mesma cadeia do AppShellV2 oimpresso real
 *   - 3 camadas implícitas via DataController::modifyAdminMenu de cada módulo
 *   - business_id session-derived (sem confusão tenant)
 *
 * PEGADINHA CRÍTICA (catalogada em skill sidebar-menu-arch):
 *
 *   AdminSidebarMenu middleware pula `Menu::create()` em request AJAX que
 *   NÃO tem header `X-Inertia: true`:
 *
 *     if ($request->ajax() && ! $request->header('X-Inertia')) {
 *         return $next($request);  // ← MENU FICA VAZIO
 *     }
 *
 *   Bridge JS precisa enviar `X-Inertia: true` no fetch pra middleware
 *   popular o Menu antes do controller chamar ShellMenuBuilder.
 *
 * Rota: GET /financeiro/cowork-sidebar-data com middleware stack canon
 * Financeiro `['web', 'auth', 'language', 'timezone', 'AdminSidebarMenu']`.
 */
class CoworkSidebarController extends Controller
{
    public function __construct(protected ShellMenuBuilder $builder)
    {
        $this->middleware('auth');
    }

    /**
     * GET /financeiro/cowork-sidebar-data
     *
     * Pré-requisito: bridge JS DEVE enviar `X-Inertia: true` no fetch (senão
     * middleware AdminSidebarMenu pula criação do Menu e retorno vem vazio).
     *
     * @return JsonResponse{
     *   business: array{ id: int, name: string },
     *   user: array{ name: string, role: ?string },
     *   menu: array<int, array<string, mixed>>,
     *   active_module: string
     * }
     */
    public function data(Request $request): JsonResponse
    {
        $user = $request->user();
        $businessId = (int) session('user.business_id', 0);
        $businessName = (string) session('business.name', '');

        // ShellMenuBuilder reusa Menu populado pelo middleware AdminSidebarMenu
        // que já rodou no stack da rota (canon). Cada DataController::modifyAdminMenu
        // de módulo checou 3 camadas (subscription + enabled_modules + Spatie) ANTES
        // de adicionar items ao Menu. Logo $menu já vem filtrado por business + user.
        $menu = $this->builder->build($request);

        $userName = '';
        $userRole = null;
        if ($user !== null) {
            $first = (string) ($user->first_name ?? '');
            $last = (string) ($user->last_name ?? '');
            $userName = trim($first . ' ' . $last);
            if ($userName === '') {
                $userName = (string) ($user->username ?? $user->email ?? '—');
            }
            // Spatie role (pode ser sufixada com #biz_id: "Admin#4")
            $userRole = method_exists($user, 'getRoleNames')
                ? $user->getRoleNames()->first()
                : null;
        }

        return response()->json([
            'business' => [
                'id'   => $businessId,
                'name' => $businessName,
            ],
            'user' => [
                'name' => $userName,
                'role' => $userRole,
            ],
            'menu'          => $menu,
            'active_module' => 'financeiro', // contexto do Mock Cowork Mode
            'item_count'    => count($menu),
        ], 200, [
            'Cache-Control' => 'private, max-age=60',
            'X-Mock-Source' => 'CoworkSidebarController::data',
        ]);
    }
}
