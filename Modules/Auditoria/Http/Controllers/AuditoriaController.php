<?php

namespace Modules\Auditoria\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Modules\Auditoria\Http\Requests\RevertActivityRequest;
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
        $filters = $this->entries->normalizeFilters($request->all());

        // Wave 18 D6.a: Inertia::defer DEFAULT em prop cara — `activities` é
        // `LengthAwarePaginator` (queries paginadas em activity_log com índices
        // subject_type/event/causer_kind, mas lookup full-text properties JSON
        // pode custar 100-400ms). Frontend wrap em `<Deferred data="activities">`.
        // `filters` é state UI (≤1ms) — eager OK.
        return Inertia::render('Auditoria/Index', [
            'activities' => Inertia::defer(fn () => $this->entries->list($businessId, $filters)),
            'filters'    => $filters,
        ]);
    }

    public function show(Request $request, int $activityId)
    {
        $businessId = (int) $request->session()->get('user.business_id');

        // Wave 18 D6.a: Inertia::defer em `activity` — find single row pode
        // hidratar properties JSON pesado (diff old/attributes em entidades
        // grandes tipo Transaction/Contact). Deferred libera 1st paint do shell.
        return Inertia::render('Auditoria/Detail', [
            'activity' => Inertia::defer(fn () => $this->entries->find($businessId, $activityId)),
        ]);
    }

    public function revert(RevertActivityRequest $request, int $activityId)
    {
        // FormRequest valida revert_reason >= 10 chars (Wave S Batch 2 D8.c).
        // Implementado em US-AUDIT-008 (RevertService). Placeholder MVP.
        return response()->json([
            'message' => 'RevertService ainda nao implementado — US-AUDIT-008 pendente.',
        ], 501);
    }
}
