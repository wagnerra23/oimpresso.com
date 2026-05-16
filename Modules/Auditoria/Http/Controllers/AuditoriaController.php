<?php

namespace Modules\Auditoria\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Modules\Auditoria\Services\AuditEntryService;

/**
 * AuditoriaController — UI rica /auditoria + revert.
 *
 * Sprint F3 implementa Pages Inertia + RevertService (US-AUDIT-008..010).
 * Wave M (2026-05-16): listing/filter extraído pra AuditEntryService thin
 * (response shape PRESERVADO — Pages Inertia não quebram).
 *
 * Tier 0 IRREVOGÁVEL: queries scoped via Service::list/find ([ADR 0093]).
 * RevertService permanece intocado (compliance crítica).
 *
 * Refs: ADR 0127 (Modules/Auditoria UI + undo) · SPEC US-AUDIT-007/010
 */
class AuditoriaController extends Controller
{
    public function __construct(private AuditEntryService $entries) {}

    public function index(Request $request)
    {
        $businessId = (int) $request->session()->get('user.business_id');

        $activities = $this->entries->list(
            $businessId,
            $this->entries->normalizeFilters($request->all())
        );

        return Inertia::render('Auditoria/Index', [
            'activities' => $activities,
            'filters'    => $this->entries->normalizeFilters($request->all()),
        ]);
    }

    public function show(Request $request, int $activityId)
    {
        $businessId = (int) $request->session()->get('user.business_id');

        $activity = $this->entries->find($businessId, $activityId);

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
