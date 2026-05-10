<?php

namespace Modules\Auditoria\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Spatie\Activitylog\Models\Activity;

/**
 * AuditoriaController — UI rica /auditoria + revert.
 *
 * Sprint F3 implementa Pages Inertia + RevertService (US-AUDIT-008..010).
 * Este scaffold (US-AUDIT-007) entrega placeholder funcional pra
 * `php artisan module:enable Auditoria` + listagem basica multi-tenant Tier 0.
 *
 * Refs: ADR 0127 (Modules/Auditoria UI + undo)
 */
class AuditoriaController extends Controller
{
    public function index(Request $request)
    {
        $businessId = (int) $request->session()->get('user.business_id');

        // Multi-tenant Tier 0 ([ADR 0093]) — toda query scoped por business_id.
        $query = Activity::query()
            ->where('activity_log.business_id', $businessId)
            ->orderByDesc('id');

        // Filtros basicos MVP
        if ($causerKind = $request->input('causer_kind')) {
            $query->where('causer_kind', $causerKind);
        }
        if ($subjectType = $request->input('subject_type')) {
            $query->where('subject_type', $subjectType);
        }
        if ($event = $request->input('event')) {
            $query->where('event', $event);
        }

        $activities = $query->paginate(config('auditoria.page_size', 50));

        return Inertia::render('Auditoria/Index', [
            'activities' => $activities,
            'filters'    => $request->only(['causer_kind', 'subject_type', 'event']),
        ]);
    }

    public function show(Request $request, int $activityId)
    {
        $businessId = (int) $request->session()->get('user.business_id');

        $activity = Activity::query()
            ->where('activity_log.business_id', $businessId)
            ->where('id', $activityId)
            ->firstOrFail();

        return Inertia::render('Auditoria/Detail', [
            'activity' => $activity,
        ]);
    }

    public function revert(Request $request, int $activityId)
    {
        // Implementado em US-AUDIT-008 (RevertService). Placeholder MVP.
        return response()->json([
            'message' => 'RevertService ainda nao implementado — US-AUDIT-008 pendente.',
        ], 501);
    }
}
