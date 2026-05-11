<?php

declare(strict_types=1);

namespace Modules\Jana\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Jana\Services\BriefDiarioService;

/**
 * JANA Pro admin (US-COPI-201, ADR 0140) — endpoint preview do Brief
 * Diário pra Wagner validar o snapshot antes de configurar Job 8h.
 *
 * Sprint A foundation. Sprint B adiciona pricing page + Asaas subscription
 * + Job + WhatsApp delivery + email HTML. Este controller é só preview.
 *
 * Permissão: copiloto.superadmin (Wagner inicial). Sprint B muda pra
 * jana_pro.preview quando granular permission entrar.
 *
 * @see memory/decisions/0140-jana-pro-produto-comercial-saas.md
 * @see memory/requisitos/Copiloto/JANA-PRO-PRODUCT-PLAN.md
 */
class JanaProController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * GET /copiloto/admin/jana-pro/preview?business_id=N
     *
     * Roda BriefDiarioService::snapshot() pro businessId fornecido (default
     * = session) e retorna JSON. Útil pra:
     *  - Wagner ver dados reais antes do brief diário rodar
     *  - QA das 5 sources (graceful degradation, Tier 0 isolation)
     *  - Tweak prompt do BriefDiarioAgent (Sprint A US-COPI-202) com payload real
     */
    public function preview(Request $request): JsonResponse
    {
        $businessId = (int) ($request->get('business_id') ?? session('user.business_id', 1));

        // Tier 0 defense (ADR 0093): superadmin pode passar qualquer biz,
        // user comum só vê o próprio business. Middleware can:copiloto.superadmin
        // já garante mas defense-in-depth não custa.
        $isSuper = $request->user()?->user_type === 'superadmin'
            || $request->user()?->user_type === 'user_oimpresso';
        $sessionBiz = (int) session('user.business_id', 0);
        if (! $isSuper && $businessId !== $sessionBiz) {
            return response()->json([
                'ok' => false,
                'error' => 'tenant_violation',
                'message' => 'Sem permissão pra ver brief de outro business.',
            ], 403);
        }

        $service = new BriefDiarioService($businessId);
        $snapshot = $service->snapshot();

        return response()->json([
            'ok' => true,
            'snapshot' => $snapshot,
        ], 200);
    }
}
