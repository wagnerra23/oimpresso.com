<?php

namespace Modules\Auditoria\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Modules\Auditoria\Services\RevertService;
use Spatie\Activitylog\Models\Activity;

/**
 * AuditoriaController — UI rica /auditoria + revert.
 *
 * US-AUDIT-007 entregou scaffold (placeholder revert).
 * US-AUDIT-010 (este patch) wire RevertService + permissions Spatie +
 * validation + redirect/flash apos sucesso.
 *
 * Refs: ADR 0127 (Modules/Auditoria UI + undo), ADR 0093 multi-tenant Tier 0.
 */
class AuditoriaController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('auditoria.view');

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
            $query->where('subject_type', 'like', '%'.$subjectType.'%');
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
        $this->authorize('auditoria.view');

        $businessId = (int) $request->session()->get('user.business_id');

        $activity = Activity::query()
            ->where('activity_log.business_id', $businessId)
            ->where('id', $activityId)
            ->firstOrFail();

        return Inertia::render('Auditoria/Detail', [
            'activity' => $activity,
        ]);
    }

    public function revert(Request $request, int $activityId, RevertService $service)
    {
        $businessId = (int) $request->session()->get('user.business_id');
        $user = auth()->user();

        $activity = Activity::query()
            ->where('activity_log.business_id', $businessId)
            ->where('id', $activityId)
            ->firstOrFail();

        $request->validate([
            'reason' => 'required|string|min:10|max:500',
        ]);

        // canRevert ja considera Tier 0 + whitelist UNREVERTIBLE + janelas
        // de permissao Spatie (own/any/unlimited).
        $check = $service->canRevert($activity, $user);
        if (! $check->allowed) {
            return back()->withErrors(['reason' => $check->reason]);
        }

        try {
            $revertEntry = $service->revert($activity, $user, $request->input('reason'));
        } catch (\DomainException|\InvalidArgumentException $e) {
            return back()->withErrors(['reason' => $e->getMessage()]);
        }

        return redirect()->route('auditoria.index')->with('success', 'Reversão aplicada (entry #'.$revertEntry->id.').');
    }
}
