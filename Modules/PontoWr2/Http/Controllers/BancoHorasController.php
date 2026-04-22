<?php

namespace Modules\PontoWr2\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\PontoWr2\Entities\BancoHorasMovimento;
use Modules\PontoWr2\Entities\BancoHorasSaldo;
use Modules\PontoWr2\Services\BancoHorasService;

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

        $totais = [
            'credito_total' => (int) BancoHorasSaldo::where('business_id', $businessId)
                ->where('saldo_minutos', '>', 0)->sum('saldo_minutos'),
            'debito_total' => (int) BancoHorasSaldo::where('business_id', $businessId)
                ->where('saldo_minutos', '<', 0)->sum('saldo_minutos'),
            'colaboradores_credito' => BancoHorasSaldo::where('business_id', $businessId)
                ->where('saldo_minutos', '>', 0)->count(),
            'colaboradores_debito' => BancoHorasSaldo::where('business_id', $businessId)
                ->where('saldo_minutos', '<', 0)->count(),
        ];

        return Inertia::render('Ponto/BancoHoras/Index', [
            'saldos' => $paginated,
            'totais' => $totais,
        ]);
    }

    public function show(Request $request, int $colaboradorId): Response
    {
        $saldo = BancoHorasSaldo::where('colaborador_config_id', $colaboradorId)
            ->with('colaborador.user:id,first_name,last_name')
            ->firstOrFail();

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
            'movimentos' => $paginated,
        ]);
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
