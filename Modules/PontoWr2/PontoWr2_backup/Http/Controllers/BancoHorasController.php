<?php

namespace Modules\PontoWr2\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
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

    public function index(Request $request): View
    {
        $businessId = session('business.id') ?: $request->user()->business_id;

        $saldos = BancoHorasSaldo::where('business_id', $businessId)
            ->with('colaborador.user')
            ->orderByDesc('saldo_minutos')
            ->paginate(30);

        $totais = [
            'credito_total' => BancoHorasSaldo::where('business_id', $businessId)
                ->where('saldo_minutos', '>', 0)->sum('saldo_minutos'),
            'debito_total' => BancoHorasSaldo::where('business_id', $businessId)
                ->where('saldo_minutos', '<', 0)->sum('saldo_minutos'),
            'colaboradores_credito' => BancoHorasSaldo::where('business_id', $businessId)
                ->where('saldo_minutos', '>', 0)->count(),
            'colaboradores_debito' => BancoHorasSaldo::where('business_id', $businessId)
                ->where('saldo_minutos', '<', 0)->count(),
        ];

        return view('pontowr2::banco-horas.index', compact('saldos', 'totais'));
    }

    public function show(Request $request, int $colaboradorId): View
    {
        $saldo = BancoHorasSaldo::where('colaborador_config_id', $colaboradorId)
            ->with('colaborador.user')
            ->firstOrFail();

        $movimentos = BancoHorasMovimento::where('colaborador_config_id', $colaboradorId)
            ->orderByDesc('created_at')
            ->paginate(50);

        return view('pontowr2::banco-horas.show', compact('saldo', 'movimentos'));
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
