<?php

namespace Modules\ADS\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Modules\ADS\Services\ContextForTaskService;

/**
 * Endpoint que Claude Code chama UMA VEZ no início de sessão complexa.
 * Retorna pacote consolidado de contexto (ADRs + skills + scope + meta + recentes).
 *
 * Cache 5min (próxima chamada idêntica reusa).
 */
class ContextController extends Controller
{
    /**
     * POST /api/ads/context-for-task
     */
    public function forTask(Request $request, ContextForTaskService $service): JsonResponse
    {
        $data = $request->validate([
            'user_id'        => 'sometimes|integer',
            'intent'         => 'required|string|max:1000',
            'domain'         => 'sometimes|nullable|string|max:50',
            'files_planned'  => 'sometimes|array',
            'files_planned.*' => 'string|max:500',
            'event_type'     => 'sometimes|nullable|string|max:80',
        ]);

        // Tier 0 (ADR 0093): resolve o business_id do tenant pelo lado SERVIDOR (nunca
        // do corpo da request — caller não escolhe tenant). Prefere o user autenticado;
        // cai pra sessão; fail-safe = 1 (plataforma). Esta API é `ads.api` (token), então
        // pode ser stateless — por isso o guard hasSession().
        $businessId = 1;
        if ($request->user() && ! empty($request->user()->business_id)) {
            $businessId = (int) $request->user()->business_id;
        } elseif ($request->hasSession()) {
            $businessId = (int) $request->session()->get('user.business_id', 1);
        }
        $data['business_id'] = $businessId;

        $context = $service->buildContext($data);

        return response()->json($context);
    }
}
