<?php

namespace Modules\Financeiro\Http\Controllers\Advisor;

use App\Business;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Modules\Financeiro\Models\AdvisorBusinessAccess;

/**
 * AdvisorPortalController — Dashboard /advisor (Onda 31 #57 US-FIN-037).
 *
 * Visão multi-business pro contador — cards de cada cliente acessível via
 * grant ATIVO. Clicar num card abre `/financeiro/relatorios?advisor_view=1&business_id=X`
 * (middleware AdvisorViewScope valida + readonly enforce).
 */
class AdvisorPortalController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:web-advisor');
    }

    /**
     * GET /advisor
     */
    public function index(): InertiaResponse
    {
        /** @var \Modules\Financeiro\Models\Advisor $advisor */
        $advisor = Auth::guard('web-advisor')->user();

        // Lista de businesses acessíveis via grant ativo + scope LGPD validado.
        $accesses = AdvisorBusinessAccess::query()
            ->where('advisor_id', $advisor->id)
            ->whereNull('revoked_at')
            ->whereNull('deleted_at')
            ->get();

        $businessIds = $accesses->pluck('business_id')->unique()->values()->all();

        $businesses = Business::query()
            ->whereIn('id', $businessIds)
            ->get(['id', 'name'])
            ->keyBy('id');

        $clientes = $accesses->map(function (AdvisorBusinessAccess $a) use ($businesses) {
            $biz = $businesses->get($a->business_id);
            return [
                'access_id' => $a->id,
                'business_id' => $a->business_id,
                'business_name' => $biz?->name ?? 'Cliente removido',
                'granted_at_label' => $a->granted_at?->locale('pt_BR')->isoFormat('DD/MM/YYYY'),
                'can_view_unificado' => $a->canViewUnificado(),
                'can_view_reports' => $a->canViewReports(),
                'has_consent' => $a->hasConsent(),
                // URLs read-only (middleware AdvisorViewScope força readonly).
                'url_unificado' => "/financeiro/unificado?advisor_view=1&business_id={$a->business_id}",
                'url_relatorios' => "/financeiro/relatorios?advisor_view=1&business_id={$a->business_id}",
            ];
        });

        return Inertia::render('Financeiro/Advisor/Dashboard', [
            'advisor' => [
                'id' => $advisor->id,
                'nome' => $advisor->nome,
                'email' => $advisor->email,
                'referral_code' => $advisor->referral_code,
            ],
            'clientes' => $clientes,
            'total_clientes' => $clientes->count(),
        ]);
    }
}
