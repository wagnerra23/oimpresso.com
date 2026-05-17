<?php

namespace Modules\Ponto\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Ponto\Entities\BancoHorasMovimento;
use Modules\Ponto\Entities\BancoHorasSaldo;
use Modules\Ponto\Services\BancoHorasService;

class BancoHorasController extends Controller
{
    protected $service;

    public function __construct(BancoHorasService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request): Response
    {
        $businessId = session('business.id') ?: $request->user()->business_id;

        // Wave 26 D6 Inertia::defer DEFAULT — paginate(30) + 4 aggregates (sum/count)
        // viram closures lazy (RUNBOOK-inertia-defer-pattern.md).
        return Inertia::render('Ponto/BancoHoras/Index', [
            'saldos' => Inertia::defer(fn () => $this->buildSaldosPagina($businessId)),
            'totais' => Inertia::defer(fn () => $this->buildTotaisSaldos($businessId)),
        ]);
    }

    /**
     * Paginação 30 saldos (orderByDesc saldo) — eager `colaborador.user`. Wave 26 extraído.
     */
    private function buildSaldosPagina(int $businessId)
    {
        $paginated = BancoHorasSaldo::where('business_id', $businessId)
            ->with('colaborador.user:id,first_name,last_name')
            ->orderByDesc('saldo_minutos')
            ->paginate(30)
            ->withQueryString();

        $paginated->getCollection()->transform(fn ($s) => [
            'colaborador_id' => $s->colaborador_config_id,
            'matricula'      => optional($s->colaborador)->matricula,
            'nome'           => trim(
                optional(optional($s->colaborador)->user)->first_name . ' ' .
                optional(optional($s->colaborador)->user)->last_name
            ) ?: '—',
            'saldo_minutos'  => (int) $s->saldo_minutos,
            'atualizado_em'  => optional($s->updated_at)->diffForHumans(),
        ]);

        return $paginated;
    }

    /**
     * Totais credito/debito + contagem colaboradores (4 aggregates). Wave 26 extraído.
     *
     * @return array<string,int>
     */
    private function buildTotaisSaldos(int $businessId): array
    {
        return [
            'credito_total' => (int) BancoHorasSaldo::where('business_id', $businessId)
                ->where('saldo_minutos', '>', 0)->sum('saldo_minutos'),
            'debito_total' => (int) BancoHorasSaldo::where('business_id', $businessId)
                ->where('saldo_minutos', '<', 0)->sum('saldo_minutos'),
            'colaboradores_credito' => BancoHorasSaldo::where('business_id', $businessId)
                ->where('saldo_minutos', '>', 0)->count(),
            'colaboradores_debito' => BancoHorasSaldo::where('business_id', $businessId)
                ->where('saldo_minutos', '<', 0)->count(),
        ];
    }

    public function show(Request $request, int $colaboradorId): Response
    {
        $saldo = BancoHorasSaldo::where('colaborador_config_id', $colaboradorId)
            ->with('colaborador.user:id,first_name,last_name')
            ->firstOrFail();

        // Wave 26 D6 Inertia::defer — paginate(50) movimentos lazy. Saldo header eager
        // (já materializado pra findOrFail validar acesso tenant).
        return Inertia::render('Ponto/BancoHoras/Show', [
            'saldo' => [
                'colaborador_id' => $saldo->colaborador_config_id,
                'matricula'      => optional($saldo->colaborador)->matricula,
                'nome'           => trim(
                    optional(optional($saldo->colaborador)->user)->first_name . ' ' .
                    optional(optional($saldo->colaborador)->user)->last_name
                ) ?: '—',
                'saldo_minutos'  => (int) $saldo->saldo_minutos,
            ],
            'movimentos' => Inertia::defer(fn () => $this->buildMovimentosPagina($colaboradorId)),
        ]);
    }

    /**
     * Paginação 50 movimentos do ledger. Wave 26 extraído pra closure lazy.
     */
    private function buildMovimentosPagina(int $colaboradorId)
    {
        $paginated = BancoHorasMovimento::where('colaborador_config_id', $colaboradorId)
            ->orderByDesc('created_at')
            ->paginate(50)
            ->withQueryString();

        $paginated->getCollection()->transform(fn ($m) => [
            'id'             => $m->id,
            'minutos'        => (int) $m->minutos,
            'tipo'           => $m->tipo,
            'data_referencia'=> optional($m->data_referencia)->format('Y-m-d'),
            'observacao'     => $m->observacao,
            'created_at'     => optional($m->created_at)->format('Y-m-d H:i'),
            'created_at_human' => optional($m->created_at)->diffForHumans(),
        ]);

        return $paginated;
    }

    public function ajustarManual(Request $request, $colaboradorId)
    {
        $request->validate([
            'minutos' => 'required|integer',
            'observacao' => 'required|string|max:500',
        ]);

        $this->service->ajustarManual(
            $colaboradorId,
            $request->input('minutos'),
            $request->input('observacao'),
            auth()->id()
        );

        return back()->with('success', 'Ajuste manual registrado no ledger.');
    }
}
