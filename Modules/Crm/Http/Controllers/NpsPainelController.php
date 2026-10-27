<?php

declare(strict_types=1);

namespace Modules\Crm\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Crm\Services\Nps\PesquisaNps;

/**
 * Painel NPS (`/crm/nps`) — US-CRM-097. Permissão `crm.nps.ver` (ninguém tem por padrão).
 * Só pesquisas da empresa da sessão (Tier 0, ADR 0093); resumo e respostas deferidos.
 */
class NpsPainelController extends Controller
{
    public const PERIODOS = [7, 30, 90];

    public function __construct(private readonly PesquisaNps $nps) {}

    public function index(Request $request): Response
    {
        abort_unless(auth()->user()?->can('crm.nps.ver'), 403);
        $businessId = (int) $request->session()->get('user.business_id');
        $dias = in_array((int) $request->query('dias'), self::PERIODOS, true) ? (int) $request->query('dias') : 30;
        $desde = Carbon::now()->subDays($dias);

        return Inertia::render('Crm/Nps/Index', [
            'dias' => $dias,
            'periodos' => self::PERIODOS,
            'resumo' => Inertia::defer(fn () => $this->nps->resumo($businessId, $desde)),
            'respostas' => Inertia::defer(fn () => $this->respostas($businessId, $desde)),
        ]);
    }

    /** Até 100 respostas do período; detratores primeiro, depois as mais recentes. */
    private function respostas(int $businessId, Carbon $desde): array
    {
        return DB::table('crm_nps_pesquisas as p')
            ->leftJoin('contacts as c', fn ($j) => $j->on('c.id', '=', 'p.contact_id')->where('c.business_id', $businessId))
            ->leftJoin('transactions as t', fn ($j) => $j->on('t.id', '=', 'p.origem_id')->where('p.origem_tipo', 'venda')->where('t.business_id', $businessId))
            ->leftJoin('repair_job_sheets as j', fn ($j) => $j->on('j.id', '=', 'p.origem_id')->where('p.origem_tipo', 'os')->where('j.business_id', $businessId))
            ->where('p.business_id', $businessId)
            ->whereNotNull('p.nota')
            ->where('p.respondida_em', '>=', $desde)
            ->orderBy('p.nota')->orderByDesc('p.respondida_em')
            ->limit(100)
            ->get(['p.id', 'p.nota', 'p.comentario', 'p.respondida_em', 'p.origem_tipo', 'c.name as cliente', 'c.supplier_business_name as empresa', 't.invoice_no', 'j.job_sheet_no'])
            ->map(fn ($r) => [
                'id' => (int) $r->id,
                'nota' => (int) $r->nota,
                'cliente' => trim((string) ($r->empresa ?: $r->cliente)) ?: null,
                'origem' => $r->origem_tipo === 'os' ? 'OS '.($r->job_sheet_no ?? '') : 'Venda '.($r->invoice_no ?? ''),
                'comentario' => $r->comentario,
                'respondida_em' => Carbon::parse($r->respondida_em)->format('d/m H:i'),
            ])
            ->values()
            ->all();
    }
}
